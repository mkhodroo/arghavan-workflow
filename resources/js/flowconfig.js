(function () {
    "use strict";

    function cfg() { return window.AW_CONFIG || {}; }
    function t(key, replacements) { return typeof window.awTranslate === "function" ? window.awTranslate(key, replacements) : key; }
    function el(id) { return document.getElementById(id); }
    function status(message, type) { if (typeof window.setStatus === "function") { window.setStatus(message, type); } }
    function setValue(id, value) { var node = el(id); if (node) { node.value = value === null || value === undefined ? "" : String(value); } }
    function getValue(id) { var node = el(id); return node ? node.value : ""; }
    function fillSelect(id, options, value, placeholder) {
        var select = el(id); if (!select) { return; }
        select.innerHTML = "";
        // Add all options first (without a placeholder at the start)
        (options || []).forEach(function (option) { 
            var item = document.createElement("option"); 
            item.value = option.id; 
            item.textContent = option.name || option.id; 
            select.appendChild(item); 
        });
        // If value is provided and not empty, select the matching option
        if (value !== null && value !== undefined && value !== "") {
            select.value = String(value);
        }
        // If value is empty/none, add placeholder option at the end
        if (value === null || value === undefined || value === "") {
            var first = document.createElement("option"); first.value = ""; first.textContent = placeholder || t("--- Select ---"); select.appendChild(first);
        }
    }

    var Modal = {
        docId: null, elementId: null, lists: { conditions: [], scripts: [], gateways: [] },
        open: function (docId, elementId) {
            if (!docId) { status(t("Save the diagram before saving its settings."), "warn"); return; }
            this.docId = docId; this.elementId = elementId;
            var title = el("aw-fl-title"); if (title) { title.textContent = t("Sequence Flow Configuration") + " — " + elementId; }
            var modal = el("aw-flowconfig-modal"); if (modal) { modal.hidden = false; }
            this.refreshLists().then(function () { return Modal.load(); });
        },
        close: function () { var modal = el("aw-flowconfig-modal"); if (modal) { modal.hidden = true; } },
        refreshLists: function () {
            var url = cfg().flowConfigListUrl; if (!url) { return Promise.resolve(); }
            return fetch(url, { headers: { Accept: "application/json" } }).then(function (response) { return response.json(); }).then(function (data) { Modal.lists.conditions = data.conditions || []; Modal.lists.scripts = data.scripts || []; });
        },
        load: function () {
            if (!this.docId) { this.apply({}); return; }
            // Construct URL directly without route_prefix to avoid mismatch
            var base = '/designer/flow-config/{doc}/{element}'
                .replace("{doc}", encodeURIComponent(this.docId))
                .replace("{element}", encodeURIComponent(this.elementId));
            fetch(base, { headers: { Accept: "application/json" } })
                .then(function (response) { return response.json(); })
                .then(function (data) { this.apply((data && data.config) || {}); }.bind(this))
                .catch(function () { this.apply({}); }.bind(this));
        },
        apply: function (config) { setValue("aw-fl-name", config.name); fillSelect("aw-fl-condition", this.lists.conditions, config.condition_id, t("--- Select Condition ---")); fillSelect("aw-fl-script", this.lists.scripts, config.script_id, t("--- Select Script ---")); },
        collect: function () { return { name: getValue("aw-fl-name"), condition_id: getValue("aw-fl-condition"), script_id: getValue("aw-fl-script") }; },
        save: function () {
            var base = cfg().flowConfigSaveUrl; if (!base || !this.docId) { status(t("Save the diagram before saving its settings."), "warn"); return; }
            var url = base.replace("{doc}", encodeURIComponent(this.docId)).replace("{element}", encodeURIComponent(this.elementId));
            var payload = this.collect(); var button = el("aw-fl-save"); if (button) { button.disabled = true; }
            fetch(url, { method: "POST", headers: { "Content-Type": "application/json", Accept: "application/json", "X-CSRF-TOKEN": cfg().csrf || "" }, body: JSON.stringify(payload) })
                .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data }; }); })
                .then(function (result) { if (!result.ok || !result.data.ok) { throw new Error(result.data.message || t("Saving failed.")); } status(t("Task settings saved."), "ok"); var api = window.ARGHAVAN_WORKFLOW; if (payload.name && api && typeof api.updateElementName === "function") { api.updateElementName(Modal.elementId, payload.name); } Modal.close(); })
                .catch(function (error) { status(error.message || t("Could not save the settings."), "error"); })
                .finally(function () { if (button) { button.disabled = false; } });
        }
    };

    function wire() {
        Array.prototype.forEach.call(document.querySelectorAll("[data-aw-fl-close]"), function (button) { button.addEventListener("click", function () { Modal.close(); }); });
        var saveButton = el("aw-fl-save"); if (saveButton) { saveButton.addEventListener("click", function () { Modal.save(); }); }
    }

    if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", wire); } else { wire(); }
    window.AW_FLOWCONFIG = Modal;
})();