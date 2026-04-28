<?php
// FPP-AutoUpdate settings page.
//
// FPP includes this file inside its own page chrome (header/menu/footer).
// We render only the body content and load our JS/CSS inline.

require_once __DIR__ . '/lib/config.php';

$pluginUrl = 'plugin.php?plugin=FPP-AutoUpdate&page=autoupdate.php';
$apiUrl = '/plugin.php?plugin=FPP-AutoUpdate&_route=api';
// Direct API access path — FPP's plugin.php router doesn't proxy POST
// bodies cleanly to api.php, so we hit it via the plugin asset path.
$apiDirect = '/plugins/FPP-AutoUpdate/api.php';
?>
<style>
:root {
    --au-bg: #ffffff;
    --au-bg-soft: #f6f7f9;
    --au-bg-page: #f0f2f5;
    --au-border: rgba(0, 0, 0, 0.08);
    --au-border-strong: rgba(0, 0, 0, 0.16);
    --au-text: #1a1a1a;
    --au-text-muted: #6b7280;
    --au-text-faint: #9ca3af;
    --au-accent: #2563eb;
    --au-accent-bg: #eff6ff;
    --au-accent-text: #1e40af;
    --au-success: #059669;
    --au-success-bg: #ecfdf5;
    --au-warning: #d97706;
    --au-warning-bg: #fffbeb;
    --au-danger: #dc2626;
    --au-danger-bg: #fef2f2;
    --au-radius: 8px;
    --au-radius-lg: 12px;
}

.au-root {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: var(--au-text);
    max-width: 980px;
    margin: 0 auto;
    padding: 24px 16px;
}
.au-root *, .au-root *::before, .au-root *::after { box-sizing: border-box; }

.au-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.au-header h2 { margin: 0 0 4px; font-size: 22px; font-weight: 500; }
.au-header p { margin: 0; font-size: 13px; color: var(--au-text-muted); }
.au-header-actions { display: flex; gap: 8px; }

.au-card {
    background: var(--au-bg);
    border: 1px solid var(--au-border);
    border-radius: var(--au-radius-lg);
    padding: 18px 20px;
    margin-bottom: 16px;
}
.au-card h3 { margin: 0 0 14px; font-size: 16px; font-weight: 500; }

.au-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    background: var(--au-bg);
    color: var(--au-text);
    border: 1px solid var(--au-border-strong);
    border-radius: var(--au-radius);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.12s ease, border-color 0.12s ease;
    line-height: 1.2;
}
.au-btn:hover { background: var(--au-bg-soft); }
.au-btn:active { transform: scale(0.98); }
.au-btn-primary {
    background: var(--au-accent);
    color: #fff;
    border-color: var(--au-accent);
}
.au-btn-primary:hover { background: #1d4ed8; }
.au-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.au-mode-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin-bottom: 4px;
}
.au-mode-option {
    display: block;
    border: 1px solid var(--au-border);
    border-radius: var(--au-radius);
    padding: 12px;
    cursor: pointer;
    background: var(--au-bg-soft);
    transition: all 0.12s ease;
}
.au-mode-option:hover { border-color: var(--au-border-strong); }
.au-mode-option.selected {
    border: 2px solid var(--au-accent);
    background: var(--au-accent-bg);
    padding: 11px;
}
.au-mode-option input { margin-right: 8px; }
.au-mode-option .au-mode-title { font-weight: 500; font-size: 14px; }
.au-mode-option .au-mode-desc { font-size: 12px; color: var(--au-text-muted); margin: 4px 0 0; }
.au-mode-option.selected .au-mode-title { color: var(--au-accent-text); }
.au-mode-option.selected .au-mode-desc { color: var(--au-accent-text); opacity: 0.85; }

.au-divider {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--au-border);
}
.au-checkbox-row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}
.au-checkbox-row .au-hint {
    margin-left: auto;
    font-size: 12px;
    color: var(--au-text-muted);
}

.au-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 12px;
}
.au-field label {
    display: block;
    font-size: 13px;
    color: var(--au-text-muted);
    margin-bottom: 6px;
}
.au-field select,
.au-field input[type="text"],
.au-field input[type="number"] {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid var(--au-border-strong);
    border-radius: var(--au-radius);
    font-size: 14px;
    background: var(--au-bg);
    color: var(--au-text);
    font-family: inherit;
}
.au-field select:focus,
.au-field input:focus {
    outline: 2px solid var(--au-accent);
    outline-offset: -1px;
    border-color: var(--au-accent);
}

.au-banner {
    padding: 10px 12px;
    border-radius: var(--au-radius);
    font-size: 13px;
}
.au-banner-warning {
    background: var(--au-warning-bg);
    color: var(--au-warning);
    border: 1px solid rgba(217, 119, 6, 0.2);
}

.au-plugin-table {
    border: 1px solid var(--au-border);
    border-radius: var(--au-radius);
    overflow: hidden;
}
.au-plugin-row {
    display: grid;
    grid-template-columns: 32px 1fr 110px 110px 80px;
    gap: 12px;
    padding: 12px;
    align-items: center;
    border-top: 1px solid var(--au-border);
    font-size: 14px;
}
.au-plugin-row:first-child { border-top: none; }
.au-plugin-row.au-plugin-header {
    background: var(--au-bg-soft);
    color: var(--au-text-muted);
    font-size: 12px;
    font-weight: 500;
    padding: 10px 12px;
}
.au-plugin-name { font-weight: 500; }
.au-plugin-source { font-size: 12px; color: var(--au-text-muted); }
.au-mono { font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, monospace; font-size: 12px; }
.au-text-success { color: var(--au-success); }
.au-text-warning { color: var(--au-warning); }
.au-text-faint { color: var(--au-text-faint); }
.au-text-center { text-align: center; }
.au-plugin-row input[type="checkbox"]:disabled { opacity: 0.4; }
.au-plugin-dimmed .au-plugin-name { color: var(--au-text-faint); }

.au-history-row {
    display: grid;
    grid-template-columns: 150px 90px 1fr;
    gap: 12px;
    padding: 8px 0;
    border-bottom: 1px solid var(--au-border);
    font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, monospace;
    font-size: 12px;
}
.au-history-row:last-child { border-bottom: none; }
.au-history-ts { color: var(--au-text-muted); }
.au-history-empty {
    color: var(--au-text-muted);
    font-size: 13px;
    padding: 16px 0;
    text-align: center;
}

.au-toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    padding: 12px 18px;
    background: var(--au-text);
    color: #fff;
    border-radius: var(--au-radius);
    font-size: 13px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    opacity: 0;
    transform: translateY(8px);
    transition: opacity 0.2s, transform 0.2s;
    pointer-events: none;
    z-index: 9999;
}
.au-toast.show { opacity: 1; transform: translateY(0); }

.au-loading {
    color: var(--au-text-muted);
    padding: 24px;
    text-align: center;
    font-size: 13px;
}

.au-row-flex { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.au-link-faint { font-size: 12px; color: var(--au-accent); text-decoration: none; }
.au-link-faint:hover { text-decoration: underline; }
.au-count-pill { font-size: 12px; color: var(--au-text-muted); }
</style>

<div class="au-root">

    <div class="au-header">
        <div>
            <h2>FPP-AutoUpdate</h2>
            <p>Keep installed plugins up to date automatically</p>
        </div>
        <div class="au-header-actions">
            <button class="au-btn" id="au-check-now">Check now</button>
            <button class="au-btn au-btn-primary" id="au-save">Save</button>
        </div>
    </div>

    <div class="au-card">
        <h3>Mode</h3>
        <div class="au-mode-grid">
            <label class="au-mode-option" data-mode="disabled">
                <input type="radio" name="au-mode" value="disabled" />
                <span class="au-mode-title">Disabled</span>
                <p class="au-mode-desc">No checking, no updates</p>
            </label>
            <label class="au-mode-option" data-mode="check_only">
                <input type="radio" name="au-mode" value="check_only" />
                <span class="au-mode-title">Check only</span>
                <p class="au-mode-desc">Notify, don't apply</p>
            </label>
            <label class="au-mode-option" data-mode="auto_apply">
                <input type="radio" name="au-mode" value="auto_apply" />
                <span class="au-mode-title">Auto-apply</span>
                <p class="au-mode-desc">Check and install</p>
            </label>
        </div>
        <div class="au-divider au-checkbox-row">
            <input type="checkbox" id="au-dryrun" />
            <label for="au-dryrun">Dry-run mode</label>
            <span class="au-hint">Log what would happen, change nothing</span>
        </div>
    </div>

    <div class="au-card">
        <h3>Schedule</h3>
        <div class="au-grid-2">
            <div class="au-field">
                <label>Check every</label>
                <select id="au-interval">
                    <option value="15m">15 minutes</option>
                    <option value="1h">1 hour</option>
                    <option value="6h">6 hours</option>
                    <option value="24h">Once daily</option>
                </select>
            </div>
            <div class="au-field">
                <label>Update window</label>
                <select id="au-window-type">
                    <option value="idle_only">Anytime FPP is idle</option>
                    <option value="specific_hours">Specific hours only</option>
                    <option value="manual_only">Manual approval only</option>
                </select>
            </div>
        </div>
        <div class="au-grid-2" id="au-hour-fields">
            <div class="au-field">
                <label>Earliest hour (0-23)</label>
                <input type="number" id="au-earliest" min="0" max="23" />
            </div>
            <div class="au-field">
                <label>Latest hour (0-23)</label>
                <input type="number" id="au-latest" min="0" max="23" />
            </div>
        </div>
        <div class="au-banner au-banner-warning">
            Scheduled show buffer: skip updates if a playlist starts within
            <input type="number" id="au-buffer" min="0" max="120" style="width: 56px; padding: 2px 6px; margin: 0 4px; border-radius: 4px; border: 1px solid rgba(217,119,6,0.3);" />
            minutes
        </div>
    </div>

    <div class="au-card">
        <div class="au-row-flex">
            <h3 style="margin: 0;">Plugin allowlist</h3>
            <span class="au-count-pill" id="au-plugin-count">0 of 0 enabled</span>
        </div>
        <div class="au-plugin-table" id="au-plugin-table">
            <div class="au-loading">Scanning plugins...</div>
        </div>
        <div class="au-divider au-checkbox-row">
            <input type="checkbox" id="au-restart-fppd" />
            <label for="au-restart-fppd">Restart fppd after batch update completes</label>
        </div>
    </div>

    <div class="au-card">
        <div class="au-row-flex">
            <h3 style="margin: 0;">Update history</h3>
            <a href="#" class="au-link-faint" id="au-history-refresh">Refresh</a>
        </div>
        <div id="au-history">
            <div class="au-loading">Loading history...</div>
        </div>
    </div>

</div>

<div class="au-toast" id="au-toast"></div>

<script>
(function () {
    'use strict';

    // API endpoint — direct path avoids FPP's plugin.php POST-body proxying.
    var API = '/plugins/FPP-AutoUpdate/api.php';

    var state = {
        config: null,
        plugins: [],
    };

    // --- DOM helpers ---

    function $(id) { return document.getElementById(id); }
    function el(tag, attrs, children) {
        var node = document.createElement(tag);
        if (attrs) {
            for (var k in attrs) {
                if (k === 'className') node.className = attrs[k];
                else if (k === 'onclick') node.onclick = attrs[k];
                else if (k === 'onchange') node.onchange = attrs[k];
                else node.setAttribute(k, attrs[k]);
            }
        }
        if (children) {
            (Array.isArray(children) ? children : [children]).forEach(function (c) {
                if (c == null) return;
                node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
            });
        }
        return node;
    }

    function toast(msg, isError) {
        var t = $('au-toast');
        t.textContent = msg;
        t.style.background = isError ? '#dc2626' : '#1a1a1a';
        t.classList.add('show');
        setTimeout(function () { t.classList.remove('show'); }, 2500);
    }

    // --- API calls ---

    function api(action, opts) {
        opts = opts || {};
        var url = API + '?action=' + encodeURIComponent(action);
        var init = { method: opts.method || 'GET' };
        if (opts.body) {
            init.method = 'POST';
            init.headers = { 'Content-Type': 'application/json' };
            init.body = JSON.stringify(opts.body);
        }
        return fetch(url, init).then(function (r) {
            return r.json().then(function (j) {
                if (!j.ok) throw new Error((j.errors || ['Unknown error']).join('; '));
                return j;
            });
        });
    }

    // --- Render ---

    function renderMode() {
        var mode = state.config.mode;
        var labels = document.querySelectorAll('.au-mode-option');
        labels.forEach(function (l) {
            var radio = l.querySelector('input');
            if (l.dataset.mode === mode) {
                l.classList.add('selected');
                radio.checked = true;
            } else {
                l.classList.remove('selected');
                radio.checked = false;
            }
        });
        $('au-dryrun').checked = !!state.config.dryRun;
    }

    function renderSchedule() {
        $('au-interval').value = state.config.checkInterval;
        $('au-window-type').value = state.config.updateWindow.type;
        $('au-earliest').value = state.config.updateWindow.earliestHour;
        $('au-latest').value = state.config.updateWindow.latestHour;
        $('au-buffer').value = state.config.scheduleBufferMinutes;

        var showHours = state.config.updateWindow.type === 'specific_hours';
        $('au-hour-fields').style.display = showHours ? 'grid' : 'none';
    }

    function renderPlugins() {
        var table = $('au-plugin-table');
        table.innerHTML = '';

        if (state.plugins.length === 0) {
            table.appendChild(el('div', { className: 'au-loading' },
                'No git-based plugins found. (Plugins installed manually outside FPP\'s plugin manager are not auto-updateable.)'));
            $('au-plugin-count').textContent = '0 of 0 enabled';
            return;
        }

        // Header row.
        table.appendChild(el('div', { className: 'au-plugin-row au-plugin-header' }, [
            el('span'),
            el('span', null, 'Plugin'),
            el('span', null, 'Current'),
            el('span', null, 'Available'),
            el('span', { className: 'au-text-center' }, 'Restart'),
        ]));

        var enabledCount = 0;

        state.plugins.forEach(function (p) {
            var pluginCfg = state.config.plugins[p.name] || { enabled: false, restartAfterUpdate: false };
            if (pluginCfg.enabled) enabledCount++;

            var current = p.currentSha
                ? (p.currentSha.trim().substring(0, 7))
                : 'unknown';

            var available;
            if (p.isDirty) {
                available = el('span', { className: 'au-mono au-text-warning' }, 'dirty');
            } else if (p.fetchError) {
                available = el('span', { className: 'au-mono au-text-warning' }, 'fetch fail');
            } else if (p.hasUpdate && p.remoteSha) {
                available = el('span', { className: 'au-mono au-text-success' }, p.remoteSha);
            } else if (p.remoteSha === null) {
                available = el('span', { className: 'au-mono au-text-faint' }, 'not checked');
            } else {
                available = el('span', { className: 'au-mono au-text-faint' }, 'up to date');
            }

            var enableBox = el('input', { type: 'checkbox' });
            enableBox.checked = !!pluginCfg.enabled;
            enableBox.disabled = p.isDirty;
            enableBox.onchange = function () {
                if (!state.config.plugins[p.name]) state.config.plugins[p.name] = {};
                state.config.plugins[p.name].enabled = enableBox.checked;
                renderPluginCount();
            };

            var restartBox = el('input', { type: 'checkbox' });
            restartBox.checked = !!pluginCfg.restartAfterUpdate;
            restartBox.onchange = function () {
                if (!state.config.plugins[p.name]) state.config.plugins[p.name] = {};
                state.config.plugins[p.name].restartAfterUpdate = restartBox.checked;
            };

            var sourceText = p.isDirty
                ? 'local edits — skipped'
                : (p.srcURL || '').replace(/^https?:\/\/github\.com\//, '').replace(/\.git$/, '');

            var row = el('div', {
                className: 'au-plugin-row' + (p.isDirty ? ' au-plugin-dimmed' : ''),
            }, [
                enableBox,
                el('div', null, [
                    el('div', { className: 'au-plugin-name' }, p.name),
                    el('div', { className: 'au-plugin-source' }, sourceText || ' '),
                ]),
                el('span', { className: 'au-mono' }, current),
                available,
                el('span', { className: 'au-text-center' }, restartBox),
            ]);

            table.appendChild(row);
        });

        $('au-plugin-count').textContent = enabledCount + ' of ' + state.plugins.length + ' enabled';
    }

    function renderPluginCount() {
        var enabled = 0;
        state.plugins.forEach(function (p) {
            if (state.config.plugins[p.name] && state.config.plugins[p.name].enabled) enabled++;
        });
        $('au-plugin-count').textContent = enabled + ' of ' + state.plugins.length + ' enabled';
    }

    function renderHistory(events) {
        var container = $('au-history');
        container.innerHTML = '';

        if (!events || events.length === 0) {
            container.appendChild(el('div', { className: 'au-history-empty' },
                'No update activity yet'));
            return;
        }

        events.forEach(function (e) {
            var actionClass = 'au-text-faint';
            var actionText = e.action;
            if (e.action === 'applied') { actionClass = 'au-text-success'; }
            else if (e.action === 'available' || e.action === 'dry_run') { actionClass = 'au-text-success'; }
            else if (e.action === 'skipped' || e.action === 'error') { actionClass = 'au-text-warning'; }

            var detail = '';
            if (e.from && e.to) detail = e.plugin + ' ' + e.from + ' → ' + e.to;
            else if (e.reason) detail = e.plugin + ' — ' + e.reason;
            else if (e.checked !== undefined) detail = e.checked + ' plugins checked, ' + (e.applied || 0) + ' applied';
            else detail = e.plugin || '';

            container.appendChild(el('div', { className: 'au-history-row' }, [
                el('span', { className: 'au-history-ts' }, formatTs(e.ts)),
                el('span', { className: actionClass }, actionText),
                el('span', null, detail),
            ]));
        });
    }

    function formatTs(iso) {
        if (!iso) return '';
        try {
            var d = new Date(iso);
            var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
            return (d.getMonth() + 1) + '/' + d.getDate() + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
        } catch (e) {
            return iso;
        }
    }

    // --- Event wiring ---

    function gatherFormToConfig() {
        var modeRadio = document.querySelector('input[name="au-mode"]:checked');
        if (modeRadio) state.config.mode = modeRadio.value;
        state.config.dryRun = $('au-dryrun').checked;
        state.config.checkInterval = $('au-interval').value;
        state.config.updateWindow.type = $('au-window-type').value;
        state.config.updateWindow.earliestHour = parseInt($('au-earliest').value, 10) || 0;
        state.config.updateWindow.latestHour = parseInt($('au-latest').value, 10) || 0;
        state.config.scheduleBufferMinutes = parseInt($('au-buffer').value, 10) || 0;
        state.config.restartFppdAfterBatch = $('au-restart-fppd').checked;
    }

    function wireEvents() {
        document.querySelectorAll('input[name="au-mode"]').forEach(function (r) {
            r.addEventListener('change', function () {
                state.config.mode = r.value;
                renderMode();
            });
        });

        $('au-window-type').addEventListener('change', function () {
            state.config.updateWindow.type = $('au-window-type').value;
            renderSchedule();
        });

        $('au-save').addEventListener('click', function () {
            gatherFormToConfig();
            $('au-save').disabled = true;
            api('saveConfig', { body: state.config })
                .then(function (r) {
                    state.config = r.config;
                    toast('Settings saved');
                })
                .catch(function (e) { toast('Save failed: ' + e.message, true); })
                .then(function () { $('au-save').disabled = false; });
        });

        $('au-check-now').addEventListener('click', function () {
            $('au-check-now').disabled = true;
            $('au-check-now').textContent = 'Checking...';

            api('checkRemotes')
                .then(function (r) {
                    state.plugins = r.plugins;
                    renderPlugins();
                    return api('runNow');
                })
                .then(function () {
                    toast('Update run triggered');
                    return new Promise(function (resolve) { setTimeout(resolve, 2000); });
                })
                .then(function () { return api('getHistory', { method: 'GET' }); })
                .then(function (r) { renderHistory(r.history); })
                .catch(function (e) { toast('Check failed: ' + e.message, true); })
                .then(function () {
                    $('au-check-now').disabled = false;
                    $('au-check-now').textContent = 'Check now';
                });
        });

        $('au-history-refresh').addEventListener('click', function (e) {
            e.preventDefault();
            api('getHistory').then(function (r) { renderHistory(r.history); });
        });

        $('au-restart-fppd').addEventListener('change', function () {
            state.config.restartFppdAfterBatch = $('au-restart-fppd').checked;
        });
    }

    // --- Init ---

    api('getState')
        .then(function (r) {
            state.config = r.config;
            state.plugins = r.plugins;

            renderMode();
            renderSchedule();
            renderPlugins();
            renderHistory(r.history);
            $('au-restart-fppd').checked = !!state.config.restartFppdAfterBatch;
            wireEvents();
        })
        .catch(function (e) {
            $('au-plugin-table').innerHTML = '<div class="au-loading" style="color: #dc2626;">Failed to load: ' + e.message + '</div>';
        });
})();
</script>
