(() => {
  // assets/src/admin/index.js
  (function() {
    "use strict";
    const config = window.alyntDrimeWPvivid || {};
    const i18n = config.i18n || {};
    function text(key) {
      return i18n[key] || "";
    }
    function request(action, data) {
      const formData = new window.FormData();
      formData.append("action", action);
      formData.append("nonce", config.nonce || "");
      Object.keys(data || {}).forEach((key) => {
        formData.append(key, data[key]);
      });
      return window.fetch(config.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: formData
      }).then((response) => response.json()).then((payload) => {
        if (!payload || !payload.success) {
          throw new Error(payload && payload.data && payload.data.message ? payload.data.message : text("requestFailed"));
        }
        return payload.data;
      });
    }
    function setBusy(container, busy) {
      const buttons = container.querySelectorAll("button");
      const spinner = container.querySelector("[data-alynt-folder-spinner]");
      container.setAttribute("aria-busy", busy ? "true" : "false");
      buttons.forEach((button) => {
        if (busy && button.disabled) {
          button.setAttribute("data-alynt-was-disabled", "1");
        }
        if (!busy && button.getAttribute("data-alynt-was-disabled")) {
          button.removeAttribute("data-alynt-was-disabled");
          button.setAttribute("aria-disabled", "true");
          return;
        }
        button.disabled = busy;
        button.setAttribute("aria-disabled", busy ? "true" : "false");
      });
      if (spinner) {
        spinner.classList.toggle("is-active", busy);
      }
    }
    function setStatus(target, message) {
      if (target) {
        target.textContent = message;
      }
    }
    function renderFolders(container, folders) {
      const rows = container.querySelector("[data-alynt-folder-rows]");
      if (!rows) {
        return;
      }
      rows.textContent = "";
      if (!folders.length) {
        const emptyRow = document.createElement("tr"), emptyCell = document.createElement("td"), emptyTitle = document.createElement("strong"), emptyHint = document.createElement("span");
        emptyCell.colSpan = 3;
        emptyCell.className = "alynt-drime-folder-empty";
        emptyTitle.textContent = text("noFolders");
        emptyHint.textContent = text("noFoldersHint");
        emptyCell.appendChild(emptyTitle);
        emptyCell.appendChild(emptyHint);
        emptyRow.appendChild(emptyCell);
        rows.appendChild(emptyRow);
        return;
      }
      folders.forEach((folder) => {
        const row = document.createElement("tr"), name = document.createElement("td"), path = document.createElement("td"), actions = document.createElement("td"), open = document.createElement("button"), use = document.createElement("button");
        name.textContent = folder.name || "";
        path.textContent = folder.path || folder.name || "";
        open.type = "button";
        open.className = "button button-small";
        open.textContent = text("open");
        open.disabled = !folder.hash;
        open.setAttribute("aria-disabled", folder.hash ? "false" : "true");
        open.setAttribute("data-alynt-folder-open", folder.hash || "");
        use.type = "button";
        use.className = "button button-small";
        use.textContent = text("useBase");
        use.setAttribute("data-alynt-folder-use", "1");
        use.setAttribute("data-folder-id", folder.id || "");
        use.setAttribute("data-folder-hash", folder.hash || "");
        use.setAttribute("data-folder-path", folder.path || folder.name || "");
        actions.appendChild(open);
        actions.appendChild(document.createTextNode(" "));
        actions.appendChild(use);
        row.appendChild(name);
        row.appendChild(path);
        row.appendChild(actions);
        rows.appendChild(row);
      });
    }
    function loadFolders(container, folderHash) {
      const query = container.querySelector("[data-alynt-folder-search]");
      const panel = container.querySelector("[data-alynt-folder-panel]");
      const status = container.querySelector("[data-alynt-folder-status]");
      if (panel) {
        panel.hidden = false;
      }
      setBusy(container, true);
      setStatus(status, text("loading"));
      request("alynt_drime_backups_list_folders", {
        folder_hash: folderHash || "",
        query: query ? query.value : ""
      }).then((data) => {
        renderFolders(container, data.folders || []);
        setStatus(status, "");
      }).catch((error) => {
        setStatus(status, error.message || text("loadFailed"));
      }).finally(() => {
        setBusy(container, false);
      });
    }
    function useFolder(button) {
      const parentId = document.getElementById("alynt-parent-folder-id");
      const parentHash = document.getElementById("alynt-parent-folder-hash");
      const displayPath = document.getElementById("alynt-parent-folder-display-path");
      const selected = document.querySelector("[data-alynt-selected-folder]");
      const path = button.getAttribute("data-folder-path") || "";
      if (parentId) {
        parentId.value = button.getAttribute("data-folder-id") || "";
      }
      if (parentHash) {
        parentHash.value = button.getAttribute("data-folder-hash") || "";
      }
      if (displayPath) {
        displayPath.value = path;
      }
      if (selected) {
        selected.textContent = `${text("selectedPrefix")} ${path}`;
      }
    }
    function previewDestination(container) {
      const parentId = document.getElementById("alynt-parent-folder-id");
      const parentHash = document.getElementById("alynt-parent-folder-hash");
      const relativePath = document.getElementById("alynt-relative-path");
      const status = container.querySelector("[data-alynt-destination-status]");
      setBusy(container, true);
      setStatus(status, text("previewing"));
      request("alynt_drime_backups_preview_destination", {
        parent_folder_id: parentId ? parentId.value : "",
        parent_folder_hash: parentHash ? parentHash.value : "",
        relative_path: relativePath ? relativePath.value : ""
      }).then((data) => {
        const message = data.exists ? `${text("exists")} ${data.destination_path}` : `${text("missing")} ${(data.missing_segments || []).join("/")} (${data.destination_path})`;
        setStatus(status, message);
      }).catch((error) => {
        setStatus(status, error.message || text("previewFailed"));
      }).finally(() => {
        setBusy(container, false);
      });
    }
    document.addEventListener("submit", (event) => {
      const confirmMessage = event.target.querySelector("[data-alynt-confirm]");
      if (confirmMessage && !window.confirm(confirmMessage.getAttribute("data-alynt-confirm"))) {
        event.preventDefault();
        return;
      }
      const button = event.target.querySelector('button[type="submit"]');
      if (!button) {
        return;
      }
      const loadingLabel = button.getAttribute("data-alynt-loading-label");
      if (loadingLabel) {
        button.setAttribute("data-alynt-original-label", button.textContent);
        button.textContent = loadingLabel;
      }
      button.disabled = true;
      button.setAttribute("aria-disabled", "true");
      button.setAttribute("aria-busy", "true");
    }, true);
    document.addEventListener("click", (event) => {
      const container = event.target.closest("[data-alynt-folder-browser]");
      if (!container) {
        return;
      }
      if (event.target.matches("[data-alynt-folder-browser-open]")) {
        loadFolders(container, "");
      }
      if (event.target.matches("[data-alynt-folder-search-button]")) {
        loadFolders(container, "");
      }
      if (event.target.matches("[data-alynt-folder-open]")) {
        loadFolders(container, event.target.getAttribute("data-alynt-folder-open"));
      }
      if (event.target.matches("[data-alynt-folder-use]")) {
        useFolder(event.target);
      }
      if (event.target.matches("[data-alynt-destination-preview]")) {
        previewDestination(container);
      }
    });
  })();
})();
