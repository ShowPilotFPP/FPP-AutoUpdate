<?php
// FPP-AutoUpdate help page.
?>
<style>
.au-help { max-width: 780px; margin: 0 auto; padding: 24px 16px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.6; }
.au-help h2 { margin-top: 0; font-weight: 500; }
.au-help h3 { margin-top: 28px; font-size: 16px; font-weight: 500; }
.au-help p, .au-help li { font-size: 14px; color: #1a1a1a; }
.au-help code { background: #f0f2f5; padding: 1px 6px; border-radius: 4px; font-size: 13px; }
.au-help .au-help-warn { background: #fffbeb; border: 1px solid rgba(217, 119, 6, 0.2); padding: 12px 16px; border-radius: 8px; margin: 16px 0; }
.au-help-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 4px; }
.au-help-back {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 14px;
    background: #ffffff; color: #1a1a1a;
    border: 1px solid rgba(0,0,0,0.16); border-radius: 8px;
    font-size: 13px; font-weight: 500;
    text-decoration: none;
    transition: background 0.12s ease;
}
.au-help-back:hover { background: #f6f7f9; color: #1a1a1a; text-decoration: none; }
</style>

<div class="au-help">
    <div class="au-help-header">
        <h2>Plugin Auto-Update — Help</h2>
        <a class="au-help-back" href="/" title="Return to FPP main interface">← Back to FPP</a>
    </div>

    <p>FPP-AutoUpdate keeps your other installed FPP plugins up to date by periodically pulling from their git repositories. It's designed to be safe to leave running during show season — it refuses to update while FPP is playing or about to play a scheduled show.</p>

    <h3>Modes</h3>
    <ul>
        <li><strong>Disabled</strong> — Nothing runs. The cron job exits immediately.</li>
        <li><strong>Check only</strong> — Periodically fetches each enabled plugin and logs which ones have updates available, but never applies them. Good for getting comfortable with the tool before letting it write to your plugin directories.</li>
        <li><strong>Auto-apply</strong> — Fetches and pulls. Runs each plugin's <code>scripts/fpp_install.sh</code> if present.</li>
    </ul>

    <h3>Dry-run mode</h3>
    <p>Available in any mode. The plugin walks through the same logic but never executes <code>git pull</code> or any install script. Every action is still logged with the <code>dry_run</code> tag, so you can see exactly what would have happened.</p>

    <h3>Schedule</h3>
    <ul>
        <li><strong>Anytime FPP is idle</strong> — Default. Runs at the configured interval whenever fppd reports idle status and no scheduled show is starting within the buffer window.</li>
        <li><strong>Specific hours only</strong> — Restricts updates to a user-defined hour range. Useful if you want updates to happen overnight regardless of whether you've manually triggered playback.</li>
        <li><strong>Manual approval only</strong> — Cron does nothing. Updates only run when you click "Check now" on the settings page.</li>
    </ul>

    <h3>The schedule buffer</h3>
    <p>Even when fppd is idle, FPP-AutoUpdate checks the upcoming schedule. If any scheduled item starts within the buffer window (default 10 minutes), the run is skipped. This prevents the worst-case scenario: an update kicking off at 6:58pm just as your 7:00pm show is about to start.</p>

    <h3>Plugin allowlist</h3>
    <p>Every git-managed plugin in <code>/home/fpp/media/plugins</code> appears in the list. You opt each one in individually. Plugins with local modifications (uncommitted edits) are flagged "dirty" and cannot be enabled — the auto-updater never clobbers your hand edits.</p>

    <h3>Restart toggles</h3>
    <p>Most plugins don't need fppd restarted after an update — PHP files are re-read on next request. Some plugins run a background daemon and do need it. The "Restart" column lets you mark which plugins should trigger a post-batch fppd restart, and the global toggle below the table forces a restart after any successful batch regardless of per-plugin settings.</p>

    <div class="au-help-warn">
        FPP-AutoUpdate never updates itself. To update this plugin, use FPP's normal Plugin Manager. This avoids the failure mode where a broken self-update would prevent recovery without SSH access.
    </div>

    <h3>History log</h3>
    <p>Every run appends to <code>/home/fpp/media/plugindata/FPP-AutoUpdate.log</code> — JSONL format, one event per line. The settings page shows the most recent 20 events. Lines older than the configured retention period (default 30 days) are pruned on the next write.</p>

    <h3>Troubleshooting</h3>
    <ul>
        <li>If a plugin shows "fetch fail" repeatedly, check that the FPP host can reach github.com (DNS, firewall, GitHub outage).</li>
        <li>If "skipped: dirty_worktree" keeps appearing for a plugin you didn't intend to edit, run <code>cd /home/fpp/media/plugins/&lt;plugin&gt; && git status</code> to see what's marked as modified. Common culprit: file mode changes from copying via SFTP.</li>
        <li>The cron entry is installed at <code>/etc/cron.d/FPP-AutoUpdate</code>. If the schedule isn't running, verify the file exists and that cron itself is enabled.</li>
    </ul>
</div>
<script>document.title = 'Plugin Auto-Update — Help — FPP';</script>
