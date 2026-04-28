<?php
// Append-only JSONL log of update activity. One line per event.
//
// Format: {"ts":"...","plugin":"...","action":"applied|skipped|no_change|error","reason":"...","from":"...","to":"..."}
//
// Tail-friendly, easy to parse, rotates by age. We keep N days of history
// (configurable, default 30) — beyond that, lines are pruned on next write.

if (!defined('FPP_AUTOUPDATE_LOG_PATH')) {
    define('FPP_AUTOUPDATE_LOG_PATH', '/home/fpp/media/plugindata/fpp-AutoUpdate.log');
}

function autoupdate_log_event(array $event) {
    $event['ts'] = $event['ts'] ?? gmdate('c');

    $line = json_encode($event, JSON_UNESCAPED_SLASHES);
    if ($line === false) {
        return false;
    }

    $path = FPP_AUTOUPDATE_LOG_PATH;
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    return @file_put_contents($path, $line . "\n", FILE_APPEND | LOCK_EX) !== false;
}

function autoupdate_log_read($limit = 50) {
    $path = FPP_AUTOUPDATE_LOG_PATH;
    if (!file_exists($path)) {
        return [];
    }

    // Read the whole file. For 30 days of activity at typical volumes this
    // is small — no need for a streaming tail. If volume becomes a problem
    // later, switch to reading the last N KB and parsing forward.
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }

    $events = [];
    foreach ($lines as $line) {
        $parsed = json_decode($line, true);
        if (is_array($parsed)) {
            $events[] = $parsed;
        }
    }

    // Newest first.
    $events = array_reverse($events);

    if ($limit > 0) {
        $events = array_slice($events, 0, $limit);
    }

    return $events;
}

function autoupdate_log_prune($retentionDays) {
    $path = FPP_AUTOUPDATE_LOG_PATH;
    if (!file_exists($path)) {
        return;
    }

    $cutoff = time() - (intval($retentionDays) * 86400);
    if ($cutoff <= 0) {
        return;
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    $kept = [];
    foreach ($lines as $line) {
        $parsed = json_decode($line, true);
        if (!is_array($parsed) || !isset($parsed['ts'])) {
            continue;
        }
        $ts = strtotime($parsed['ts']);
        if ($ts !== false && $ts >= $cutoff) {
            $kept[] = $line;
        }
    }

    $tmp = $path . '.tmp';
    if (@file_put_contents($tmp, implode("\n", $kept) . (count($kept) ? "\n" : ''), LOCK_EX) !== false) {
        @rename($tmp, $path);
    }
}
