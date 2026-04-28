<?php
// FPP-AutoUpdate menu entries
//
// type values: status / content / output / help
// We use 'content' so the entry appears under "Content Setup", which is
// where most plugin configuration pages live.

$menuEntries = Array(
    Array(
        'text' => 'Plugin Auto-Update',
        'type' => 'content',
        'page' => 'autoupdate.php',
        'wrap' => 1
    ),
    Array(
        'text' => 'Plugin Auto-Update — Help',
        'type' => 'help',
        'page' => 'help.php',
        'wrap' => 1
    )
);

##############################################################################
# Display the menu entries for this plugin.
#
# This boilerplate matches FPP's expected menu.inc template — $plugin and
# $menu are set by FPP's menu loader before this file is included.

if (!isset($menuEntries) || !is_array($menuEntries)) {
    return;
}

foreach ($menuEntries as $entry) {
    if (!isset($entry['type']) || $entry['type'] !== $menu) {
        continue;
    }

    $href = strpos($entry['page'], 'http://') === 0 || strpos($entry['page'], 'https://') === 0
        ? $entry['page']
        : 'plugin.php?plugin=' . $plugin . '&page=' . $entry['page'] . '&nopage=' . (isset($entry['wrap']) ? (1 - intval($entry['wrap'])) : 0);

    echo '<li><a href="' . htmlspecialchars($href) . '">' . htmlspecialchars($entry['text']) . '</a></li>';
}
