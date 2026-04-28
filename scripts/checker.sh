#!/bin/bash
# checker.sh — Main update loop. Runs from cron at the user's chosen interval.
#
# Reads config from /home/fpp/media/plugindata/fpp-AutoUpdate.json,
# evaluates the safety gates, and either checks-only or applies updates
# to each enabled plugin. Every action is appended to the history log.

set -uo pipefail

PLUGIN_DIR="/home/fpp/media/plugins"
PLUGINDATA_DIR="/home/fpp/media/plugindata"
SELF_DIR="$PLUGIN_DIR/fpp-AutoUpdate"
CONFIG_FILE="$PLUGINDATA_DIR/fpp-AutoUpdate.json"
LOG_FILE="$PLUGINDATA_DIR/fpp-AutoUpdate.log"
LOCK_FILE="/tmp/fpp-AutoUpdate.lock"

# Single-instance lock — prevents two cron invocations stomping on each
# other if a previous run is still doing a slow git pull.
exec 9>"$LOCK_FILE"
if ! flock -n 9; then
    echo "Another fpp-AutoUpdate run is in progress; exiting." >&2
    exit 0
fi

log_event() {
    # Args: action plugin_name [extra_json_fragment]
    local action="$1"
    local plugin="$2"
    local extra="${3:-}"
    local ts
    ts="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

    local line
    if [[ -n "$extra" ]]; then
        line="{\"ts\":\"${ts}\",\"plugin\":\"${plugin}\",\"action\":\"${action}\",${extra}}"
    else
        line="{\"ts\":\"${ts}\",\"plugin\":\"${plugin}\",\"action\":\"${action}\"}"
    fi

    echo "$line" >> "$LOG_FILE"
}

# Bail if config doesn't exist — first run before user has visited
# settings page.
if [[ ! -f "$CONFIG_FILE" ]]; then
    exit 0
fi

# Load config values via jq.
mode="$(jq -r '.mode // "check_only"' "$CONFIG_FILE")"
dry_run="$(jq -r '.dryRun // false' "$CONFIG_FILE")"
window_type="$(jq -r '.updateWindow.type // "idle_only"' "$CONFIG_FILE")"
earliest_hour="$(jq -r '.updateWindow.earliestHour // 2' "$CONFIG_FILE")"
latest_hour="$(jq -r '.updateWindow.latestHour // 5' "$CONFIG_FILE")"
buffer_min="$(jq -r '.scheduleBufferMinutes // 10' "$CONFIG_FILE")"
restart_fppd="$(jq -r '.restartFppdAfterBatch // false' "$CONFIG_FILE")"

if [[ "$mode" == "disabled" ]]; then
    exit 0
fi

# Manual-only mode: never run from cron, only from the "Check now" button.
# The button passes --manual to override.
if [[ "$window_type" == "manual_only" ]] && [[ "${1:-}" != "--manual" ]]; then
    exit 0
fi

# specific_hours: restrict to user-defined hour range.
if [[ "$window_type" == "specific_hours" ]] && [[ "${1:-}" != "--manual" ]]; then
    current_hour="$(date +%-H)"
    if (( earliest_hour <= latest_hour )); then
        # Normal range: e.g. 02-05.
        if (( current_hour < earliest_hour )) || (( current_hour > latest_hour )); then
            exit 0
        fi
    else
        # Wraps midnight: e.g. 22-05.
        if (( current_hour < earliest_hour )) && (( current_hour > latest_hour )); then
            exit 0
        fi
    fi
fi

# Safety gate — fpp must be idle and no show starting soon.
status_output="$("$SELF_DIR/scripts/fpp-status.sh" "$buffer_min" 2>/dev/null || true)"
status_reason="$(echo "$status_output" | grep '^REASON=' | head -1 | cut -d= -f2-)"

if [[ "$status_reason" != "ok" ]]; then
    log_event "skipped" "all" "\"reason\":\"${status_reason}\""
    exit 0
fi

# Iterate plugins. Anything in the directory with a .git checkout is a
# candidate; we filter by the per-plugin allowlist from config.
fppd_restart_needed=false
checked_count=0
applied_count=0

for plugin_path in "$PLUGIN_DIR"/*/; do
    plugin_name="$(basename "$plugin_path")"

    # Never touch ourselves.
    if [[ "$plugin_name" == "fpp-AutoUpdate" ]]; then
        continue
    fi

    # Must be a git checkout.
    if [[ ! -d "$plugin_path/.git" ]]; then
        continue
    fi

    # Allowlist check — plugin must be enabled in config.
    enabled="$(jq -r --arg p "$plugin_name" '.plugins[$p].enabled // false' "$CONFIG_FILE")"
    if [[ "$enabled" != "true" ]]; then
        continue
    fi

    checked_count=$((checked_count + 1))

    # Dirty-worktree check — if user has hand-edited files, leave it alone.
    if [[ -n "$(cd "$plugin_path" && git status --porcelain 2>/dev/null)" ]]; then
        log_event "skipped" "$plugin_name" "\"reason\":\"dirty_worktree\""
        continue
    fi

    # Fetch with timeout.
    if ! (cd "$plugin_path" && timeout 30 git fetch --quiet 2>/dev/null); then
        log_event "error" "$plugin_name" "\"reason\":\"fetch_failed\""
        continue
    fi

    local_sha="$(cd "$plugin_path" && git rev-parse HEAD 2>/dev/null || echo)"
    remote_sha="$(cd "$plugin_path" && git rev-parse '@{u}' 2>/dev/null || echo)"

    if [[ -z "$local_sha" ]] || [[ -z "$remote_sha" ]]; then
        log_event "error" "$plugin_name" "\"reason\":\"sha_lookup_failed\""
        continue
    fi

    if [[ "$local_sha" == "$remote_sha" ]]; then
        # Already up to date — log nothing per-plugin to keep history clean.
        continue
    fi

    local_short="${local_sha:0:7}"
    remote_short="${remote_sha:0:7}"

    # Check-only mode: log availability and stop.
    if [[ "$mode" == "check_only" ]]; then
        log_event "available" "$plugin_name" "\"from\":\"${local_short}\",\"to\":\"${remote_short}\""
        continue
    fi

    # auto_apply mode below.
    if [[ "$dry_run" == "true" ]]; then
        log_event "dry_run" "$plugin_name" "\"from\":\"${local_short}\",\"to\":\"${remote_short}\""
        continue
    fi

    # Real update.
    if ! (cd "$plugin_path" && timeout 60 git pull --quiet 2>/dev/null); then
        log_event "error" "$plugin_name" "\"reason\":\"pull_failed\",\"from\":\"${local_short}\",\"to\":\"${remote_short}\""
        continue
    fi

    # Run the plugin's install script if it has one. FPP convention is
    # scripts/fpp_install.sh; some older plugins have install.sh at root.
    install_ran=false
    if [[ -x "$plugin_path/scripts/fpp_install.sh" ]]; then
        if (cd "$plugin_path" && timeout 300 ./scripts/fpp_install.sh >/dev/null 2>&1); then
            install_ran=true
        fi
    elif [[ -x "$plugin_path/install.sh" ]]; then
        if (cd "$plugin_path" && timeout 300 ./install.sh >/dev/null 2>&1); then
            install_ran=true
        fi
    fi

    log_event "applied" "$plugin_name" "\"from\":\"${local_short}\",\"to\":\"${remote_short}\",\"installRan\":${install_ran}"
    applied_count=$((applied_count + 1))

    # Per-plugin restart override.
    plugin_needs_restart="$(jq -r --arg p "$plugin_name" '.plugins[$p].restartAfterUpdate // false' "$CONFIG_FILE")"
    if [[ "$plugin_needs_restart" == "true" ]]; then
        fppd_restart_needed=true
    fi
done

# Optional fppd restart after batch.
if [[ "$applied_count" -gt 0 ]]; then
    if [[ "$restart_fppd" == "true" ]] || [[ "$fppd_restart_needed" == "true" ]]; then
        if [[ "$dry_run" != "true" ]]; then
            log_event "fppd_restart" "system" ""
            sudo systemctl restart fppd 2>/dev/null || true
        fi
    fi
fi

# Summary line at the end of every successful run, even if nothing happened.
log_event "run_complete" "system" "\"checked\":${checked_count},\"applied\":${applied_count}"

exit 0
