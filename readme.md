# FPP-AutoUpdate

An FPP plugin that automatically checks for and applies updates to your other installed FPP plugins. Designed to be safe to leave running during show season.

## What it does

- Periodically checks each enabled plugin's git remote for updates
- Refuses to update while FPP is playing, or if a scheduled show starts within a configurable buffer window
- Skips plugins with uncommitted local edits (never clobbers your hand modifications)
- Logs every run to `/home/fpp/media/plugindata/FPP-AutoUpdate.log` in JSONL format
- Supports a per-plugin allowlist, dry-run mode, and check-only mode
- Optionally restarts fppd after a successful batch update

## What it does NOT do

- Update itself (do that manually through FPP's Plugin Manager — keeps recovery simple if a release breaks)
- Auto-rollback (use `git reset --hard HEAD~1` in the affected plugin directory if needed)
- Update plugins that weren't installed via git (zip-installed plugins have no remote to fetch from)

## Installation

Install via FPP's Plugin Manager. The post-install script sets up:

- `/etc/cron.d/FPP-AutoUpdate` — runs the checker every 15 minutes
- `/etc/sudoers.d/FPP-AutoUpdate` — passwordless `systemctl restart fppd` for the fpp user (only used if you enable that option)

After install, navigate to **Content Setup → Plugin Auto-Update** to configure.

## Configuration

All settings live in `/home/fpp/media/plugindata/FPP-AutoUpdate.json`. The settings page writes this file; you can also edit it directly. See `help.php` (or the in-app Help page) for full documentation of each option.

## Logs

Tail recent activity:

```bash
tail -f /home/fpp/media/plugindata/FPP-AutoUpdate.log | jq .
```

## Development

The plugin is pure PHP + bash. No build step.

- `autoupdate.php` — settings page (HTML/CSS/JS in one file)
- `api.php` — AJAX endpoints
- `lib/` — config, scanner, history modules
- `scripts/checker.sh` — main update loop, runs from cron
- `scripts/fpp-status.sh` — safety check (idle + schedule)

## License

MIT
