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

    var config = window.bsptAdmin || {};
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

            // Broadcast tab change for lazily-initialized panels.
            try {
                document.dispatchEvent(new CustomEvent("bsa:tab-change", {
                    detail: { tab: tabName },
                }));
            } catch (err) {
                // CustomEvent constructor unavailable — acceptable, panels
                // can still init via hash fallback in their own modules.
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
        bsaAjax("bspt_get_status", {}, nonces.getStatus).then(function (res) {
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

        bsaAjax("bspt_get_logs", { level: state.logFilter }, nonces.getLogs).then(function (res) {
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

    function handleTestConnection(btn, apiKey) {
        if (!btn) return;
        btn.disabled = true;
        var originalText = btn.textContent;
        btn.textContent = strings.testing || "Connecting...";

        var payload = apiKey ? { api_key: apiKey } : {};
        bsaAjax("bspt_test_connection", payload, nonces.testConnection).then(function (res) {
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

    function handleReconnect(btn, apiKey) {
        if (!btn) return;
        btn.disabled = true;
        var originalText = btn.textContent;
        btn.textContent = strings.testing || "Working...";

        var payload = apiKey ? { api_key: apiKey } : {};
        bsaAjax("bspt_register_connection", payload, nonces.registerConnection).then(function (res) {
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

    function handleForceResync(btn) {
        if (!btn) return;
        btn.disabled = true;
        var originalText = btn.innerHTML;
        btn.querySelector("span").textContent = strings.testing || "Working...";

        bsaAjax("bspt_force_resync", {}, nonces.forceResync).then(function (res) {
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

        bsaAjax("bspt_clear_cache", {}, nonces.clearCache).then(function (res) {
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
        // Bind to ALL test-connection buttons (Connect tab + Developer tab)
        qsa('[data-bsa-action="test-connection"]').forEach(function (btn) {
            on(btn, "click", function (e) {
                // Save-then-connect: if the user typed a new key in the input,
                // pass it inline so the server saves it before registering. No
                // separate Save button needed.
                var clickedBtn = e.currentTarget;
                var input = qs("#bsa-api-key");
                var typedKey = input && input.value ? input.value.trim() : "";
                if (typedKey) {
                    handleReconnect(clickedBtn, typedKey);
                } else if (clickedBtn.getAttribute("data-bsa-is-connected") === "1") {
                    handleTestConnection(clickedBtn);
                } else {
                    // Developer tab button or not connected - just test connection
                    handleTestConnection(clickedBtn);
                }
            });
        });
        on(qs('[data-bsa-action="reconnect"]'), "click", function (e) {
            handleReconnect(e.currentTarget);
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
    // AJAX form save (bypasses options.php)
    // ------------------------------------------------------------
    function collectFormSettings() {
        var form = qs("#bsa-settings-form");
        if (!form) return {};

        var settings = {};

        // API key - only include if user entered a new value
        var apiKeyInput = form.querySelector('[name="bspt_api_key"]');
        if (apiKeyInput && apiKeyInput.value) {
            settings.api_key = apiKeyInput.value;
        }

        // Checkboxes - booleans
        var checkboxes = [
            { name: "bspt_auto_sync_enabled", key: "auto_sync_enabled" },
            { name: "bspt_appendix_enabled", key: "appendix_enabled" },
            { name: "bspt_jsonld_enabled", key: "jsonld_enabled" },
            { name: "bspt_debug_mode", key: "debug_mode" },
        ];
        checkboxes.forEach(function (cb) {
            var el = form.querySelector('[name="' + cb.name + '"]');
            if (el) settings[cb.key] = el.checked ? "1" : "";
        });

        // Selects (dropdowns)
        var selects = [
            { name: "bspt_sync_sensitivity", key: "sync_sensitivity" },
        ];
        selects.forEach(function (sel) {
            var el = form.querySelector('select[name="' + sel.name + '"]');
            if (el) settings[sel.key] = el.value;
        });

        // Radio buttons - must query for :checked
        var radios = [
            { name: "bspt_injection_position", key: "injection_position" },
            { name: "bspt_jsonld_conflict_mode", key: "jsonld_conflict_mode" },
        ];
        radios.forEach(function (radio) {
            var el = form.querySelector('[name="' + radio.name + '"]:checked');
            if (el) settings[radio.key] = el.value;
        });

        // Multi-selects / checkboxes for arrays
        var syncPostTypes = form.querySelectorAll('[name="bspt_sync_post_types[]"]:checked');
        settings.sync_post_types = Array.prototype.map.call(syncPostTypes, function (el) { return el.value; });

        var injectPostTypes = form.querySelectorAll('[name="bspt_inject_on_post_types[]"]:checked');
        settings.inject_on_post_types = Array.prototype.map.call(injectPostTypes, function (el) { return el.value; });

        // Numbers
        var cacheTtl = form.querySelector('[name="bspt_cache_ttl"]');
        if (cacheTtl) settings.cache_ttl = cacheTtl.value;

        return settings;
    }

    function handleAjaxSave(e) {
        e.preventDefault();

        var form = qs("#bsa-settings-form");
        var submitBtn = form ? form.querySelector('[type="submit"]') : null;
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = "Saving...";
        }

        var settings = collectFormSettings();
        var body = new URLSearchParams();
        body.append("action", "bspt_save_settings");
        body.append("nonce", nonces.saveSettings || "");

        // Serialize settings as individual params
        Object.keys(settings).forEach(function (k) {
            var v = settings[k];
            if (Array.isArray(v)) {
                v.forEach(function (item) {
                    body.append("settings[" + k + "][]", item);
                });
            } else {
                body.append("settings[" + k + "]", v);
            }
        });

        fetch(ajaxurl, {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
            body: body.toString(),
        })
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = "Save settings";
                }
                if (resp.success) {
                    markClean();
                    // Clear API key input after successful save (keep placeholder)
                    var apiKeyInput = qs('[name="bspt_api_key"]');
                    if (apiKeyInput && apiKeyInput.value) {
                        apiKeyInput.value = "";
                        apiKeyInput.setAttribute("data-has-value", "1");
                        apiKeyInput.placeholder = "••••••••••••••••••••••••";
                    }
                    fetchStatus();
                } else {
                    alert(resp.data && resp.data.message ? resp.data.message : "Save failed");
                }
            })
            .catch(function (err) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = "Save settings";
                }
                alert("Save failed: " + err.message);
            });
    }

    function initAjaxSave() {
        var form = qs("#bsa-settings-form");
        if (!form || !form.hasAttribute("data-bsa-ajax-save")) return;
        form.addEventListener("submit", handleAjaxSave);
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
        initAjaxSave();
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
