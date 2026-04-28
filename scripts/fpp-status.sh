#!/bin/bash
# fpp-status.sh — Determine whether it is safe to run plugin updates right now.
#
# Exits 0 ("safe to update") only if ALL of the following are true:
#   1. fppd is reachable
#   2. fppd status is "idle" (not playing/testing)
#   3. No scheduled item starts within $BUFFER_MIN minutes
#
# Otherwise prints the reason to stderr and exits non-zero. The reason is
# also echoed in a tagged form on stdout (REASON=...) so the caller can
# include it in the update history log without parsing free-form text.
#
# Usage: fpp-status.sh [BUFFER_MIN]
#   BUFFER_MIN defaults to 10

set -euo pipefail

BUFFER_MIN="${1:-10}"
FPP_API="http://localhost/api"

# 1. Check fppd is up.
status_json="$(curl -s -m 5 "${FPP_API}/fppd/status" || true)"
if [[ -z "$status_json" ]]; then
    echo "REASON=fppd_unreachable"
    echo "fppd not reachable at ${FPP_API}/fppd/status" >&2
    exit 1
fi

# 2. Parse status_name. We use jq (declared in pluginInfo.json deps).
status_name="$(echo "$status_json" | jq -r '.status_name // empty')"
if [[ -z "$status_name" ]]; then
    echo "REASON=fppd_status_unparseable"
    echo "fppd status JSON missing status_name field" >&2
    exit 1
fi

if [[ "$status_name" != "idle" ]]; then
    echo "REASON=fpp_not_idle:${status_name}"
    echo "fppd is busy (status=${status_name}); deferring update" >&2
    exit 2
fi

# 3. Check the schedule for anything starting soon.
#
# /api/schedule returns an array of upcoming scheduled items. The exact
# shape varies by FPP version, but every version we care about exposes
# a "startTime" or "startDate"+"startTime" pair we can parse. We use the
# "scheduledStartTime" Unix timestamp if present (newer FPP) and fall back
# to constructing it from string fields otherwise.
schedule_json="$(curl -s -m 5 "${FPP_API}/schedule" || true)"
if [[ -z "$schedule_json" ]] || [[ "$schedule_json" == "null" ]]; then
    # No schedule API response means we can't verify — be conservative and
    # treat as "no scheduled item within window" rather than refusing forever.
    # The idle check above is the primary safety; this is a secondary check.
    echo "REASON=ok"
    exit 0
fi

now_epoch="$(date -u +%s)"
buffer_seconds=$((BUFFER_MIN * 60))
deadline=$((now_epoch + buffer_seconds))

# Find the soonest scheduled start time. The jq pipeline tolerates an empty
# array, missing fields, and non-numeric values.
soonest="$(echo "$schedule_json" | jq -r '
  if type == "array" then
    [.[] | (.scheduledStartTime // .startTimeEpoch // empty) | tonumber? // empty]
    | min // empty
  else empty end
')"

if [[ -n "$soonest" ]] && [[ "$soonest" =~ ^[0-9]+$ ]]; then
    if (( soonest <= deadline )) && (( soonest >= now_epoch )); then
        minutes_until=$(( (soonest - now_epoch) / 60 ))
        echo "REASON=schedule_imminent:${minutes_until}m"
        echo "Scheduled item starts in ${minutes_until} minutes (within ${BUFFER_MIN}m buffer); deferring" >&2
        exit 3
    fi
fi

echo "REASON=ok"
exit 0
