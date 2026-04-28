<?php
// API endpoints for the settings page. All requests POST a JSON body
// or a form-encoded "action" parameter. Responses are JSON.
//
// SAFETY GUARD: if invoked without an action parameter, exit silently
// before any output. Previously, an early v0.1.x release of this plugin
// caused FPP-wide /api/* endpoints to return 400 because something on the
// FPP side (still not fully diagnosed) was invoking this file with no
// action and capturing its error JSON into fppd's own stdout. Defending
// at the entry point is correct regardless of cause: there is no legitimate
// reason for any caller to invoke this script without specifying an action.
if (empty($_REQUEST['action'] ?? '') && PHP_SAPI !== 'cli') {
    // Web request without action — return empty 400, no body, no JSON.
    http_response_code(400);
    exit;
}
if (PHP_SAPI === 'cli') {
    // Never produce output when run from the command line.
    exit(0);
}

require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/plugin-scanner.php';
require_once __DIR__ . '/lib/history.php';

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'getState':
            // Combined endpoint — returns config + scanned plugin list +
            // recent history in one round-trip. The settings page calls
            // this on load.
            echo json_encode([
                'ok' => true,
                'config' => autoupdate_load_config(),
                'plugins' => autoupdate_scan_plugins(),
                'history' => autoupdate_log_read(20),
            ]);
            break;

        case 'checkRemotes':
            // For each enabled plugin in the scan, hit the remote to see
            // if updates are available. Slower than getState, so only
            // called when the user clicks "Check now".
            $plugins = autoupdate_scan_plugins();
            $results = [];
            foreach ($plugins as $p) {
                if ($p['isDirty']) {
                    $results[] = array_merge($p, ['hasUpdate' => false, 'remoteSha' => null]);
                    continue;
                }
                $remote = autoupdate_check_remote($p['path']);
                if ($remote === null) {
                    $results[] = array_merge($p, ['hasUpdate' => false, 'remoteSha' => null, 'fetchError' => true]);
                } else {
                    $results[] = array_merge($p, [
                        'hasUpdate' => $remote['hasUpdate'],
                        'remoteSha' => substr($remote['remoteSha'], 0, 7),
                    ]);
                }
            }
            echo json_encode(['ok' => true, 'plugins' => $results]);
            break;

        case 'saveConfig':
            $body = json_decode(file_get_contents('php://input'), true);
            if (!is_array($body)) {
                throw new RuntimeException('Invalid request body');
            }

            $current = autoupdate_load_config();
            $merged = autoupdate_merge_recursive($current, $body);

            $errors = autoupdate_validate_config($merged);
            if (!empty($errors)) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'errors' => $errors]);
                break;
            }

            if (!autoupdate_save_config($merged)) {
                http_response_code(500);
                echo json_encode(['ok' => false, 'errors' => ['failed to write config file']]);
                break;
            }

            echo json_encode(['ok' => true, 'config' => $merged]);
            break;

        case 'runNow':
            // Trigger checker.sh immediately with --manual flag so it
            // bypasses window restrictions. Run async — don't block the
            // HTTP request waiting for it to finish.
            $script = __DIR__ . '/scripts/checker.sh';
            $cmd = 'nohup ' . escapeshellarg($script) . ' --manual > /dev/null 2>&1 &';
            @exec($cmd);
            echo json_encode(['ok' => true, 'message' => 'Update run started in background']);
            break;

        case 'getHistory':
            $limit = intval($_REQUEST['limit'] ?? 50);
            echo json_encode(['ok' => true, 'history' => autoupdate_log_read($limit)]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'errors' => ['unknown action: ' . $action]]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'errors' => [$e->getMessage()]]);
}
