<?php
// Settings storage for FPP-AutoUpdate.
//
// Settings live at /home/fpp/media/plugindata/FPP-AutoUpdate.json so they
// survive plugin reinstalls (FPP wipes the plugin directory on update but
// preserves plugindata/).

if (!defined('FPP_AUTOUPDATE_CONFIG_PATH')) {
    // Allow override for tests; default to FPP's standard plugindata location.
    define('FPP_AUTOUPDATE_CONFIG_PATH', '/home/fpp/media/plugindata/FPP-AutoUpdate.json');
}

function autoupdate_default_config() {
    return [
        'mode' => 'check_only',           // disabled | check_only | auto_apply
        'dryRun' => false,
        'checkInterval' => '1h',          // 15m | 1h | 6h | 24h
        'updateWindow' => [
            'type' => 'idle_only',        // idle_only | specific_hours | manual_only
            'earliestHour' => 2,
            'latestHour' => 5,
        ],
        'scheduleBufferMinutes' => 10,
        'plugins' => (object)[],          // keyed by plugin directory name
        'restartFppdAfterBatch' => false,
        'historyRetentionDays' => 30,
    ];
}

function autoupdate_load_config() {
    $path = FPP_AUTOUPDATE_CONFIG_PATH;
    $defaults = autoupdate_default_config();

    if (!file_exists($path)) {
        return $defaults;
    }

    $raw = @file_get_contents($path);
    if ($raw === false) {
        return $defaults;
    }

    $parsed = json_decode($raw, true);
    if (!is_array($parsed)) {
        return $defaults;
    }

    // Merge over defaults so newly-added keys appear with sensible values
    // even on configs written by older versions.
    return autoupdate_merge_recursive($defaults, $parsed);
}

function autoupdate_save_config($config) {
    $path = FPP_AUTOUPDATE_CONFIG_PATH;
    $dir = dirname($path);

    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }

    // Atomic write — write to .tmp and rename.
    $tmp = $path . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) === false) {
        return false;
    }

    return rename($tmp, $path);
}

function autoupdate_merge_recursive($defaults, $overrides) {
    foreach ($overrides as $key => $value) {
        if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key])) {
            $defaults[$key] = autoupdate_merge_recursive($defaults[$key], $value);
        } else {
            $defaults[$key] = $value;
        }
    }
    return $defaults;
}

function autoupdate_validate_config($config) {
    $errors = [];

    if (!in_array($config['mode'] ?? null, ['disabled', 'check_only', 'auto_apply'], true)) {
        $errors[] = 'mode must be disabled, check_only, or auto_apply';
    }

    $intervals = ['15m', '1h', '6h', '24h'];
    if (!in_array($config['checkInterval'] ?? null, $intervals, true)) {
        $errors[] = 'checkInterval must be one of: ' . implode(', ', $intervals);
    }

    $windowTypes = ['idle_only', 'specific_hours', 'manual_only'];
    if (!in_array($config['updateWindow']['type'] ?? null, $windowTypes, true)) {
        $errors[] = 'updateWindow.type invalid';
    }

    $eh = $config['updateWindow']['earliestHour'] ?? null;
    $lh = $config['updateWindow']['latestHour'] ?? null;
    if (!is_int($eh) || $eh < 0 || $eh > 23) {
        $errors[] = 'earliestHour must be 0-23';
    }
    if (!is_int($lh) || $lh < 0 || $lh > 23) {
        $errors[] = 'latestHour must be 0-23';
    }

    $buf = $config['scheduleBufferMinutes'] ?? null;
    if (!is_int($buf) || $buf < 0 || $buf > 120) {
        $errors[] = 'scheduleBufferMinutes must be 0-120';
    }

    return $errors;
}
