(function () {
	'use strict';

	const config = window.alyntDrimeWPvivid || {};
	const i18n = config.i18n || {};

	function text(key, fallback) {
		return i18n[key] || fallback;
	}

	function request(action) {
		const formData = new window.FormData();
		formData.append('action', action);
		formData.append('nonce', config.nonce || '');

		return window.fetch(config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		}).then((response) => response.json()).then((payload) => {
			if (!payload || !payload.success) {
				throw new Error(payload && payload.data && payload.data.message ? payload.data.message : text('workspacesLoadFailed', 'Could not load Drime workspaces.'));
			}

			return payload.data || {};
		});
	}

	function setBusy(container, busy) {
		const button = container.querySelector('[data-alynt-workspaces-load]');
		const spinner = container.querySelector('[data-alynt-workspace-spinner]');

		container.setAttribute('aria-busy', busy ? 'true' : 'false');

		if (button) {
			button.disabled = busy;
			button.setAttribute('aria-disabled', busy ? 'true' : 'false');
		}

		if (spinner) {
			spinner.classList.toggle('is-active', busy);
		}
	}

	function setStatus(container, message) {
		const status = container.querySelector('[data-alynt-workspace-status]');
		if (status) {
			status.textContent = message || '';
		}
	}

	function optionLabel(workspace) {
		const details = [];

		if (workspace.role) {
			details.push(workspace.role);
		}

		if (workspace.members_count) {
			details.push(workspace.members_count === 1 ? text('workspaceMemberSingular', '1 member') : text('workspaceMembers', '%d members').replace('%d', workspace.members_count));
		}

		return details.length ? `${workspace.name} (${details.join(', ')})` : workspace.name;
	}

	function renderOptions(container, workspaces) {
		const select = container.querySelector('[data-alynt-workspace-select]');

		if (!select) {
			return;
		}

		select.textContent = '';
		select.appendChild(new window.Option(text('personalWorkspace', 'Personal/default workspace'), '0'));

		workspaces.forEach((workspace) => {
			select.appendChild(new window.Option(optionLabel(workspace), String(workspace.id)));
		});

		const input = document.getElementById('alynt-workspace-id');
		const currentValue = input ? input.value || '0' : '0';
		if (currentValue !== '0' && !workspaces.some((workspace) => String(workspace.id) === currentValue)) {
			select.appendChild(new window.Option(`${text('workspaceIdPrefix', 'Workspace ID')} ${currentValue}`, currentValue));
		}

		select.hidden = false;
		select.value = currentValue;
	}

	function clearSelectedFolder() {
		const parentId = document.getElementById('alynt-parent-folder-id');
		const parentHash = document.getElementById('alynt-parent-folder-hash');
		const displayPath = document.getElementById('alynt-parent-folder-display-path');
		const selected = document.querySelector('[data-alynt-selected-folder]');

		if (parentId) {
			parentId.value = '';
		}

		if (parentHash) {
			parentHash.value = '';
		}

		if (displayPath) {
			displayPath.value = '';
		}

		if (selected) {
			selected.textContent = text('selectedRootFolder', 'Selected base folder: Drime root or manually entered folder ID.');
		}
	}

	function loadWorkspaces(container) {
		setBusy(container, true);
		setStatus(container, text('loading', 'Loading...'));

		request('alynt_drime_backups_list_workspaces').then((data) => {
			const workspaces = data.workspaces || [];
			renderOptions(container, workspaces);
			setStatus(container, workspaces.length ? text('workspacesLoaded', 'Workspaces loaded. Choose one, then save settings.') : text('noWorkspaces', 'No team workspaces found. The personal/default workspace remains available.'));
		}).catch((error) => {
			setStatus(container, error.message || text('workspacesLoadFailed', 'Could not load Drime workspaces.'));
		}).finally(() => {
			setBusy(container, false);
		});
	}

	document.addEventListener('click', (event) => {
		const container = event.target.closest('[data-alynt-workspace-browser]');
		if (container && event.target.matches('[data-alynt-workspaces-load]')) {
			loadWorkspaces(container);
		}
	});

	document.addEventListener('change', (event) => {
		const select = event.target.closest('[data-alynt-workspace-select]');
		const input = document.getElementById('alynt-workspace-id');

		if (!select || !input) {
			return;
		}

		input.value = select.value || '0';
		clearSelectedFolder();
		setStatus(select.closest('[data-alynt-workspace-browser]'), `${text('workspaceSelectedPrefix', 'Selected workspace:')} ${select.options[select.selectedIndex].text}`);
	});
}());
