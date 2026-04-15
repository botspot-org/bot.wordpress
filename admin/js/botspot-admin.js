/*!
 * BotSpot WordPress Admin UI
 * Vanilla JS, no dependencies.
 *
 * @since 2.2.0
 */
(function () {
    "use strict";

    var root = document.querySelector(".botspot-admin-root");
    if (!root) {
        return;
    }

    var config = window.botspotAdmin || {};
    var nonces = config.nonces || {};
    var strings = config.strings || {};

    // Local state
    var state = {
        logs: [],
        logFilter: "all",
        formDirty: false,
        statusFetched: false,
        logsFetched: false,
    };

    // ------------------------------------------------------------
    // Tiny DOM helpers
    // ------------------------------------------------------------
    function qs(sel, ctx) { return (ctx || root).querySelector(sel); }
    function qsa(sel, ctx) { return Array.prototype.slice.call((ctx || root).querySelectorAll(sel)); }
    function on(el, ev, fn) { if (el) el.addEventListener(ev, fn); }
    function escapeHtml(str) {
        return String(str || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // ------------------------------------------------------------
    // AJAX helper (returns a Promise of parsed JSON)
    // ------------------------------------------------------------
    function bsaAjax(action, data, nonce) {
        var formData = new FormData();
        formData.append("action", action);
        if (nonce) {
            formData.append("nonce", nonce);
            formData.append("_wpnonce", nonce);
        }
        if (data) {
            Object.keys(data).forEach(function (k) {
                var val = data[k];
                if (Array.isArray(val)) {
                    val.forEach(function (v) { formData.append(k + "[]", v); });
                } else {
                    formData.append(k, val);
                }
            });
        }
        return fetch(config.ajaxurl, {
            method: "POST",
            credentials: "same-origin",
            body: formData,
        }).then(function (r) {
            return r.json();
        }).catch(function (err) {
            return { success: false, data: { message: err && err.message ? err.message : "Network error" } };
        });
    }

    // ------------------------------------------------------------
    // Tab switcher with hash persistence
    // ------------------------------------------------------------
    function initTabs() {
        var tabs = qsa("[data-bsa-tab]");
        var panels = qsa("[data-bsa-panel]");

        function activate(tabName) {
            tabs.forEach(function (btn) {
                var active = btn.dataset.bsaTab === tabName;
                btn.classList.toggle("bsa-tab--active", active);
                btn.setAttribute("aria-selected", active ? "true" : "false");
            });
            panels.forEach(function (panel) {
                panel.classList.toggle("bsa-hidden", panel.dataset.bsaPanel !== tabName);
            });
            root.dataset.tab = tabName;
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, "", "#" + tabName);
            }

            // Lazy fetch logs when Developer tab is first opened
            if (tabName === "developer" && !state.logsFetched) {
                fetchLogs();
            }

            // Broadcast tab change for lazily-initialized panels (e.g. Analytics).
            try {
                document.dispatchEvent(new CustomEvent("bsa:tab-change", {
                    detail: { tab: tabName },
                }));
            } catch (err) {
                // CustomEvent constructor unavailable — acceptable, analytics will
                // still init via hash fallback in its own module.
            }
        }

        tabs.forEach(function (btn) {
            on(btn, "click", function (e) {
                e.preventDefault();
                activate(btn.dataset.bsaTab);
            });
        });

        var initial = (window.location.hash || "").replace("#", "") || "connect";
        var validTabs = tabs.map(function (t) { return t.dataset.bsaTab; });
        if (validTabs.indexOf(initial) === -1) {
            initial = validTabs[0] || "connect";
        }
        activate(initial);
    }

    // ------------------------------------------------------------
    // API key show/hide toggle + live enable of connection buttons
    // ------------------------------------------------------------
    function initApiKeyToggle() {
        var input = qs("#bsa-api-key");
        var btn = qs('[data-bsa-action="toggle-key-visibility"]');

        // Enable Connect / Test Connection buttons the moment a non-empty
        // value is typed or pasted into the access key input — don't wait
        // until settings are saved. The user may still need to save the
        // form before an AJAX Connect actually succeeds, but the button
        // should at least not appear disabled while there's a value.
        function syncActionButtonsDisabled() {
            var hasInputValue = !!(input && input.value && input.value.trim().length > 0);
            var hadExistingKey = input && input.getAttribute("data-has-value") === "1";
            var shouldEnable = hasInputValue || hadExistingKey;
            qsa('[data-bsa-requires-key="1"]').forEach(function (el) {
                el.disabled = !shouldEnable;
            });
        }

        if (input) {
            ["input", "change", "paste", "keyup"].forEach(function (ev) {
                on(input, ev, function () {
                    // For paste, value isn't populated until next tick.
                    setTimeout(syncActionButtonsDisabled, 0);
                });
            });
            // Run once at init so the initial state is correct.
            syncActionButtonsDisabled();
        }

        if (!input || !btn) return;

        on(btn, "click", function () {
            var showing = input.type === "text";
            input.type = showing ? "password" : "text";
            btn.textContent = showing ? (strings.show || "Show") : (strings.hide || "Hide");
        });
    }

    // ------------------------------------------------------------
    // Status probe — fills the three header dots
    // ------------------------------------------------------------
    function paintStatusPill(kind, payload) {
        var pill = qs('[data-bsa-status="' + kind + '"]');
        if (!pill) return;
        var dot = qs(".bsa-dot", pill);
        if (!dot) return;

        var status = (payload && payload.status) || "warn";
        dot.classList.remove("bsa-dot--pending", "bsa-dot--ok", "bsa-dot--warn", "bsa-dot--error");
        dot.classList.add("bsa-dot--" + status);

        if (kind === "connection" && status === "ok") {
            dot.classList.add("bsa-dot--pulse");
        }

        var title = (payload && payload.label) || "";
        if (payload && payload.detail) {
            title += title ? " — " + payload.detail : payload.detail;
        }
        pill.title = title || kind;
    }

    function fetchStatus() {
        bsaAjax("botdot_wp_get_status", {}, nonces.getStatus).then(function (res) {
            state.statusFetched = true;
            if (!res || !res.success) {
                ["connection", "sync", "runtime"].forEach(function (k) {
                    paintStatusPill(k, { status: "error", label: "Status unavailable" });
                });
                return;
            }
            var data = res.data || {};
            paintStatusPill("connection", data.connection || {});
            paintStatusPill("sync", data.sync || {});
            paintStatusPill("runtime", data.runtime || {});
        });
    }

    // ------------------------------------------------------------
    // Log viewer
    // ------------------------------------------------------------
    function renderLogs() {
        var list = qs("[data-bsa-log-list]");
        var countEl = qs("[data-bsa-log-count]");
        if (!list) return;

        var filter = state.logFilter;
        var filtered = state.logs.filter(function (e) {
            if (filter === "all") return true;
            if (filter === "warning") return e.level === "warn";
            return e.level === filter;
        });

        if (countEl) {
            countEl.textContent = filtered.length + (filtered.length === 1 ? " entry" : " entries");
        }

        if (filtered.length === 0) {
            list.innerHTML = '<div class="bsa-log-empty">' + escapeHtml(strings.noLogs || "No log entries.") + "</div>";
            return;
        }

        var html = filtered.map(function (e) {
            return (
                '<div class="bsa-log-entry">' +
                    '<span class="bsa-log-entry__time bsa-tabular-nums">' + escapeHtml(e.time_display) + "</span>" +
                    '<span class="bsa-log-entry__level bsa-log-entry__level--' + escapeHtml(e.level) + '">' + escapeHtml(e.level) + "</span>" +
                    '<span class="bsa-log-entry__source">' + escapeHtml(e.source) + "</span>" +
                    '<span class="bsa-log-entry__message">' + escapeHtml(e.message) + "</span>" +
                "</div>"
            );
        }).join("");
        list.innerHTML = html;
    }

    function fetchLogs() {
        var list = qs("[data-bsa-log-list]");
        if (list) {
            list.innerHTML = '<div class="bsa-log-empty">' + escapeHtml(strings.loadingLogs || "Loading logs...") + "</div>";
        }

        bsaAjax("botdot_wp_get_logs", { level: state.logFilter }, nonces.getLogs).then(function (res) {
            state.logsFetched = true;
            if (res && res.success && res.data && Array.isArray(res.data.entries)) {
                state.logs = res.data.entries;
            } else {
                state.logs = [];
            }
            renderLogs();
        });
    }

    function initLogViewer() {
        var filterEl = qs("[data-bsa-log-filter]");
        on(filterEl, "change", function () {
            state.logFilter = filterEl.value;
            renderLogs();
        });

        on(qs('[data-bsa-action="refresh-logs"]'), "click", function () {
            fetchLogs();
        });

        on(qs('[data-bsa-action="download-logs"]'), "click", function () {
            var lines = state.logs.map(function (e) {
                return [e.timestamp, e.level.toUpperCase(), e.source, e.message].join(" | ");
            });
            var blob = new Blob([lines.join("\n")], { type: "text/plain;charset=utf-8" });
            var url = URL.createObjectURL(blob);
            var a = document.createElement("a");
            a.href = url;
            a.download = "botspot-wp-logs-" + new Date().toISOString().replace(/[:.]/g, "-") + ".txt";
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });
    }

    // ------------------------------------------------------------
    // Actions (Connect tab + Developer sidebar)
    // ------------------------------------------------------------
    function showResult(targetKey, success, message) {
        var el = qs('[data-bsa-result="' + targetKey + '"]');
        if (!el) {
            // No result container — fall back to a temporary toast near the clicked button
            return;
        }
        el.classList.remove("bsa-hidden", "bsa-result--ok", "bsa-result--error");
        el.classList.add(success ? "bsa-result--ok" : "bsa-result--error");
        el.textContent = message || "";
    }

    // Map backend error strings to user-friendly copy. Any message that
    // clearly indicates a bad/invalid API key should surface the guidance
    // to re-copy from the dashboard rather than a raw HTTP error.
    function friendlyConnectionError(rawMsg) {
        var msg = String(rawMsg || "").toLowerCase();
        var looksLikeBadKey = (
            msg.indexOf("invalid api key") !== -1 ||
            msg.indexOf("invalid access key") !== -1 ||
            msg.indexOf("webhook registration failed") !== -1 ||
            msg.indexOf("401") !== -1 ||
            msg.indexOf("403") !== -1 ||
            msg.indexOf("unauthorized") !== -1 ||
            msg.indexOf("forbidden") !== -1
        );
        if (looksLikeBadKey) {
            return "Connection failed: Invalid Access Key. Please ensure you copied the entire key from the bot.spot dashboard.";
        }
        return rawMsg;
    }

    function handleTestConnection(btn) {
        if (!btn) return;
        btn.disabled = true;
        var originalText = btn.textContent;
        btn.textContent = strings.testing || "Connecting...";

        bsaAjax("botdot_wp_test_connection", {}, nonces.testConnection).then(function (res) {
            btn.disabled = false;
            btn.textContent = originalText;

            if (res && res.success) {
                showResult("test-connection", true, (res.data && res.data.message) || strings.testSuccess || "Connected");
                // Refresh the status dots — connection should flip to OK
                fetchStatus();
            } else {
                var msg = (res && res.data && res.data.message) || strings.testFailed || "Failed";
                showResult("test-connection", false, friendlyConnectionError(msg));
            }
        });
    }

    function handleReconnect(btn) {
        if (!btn) return;
        btn.disabled = true;
        var originalText = btn.textContent;
        btn.textContent = strings.testing || "Working...";

        bsaAjax("botdot_wp_register_connection", {}, nonces.registerConnection).then(function (res) {
            btn.disabled = false;
            btn.textContent = originalText;
            if (res && res.success) {
                showResult("test-connection", true, (res.data && res.data.message) || "Connected");
                setTimeout(function () { window.location.reload(); }, 600);
            } else {
                showResult("test-connection", false, friendlyConnectionError((res && res.data && res.data.message) || "Connection failed"));
            }
        });
    }

    function handleDisconnect(btn) {
        if (!btn) return;
        if (!window.confirm(strings.confirmDisconnect || "Disconnect?")) return;

        btn.disabled = true;
        bsaAjax("botdot_wp_disconnect", {}, nonces.disconnect).then(function (res) {
            btn.disabled = false;
            if (res && res.success) {
                window.location.reload();
            } else {
                showResult("test-connection", false, (res && res.data && res.data.message) || "Disconnect failed");
            }
        });
    }

    function handleForceResync(btn) {
        if (!btn) return;
        btn.disabled = true;
        var originalText = btn.innerHTML;
        btn.querySelector("span").textContent = strings.testing || "Working...";

        bsaAjax("botdot_wp_force_resync", {}, nonces.forceResync).then(function (res) {
            btn.disabled = false;
            btn.innerHTML = originalText;
            var msg = (res && res.data && res.data.message) || (res && res.success ? "Done" : "Failed");
            alert(msg);
            fetchStatus();
            fetchLogs();
        });
    }

    function handleClearCache(btn) {
        if (!btn) return;
        btn.disabled = true;
        var originalText = btn.innerHTML;
        btn.querySelector("span").textContent = strings.testing || "Working...";

        bsaAjax("botdot_wp_clear_cache", {}, nonces.clearCache).then(function (res) {
            btn.disabled = false;
            btn.innerHTML = originalText;
            var msg = (res && res.data && res.data.message) || (res && res.success ? "Cache cleared" : "Failed");
            alert(msg);
            fetchStatus();
        });
    }

    function handleCopyDiagnostics(btn) {
        if (!btn) return;
        var payload = [
            "# bot.spot WP Diagnostics",
            "Generated: " + new Date().toISOString(),
            "",
            "## Environment",
            "- plugin: " + (config.pluginVersion || "?"),
            "- wordpress: " + (config.wpVersion || "?"),
            "- php: " + (config.phpVersion || "?"),
            "- api: " + (config.apiVersion || "?"),
            "- domain: " + (config.siteDomain || "?"),
            "",
            "## Recent log entries (" + state.logs.length + ")",
        ];
        state.logs.slice(0, 50).forEach(function (e) {
            payload.push(
                "- [" + e.level.toUpperCase() + "] " +
                e.time_display + " " + e.source + ": " + e.message
            );
        });

        var text = payload.join("\n");
        function done() {
            var originalText = btn.innerHTML;
            btn.querySelector("span").textContent = strings.copied || "Copied";
            setTimeout(function () { btn.innerHTML = originalText; }, 1500);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(function () {
                // Fallback: show in prompt so user can copy manually
                window.prompt("Copy diagnostics:", text);
            });
        } else {
            window.prompt("Copy diagnostics:", text);
        }
    }

    function initActions() {
        on(qs('[data-bsa-action="test-connection"]'), "click", function (e) {
            handleTestConnection(e.currentTarget);
        });
        on(qs('[data-bsa-action="reconnect"]'), "click", function (e) {
            handleReconnect(e.currentTarget);
        });
        on(qs('[data-bsa-action="disconnect"]'), "click", function (e) {
            handleDisconnect(e.currentTarget);
        });
        on(qs('[data-bsa-action="force-resync"]'), "click", function (e) {
            handleForceResync(e.currentTarget);
        });
        on(qs('[data-bsa-action="clear-cache"]'), "click", function (e) {
            handleClearCache(e.currentTarget);
        });
        on(qs('[data-bsa-action="copy-diagnostics"]'), "click", function (e) {
            handleCopyDiagnostics(e.currentTarget);
        });
        on(qs('[data-bsa-action="reset-form"]'), "click", function () {
            var form = qs("#bsa-settings-form");
            if (form && form.reset) form.reset();
            markClean();
        });
    }

    // ------------------------------------------------------------
    // Save state indicator
    // ------------------------------------------------------------
    function markDirty() {
        if (state.formDirty) return;
        state.formDirty = true;
        var el = qs("[data-bsa-save-status]");
        if (el) el.textContent = strings.unsaved || "Unsaved changes";
    }
    function markClean() {
        state.formDirty = false;
        var el = qs("[data-bsa-save-status]");
        if (el) el.textContent = strings.allSaved || "All changes saved";
    }
    function initSaveStatus() {
        var form = qs("#bsa-settings-form");
        if (!form) return;
        ["input", "change"].forEach(function (ev) {
            form.addEventListener(ev, markDirty);
        });
    }

    // ------------------------------------------------------------
    // Init
    // ------------------------------------------------------------
    function init() {
        initTabs();
        initApiKeyToggle();
        initActions();
        initLogViewer();
        initSaveStatus();
        fetchStatus();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }

    // Expose for debugging
    window.bsaAjax = bsaAjax;
})();

/* =============================================================================
   Analytics tab
   ============================================================================= */

(function (window, document) {
    'use strict';

    var AJAX = (window.botspotAdmin && window.botspotAdmin.ajaxurl) || '/wp-admin/admin-ajax.php';
    var NONCES = (window.botspotAdmin && window.botspotAdmin.nonces) || {};
    var currentWindow = '7d';
    var initialized = false;

    function postAjax(action, nonceKey, data) {
        var body = new URLSearchParams();
        body.append('action', action);
        body.append('nonce', NONCES[nonceKey] || '');
        Object.keys(data || {}).forEach(function (k) {
            body.append(k, data[k]);
        });
        return fetch(AJAX, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body: body.toString(),
        }).then(function (r) { return r.json(); });
    }

    function renderSync(data) {
        var counts = (data && data.counts) || {};
        var el = document.querySelector('[data-bsa-analytics="sync"] .bsa-analytics-card__body');
        if (!el) return;
        el.innerHTML =
            '<ul class="bsa-analytics__list">' +
                '<li>Synced: <strong>' + (counts.synced || 0) + '</strong></li>' +
                '<li>Pending: <strong>' + (counts.pending || 0) + '</strong></li>' +
                '<li>Errors: <strong>' + (counts.error || 0) + '</strong></li>' +
                '<li>Never synced: <strong>' + (counts.never || 0) + '</strong></li>' +
            '</ul>';
    }

    function renderEnrichment(data) {
        var counts = (data && data.counts) || {};
        var el = document.querySelector('[data-bsa-analytics="enrichment"] .bsa-analytics-card__body');
        if (!el) return;
        el.innerHTML =
            '<ul class="bsa-analytics__list">' +
                '<li>NONE: <strong>' + (counts.NONE || 0) + '</strong></li>' +
                '<li>TIER0: <strong>' + (counts.TIER0 || 0) + '</strong></li>' +
                '<li>TIER1: <strong>' + (counts.TIER1 || 0) + '</strong></li>' +
                '<li>TIER2: <strong>' + (counts.TIER2 || 0) + '</strong></li>' +
                '<li>FULL: <strong>' + (counts.FULL || 0) + '</strong></li>' +
            '</ul>';
    }

    function renderImpressions(data) {
        var el = document.querySelector('[data-bsa-analytics="impressions"] .bsa-analytics-card__body');
        if (!el) return;
        if (!data) {
            el.innerHTML = '<p class="bsa-muted">No data yet.</p>';
            return;
        }
        var totals = data.totals || { all: 0, by_bot_class: {} };
        var rows = (data.by_artifact || []).map(function (a) {
            var title = a.title || '(unknown)';
            var link = a.permalink ? ('<a href="' + a.permalink + '" target="_blank" rel="noopener">' + title + '</a>') : title;
            return '<tr><td>' + link + '</td><td>' + (a.total || 0) + '</td></tr>';
        }).join('');
        var classRows = Object.keys(totals.by_bot_class || {}).map(function (cls) {
            return '<tr><td>' + cls + '</td><td>' + totals.by_bot_class[cls] + '</td></tr>';
        }).join('');
        el.innerHTML =
            '<p><strong>' + (totals.all || 0) + '</strong> total impressions (' + currentWindow + ')</p>' +
            '<table class="bsa-analytics__table"><thead><tr><th>Bot class</th><th>Count</th></tr></thead><tbody>' + classRows + '</tbody></table>' +
            '<h4>Top content</h4>' +
            '<table class="bsa-analytics__table"><thead><tr><th>Title</th><th>Hits</th></tr></thead><tbody>' + rows + '</tbody></table>';
    }

    function loadSyncHealth() {
        return postAjax('botdot_wp_get_sync_health', 'getSyncHealth', {})
            .then(function (resp) { if (resp.success) renderSync(resp.data); });
    }

    function loadEnrichmentLifecycle() {
        return postAjax('botdot_wp_get_enrichment_lifecycle', 'getEnrichmentLifecycle', {})
            .then(function (resp) { if (resp.success) renderEnrichment(resp.data); });
    }

    function loadImpressions() {
        return postAjax('botdot_wp_get_impressions', 'getImpressions', { window: currentWindow })
            .then(function (resp) { if (resp.success) renderImpressions(resp.data); });
    }

    function loadAll() {
        loadSyncHealth();
        loadEnrichmentLifecycle();
        loadImpressions();
    }

    function initAnalyticsPanel() {
        if (initialized) return;
        initialized = true;

        // Window selector
        var selector = document.querySelector('.bsa-analytics__window-selector');
        if (selector) {
            selector.addEventListener('click', function (evt) {
                var target = evt.target.closest('button[data-window]');
                if (!target) return;
                currentWindow = target.getAttribute('data-window');
                [].slice.call(selector.querySelectorAll('button')).forEach(function (b) {
                    b.classList.remove('is-active');
                });
                target.classList.add('is-active');
                loadImpressions();
            });
        }

        // Flush now button
        var flushBtn = document.querySelector('[data-bsa-action="force-flush"]');
        if (flushBtn) {
            flushBtn.addEventListener('click', function () {
                flushBtn.disabled = true;
                postAjax('botdot_wp_force_flush', 'forceFlush', {}).then(function () {
                    flushBtn.disabled = false;
                    loadImpressions();
                });
            });
        }
    }

    // Hook into the existing tab-change dispatcher. Existing tab code emits a
    // 'bsa:tab-change' event whenever a panel is activated; we load on first
    // activation of the analytics panel.
    document.addEventListener('bsa:tab-change', function (evt) {
        // Analytics now lives inside the Developer tab — load on either
        // tab activation. Legacy 'analytics' tab name kept as a no-op
        // safety in case an old link lands here.
        if (evt && evt.detail && (evt.detail.tab === 'analytics' || evt.detail.tab === 'developer')) {
            initAnalyticsPanel();
            loadAll();
        }
    });

    // Also support direct URL hash navigation (#developer or legacy #analytics).
    if (window.location.hash === '#analytics' || window.location.hash === '#developer') {
        document.addEventListener('DOMContentLoaded', function () {
            initAnalyticsPanel();
            loadAll();
        });
    }

})(window, document);
