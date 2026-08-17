(function () {
	'use strict';

	document.querySelectorAll('.cr-field--toggle .cr-switch input[type="checkbox"]').forEach(function (input) {
		input.addEventListener('change', function () {
			var field = input.closest('.cr-field');
			if (!field) return;
			field.classList.toggle('is-on', input.checked);
		});
	});

	var selectAll = document.getElementById('cr-db-select-all');
	if (selectAll) {
		selectAll.addEventListener('change', function () {
			document.querySelectorAll('input[name="cacherocket_db_actions[]"]').forEach(function (box) {
				box.checked = selectAll.checked;
			});
		});
	}

	var btn = document.getElementById('cr-run-pagespeed');
	var status = document.getElementById('cr-pagespeed-status');
	if (btn && status && typeof cacherocketAdmin !== 'undefined') {
		btn.addEventListener('click', function () {
			btn.disabled = true;
			status.textContent = cacherocketAdmin.i18n.queuing;
			status.classList.remove('is-error', 'is-ok');
			var body = new FormData();
			body.append('action', 'cacherocket_run_pagespeed');
			body.append('nonce', cacherocketAdmin.nonce);
			body.append('strategy', 'mobile');
			fetch(cacherocketAdmin.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
				.then(function (r) { return r.json(); })
				.then(function (json) {
					if (json && json.success) {
						status.textContent = cacherocketAdmin.i18n.queued;
						status.classList.add('is-ok');
					} else {
						status.textContent = (json && json.data && json.data.message) ? json.data.message : cacherocketAdmin.i18n.failed;
						status.classList.add('is-error');
					}
				})
				.catch(function () {
					status.textContent = cacherocketAdmin.i18n.requestFailed;
					status.classList.add('is-error');
				})
				.finally(function () { btn.disabled = false; });
		});
	}
})();
