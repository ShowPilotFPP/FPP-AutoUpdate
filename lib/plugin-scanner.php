<?php
// Enumerate other plugins installed on this FPP instance and check their
// git state. We deliberately exclude ourselves from the scan — FPP-AutoUpdate
// never auto-updates itself; the user must update it manually through FPP's
// plugin manager. This avoids the "updater breaks itself" failure mode where
// you lose your update mechanism with no easy recovery path.

if (!defined('FPP_PLUGIN_DIR')) {
    define('FPP_PLUGIN_DIR', '/home/fpp/media/plugins');
}

define('AUTOUPDATE_SELF_REPO', 'FPP-AutoUpdate');

function autoupdate_scan_plugins() {
    $plugins = [];

    if (!is_dir(FPP_PLUGIN_DIR)) {
        return $plugins;
    }

    $entries = @scandir(FPP_PLUGIN_DIR);
    if ($entries === false) {
        return $plugins;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        if ($entry === AUTOUPDATE_SELF_REPO) {
            continue; // never include self
        }

        $path = FPP_PLUGIN_DIR . '/' . $entry;
        if (!is_dir($path)) {
            continue;
        }

        // Only treat directories with a .git as managed plugins. Plugins
        // installed manually (or as zip archives) won't appear here, which
        // is correct — we can't safely git-pull them.
        if (!is_dir($path . '/.git')) {
            continue;
        }

        $plugins[] = autoupdate_inspect_plugin($entry, $path);
    }

    usort($plugins, function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });

    return $plugins;
}

function autoupdate_inspect_plugin($name, $path) {
    $info = [
        'name' => $name,
        'path' => $path,
        'srcURL' => null,
        'currentRef' => null,
        'currentSha' => null,
        'remoteSha' => null,
        'hasUpdate' => false,
        'isDirty' => false,
        'hasInstallScript' => file_exists($path . '/scripts/fpp_install.sh') || file_exists($path . '/install.sh'),
        'description' => null,
    ];

    // Read pluginInfo.json for source URL and description if present.
    $infoFile = $path . '/pluginInfo.json';
    if (file_exists($infoFile)) {
        $parsed = json_decode(@file_get_contents($infoFile), true);
        if (is_array($parsed)) {
            $info['srcURL'] = $parsed['srcURL'] ?? null;
            $info['description'] = $parsed['description'] ?? null;
        }
    }

    // Current branch and short SHA — purely informational, useful for the UI.
    $info['currentRef'] = autoupdate_git_exec($path, ['rev-parse', '--abbrev-ref', 'HEAD']);
    $info['currentSha'] = autoupdate_git_exec($path, ['rev-parse', '--short', 'HEAD']);

    // Dirty check — any uncommitted local changes mean we leave this plugin
    // alone. We never stash, never reset --hard. The user's edits are theirs.
    //
    // Mode-bit noise is a real problem: SFTP uploads, apt installs, and
    // FPP's own scripts often flip executable bits on plugin files without
    // touching content. Treating those as "dirty" leaves users unable to
    // enable plugins they never modified. We run `git diff --quiet` with
    // -c core.fileMode=false to ignore mode-only differences, and check
    // for actual modified content. We DO still consider untracked files
    // as a signal that something is going on, but only via a separate path.
    $diffRc = -1;
    @exec('cd ' . escapeshellarg($path) . ' && git -c core.fileMode=false diff --quiet 2>/dev/null', $_, $diffRc);
    @exec('cd ' . escapeshellarg($path) . ' && git -c core.fileMode=false diff --cached --quiet 2>/dev/null', $_, $diffRcCached);
    $info['isDirty'] = ($diffRc !== 0) || ($diffRcCached !== 0);

    return $info;
}

function autoupdate_check_remote($path) {
    // Fetch with a timeout so a slow/unreachable remote doesn't hang the
    // whole scan. Returns ['localSha' => ..., 'remoteSha' => ..., 'hasUpdate' => bool]
    // or null if the fetch failed.

    $fetchOk = autoupdate_git_exec($path, ['fetch', '--quiet'], 30) !== null;
    if (!$fetchOk) {
        return null;
    }

    $local = autoupdate_git_exec($path, ['rev-parse', 'HEAD']);
    $upstream = autoupdate_git_exec($path, ['rev-parse', '@{u}']);

    if ($local === null || $upstream === null) {
        return null;
    }

    return [
        'localSha' => trim($local),
        'remoteSha' => trim($upstream),
        'hasUpdate' => trim($local) !== trim($upstream),
    ];
}

function autoupdate_git_exec($path, array $args, $timeoutSeconds = 10) {
    // Build the command with proper escaping. We never interpolate user
    // input directly; $args is always a fixed list of git subcommands.
    $cmd = 'cd ' . escapeshellarg($path) . ' && timeout ' . intval($timeoutSeconds) . ' git';
    foreach ($args as $arg) {
        $cmd .= ' ' . escapeshellarg($arg);
    }
    $cmd .= ' 2>&1';

    $output = [];
    $rc = 0;
    @exec($cmd, $output, $rc);

    if ($rc !== 0) {
        return null;
    }

    return implode("\n", $output);
}
