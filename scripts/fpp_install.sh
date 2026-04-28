#!/bin/bash
# fpp_install.sh — runs after FPP clones the plugin via the Plugin Manager.
#
# Sets up:
#   - Executable bits on shell scripts
#   - cron entry at /etc/cron.d/fpp-AutoUpdate
#   - sudoers fragment to allow the fpp user to systemctl restart fppd
#     without a password (only used if "Restart fppd after batch" is on)

set -e

PLUGIN_DIR="/home/fpp/media/plugins/fpp-AutoUpdate"
CRON_FILE="/etc/cron.d/fpp-AutoUpdate"
SUDOERS_FILE="/etc/sudoers.d/fpp-AutoUpdate"

if [[ ! -d "$PLUGIN_DIR" ]]; then
    echo "Plugin directory not found at $PLUGIN_DIR" >&2
    exit 1
fi

# 1. Make scripts executable.
chmod +x "$PLUGIN_DIR/scripts/checker.sh" 2>/dev/null || true
chmod +x "$PLUGIN_DIR/scripts/fpp-status.sh" 2>/dev/null || true

# 2. Install the cron entry. We run every 15 minutes; checker.sh internally
# decides whether to act based on the user's checkInterval setting (it
# tracks last-run time in the lock file's timestamp and bails early if not
# enough time has passed).
#
# We use root for cron rather than the fpp user because some operations
# (apt installs run by plugin install scripts, fppd restart) need root.
if [[ ! -f "$CRON_FILE" ]] || ! grep -q "fpp-AutoUpdate/scripts/checker.sh" "$CRON_FILE"; then
    cat > "$CRON_FILE" <<'EOF'
# FPP-AutoUpdate — checks for plugin updates on a schedule.
# Runs every 15 minutes; the script itself checks user config to decide
# whether to actually do anything on each invocation.
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
*/15 * * * * root /home/fpp/media/plugins/fpp-AutoUpdate/scripts/checker.sh >/dev/null 2>&1
EOF
    chmod 644 "$CRON_FILE"
    echo "Installed cron entry at $CRON_FILE"
fi

# 3. Sudoers fragment for fppd restart. Only if "Restart fppd after batch"
# is enabled does this actually get used. We scope it to exactly the one
# command we need.
if [[ ! -f "$SUDOERS_FILE" ]]; then
    cat > "$SUDOERS_FILE" <<'EOF'
# FPP-AutoUpdate — allows passwordless fppd restart only.
fpp ALL=(root) NOPASSWD: /bin/systemctl restart fppd
EOF
    chmod 440 "$SUDOERS_FILE"
    # Validate sudoers syntax — if visudo rejects it, remove rather than
    # leave a broken file that could lock out sudo entirely.
    if ! visudo -c -f "$SUDOERS_FILE" >/dev/null 2>&1; then
        rm -f "$SUDOERS_FILE"
        echo "Warning: sudoers fragment failed validation, removed" >&2
    else
        echo "Installed sudoers fragment at $SUDOERS_FILE"
    fi
fi

# 4. Ensure plugindata directory exists.
mkdir -p /home/fpp/media/plugindata
chown -R fpp:fpp /home/fpp/media/plugindata 2>/dev/null || true

echo "FPP-AutoUpdate installation complete"
exit 0
