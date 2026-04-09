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
    // API key show/hide toggle
    // ------------------------------------------------------------
    function initApiKeyToggle() {
        var input = qs("#bsa-api-key");
        var btn = qs('[data-bsa-action="toggle-key-visibility"]');
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

    function handleTestConnection(btn) {
        if (!btn) return;
        btn.disabled = true;
        var originalText = btn.textContent;
        btn.textContent = strings.testing || "Testing...";

        bsaAjax("botdot_wp_test_connection", {}, nonces.testConnection).then(function (res) {
            btn.disabled = false;
            btn.textContent = originalText;

            if (res && res.success) {
                showResult("test-connection", true, (res.data && res.data.message) || strings.testSuccess || "Success");
                // Refresh the status dots — connection should flip to OK
                fetchStatus();
            } else {
                var msg = (res && res.data && res.data.message) || strings.testFailed || "Failed";
                showResult("test-connection", false, msg);
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
                showResult("test-connection", true, (res.data && res.data.message) || "Reconnected");
                setTimeout(function () { window.location.reload(); }, 600);
            } else {
                showResult("test-connection", false, (res && res.data && res.data.message) || "Reconnect failed");
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
            "# BotSpot WP Diagnostics",
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
