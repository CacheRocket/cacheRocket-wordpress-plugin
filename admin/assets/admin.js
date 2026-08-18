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

	var warmJob = document.getElementById('cr-warm-job');
	if (warmJob && warmJob.dataset.done !== '1' && typeof cacherocketWarmJob !== 'undefined') {
		var headline = warmJob.querySelector('[data-cr-warm-job-headline]');
		var detail = warmJob.querySelector('[data-cr-warm-job-detail]');
		var timer = null;

		var format = function (template, values) {
			var i = 0;
			return template.replace(/%(\d+)\$d/g, function (match, position) {
				return values[parseInt(position, 10) - 1];
			}).replace(/%d/g, function () {
				return values[i++];
			});
		};

		var render = function (job) {
			if (job.done) {
				headline.textContent = format(cacherocketWarmJob.i18n.finished, [job.warmed, job.failed, job.skipped]);
				warmJob.classList.add('cr-notice--ok');
			} else if (job.status === 'queued') {
				headline.textContent = cacherocketWarmJob.i18n.queued;
			} else {
				headline.textContent = format(cacherocketWarmJob.i18n.progress, [job.processedUrls, job.totalUrls]);
			}
			detail.textContent = job.errorMessage || job.quotaMessage || '';
		};

		var poll = function () {
			var body = new FormData();
			body.append('action', 'cacherocket_warm_job_status');
			body.append('nonce', cacherocketWarmJob.nonce);
			fetch(cacherocketWarmJob.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
				.then(function (r) { return r.json(); })
				.then(function (json) {
					if (!json || !json.success || !json.data || !json.data.job) {
						clearInterval(timer);
						return;
					}
					render(json.data.job);
					if (json.data.job.done) {
						clearInterval(timer);
					}
				})
				.catch(function () {
					detail.textContent = cacherocketWarmJob.i18n.requestFailed;
					clearInterval(timer);
				});
		};

		timer = setInterval(poll, cacherocketWarmJob.intervalMs);
		poll();
	}

	if (typeof cacherocketAdmin !== 'undefined') {
		var crFmt = function (template, values) {
			var i = 0;
			return String(template).replace(/%(\d+)\$d/g, function (match, position) {
				return values[parseInt(position, 10) - 1];
			}).replace(/%d/g, function () {
				return values[i++];
			});
		};

		var crPost = function (action, extra) {
			var body = new FormData();
			body.append('action', action);
			body.append('nonce', cacherocketAdmin.nonce);
			if (extra) {
				Object.keys(extra).forEach(function (key) { body.append(key, extra[key]); });
			}
			return fetch(cacherocketAdmin.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
				.then(function (r) { return r.json(); });
		};

		// Media Library column: per-attachment Optimize / Restore / Exclude / Include.
		document.addEventListener('click', function (event) {
			var target = event.target;
			if (!target || !target.classList) return;
			var isOptimize = target.classList.contains('cr-media-optimize');
			var isRestore = target.classList.contains('cr-media-restore');
			var isExclude = target.classList.contains('cr-media-exclude');
			var isInclude = target.classList.contains('cr-media-include');
			if (!isOptimize && !isRestore && !isExclude && !isInclude) return;

			var cell = target.closest('.cr-media-cell');
			if (!cell) return;
			var attachmentId = cell.getAttribute('data-attachment');
			var msg = cell.querySelector('.cr-media-msg');
			if (!attachmentId) return;

			target.disabled = true;
			if (msg) {
				msg.textContent = isRestore
					? cacherocketAdmin.i18n.restoring
					: (isExclude || isInclude ? cacherocketAdmin.i18n.saving : cacherocketAdmin.i18n.queuing);
				msg.classList.remove('is-error', 'is-ok');
			}

			var action = 'cacherocket_optimize_attachment';
			var extra = { attachmentId: attachmentId };
			if (isRestore) {
				action = 'cacherocket_restore_attachment';
			} else if (isExclude || isInclude) {
				action = 'cacherocket_ignore_attachment';
				extra.ignore = isExclude ? '1' : '0';
			}

			crPost(action, extra)
				.then(function (json) {
					if (json && json.success) {
						if (msg) {
							msg.textContent = (json.data && json.data.message) ? json.data.message : cacherocketAdmin.i18n.queued;
							msg.classList.add('is-ok');
						}
						if (isExclude || isInclude || isRestore || isOptimize) {
							window.setTimeout(function () { window.location.reload(); }, 600);
						}
					} else if (msg) {
						msg.textContent = (json && json.data && json.data.message) ? json.data.message : cacherocketAdmin.i18n.failed;
						msg.classList.add('is-error');
					}
				})
				.catch(function () {
					if (msg) {
						msg.textContent = cacherocketAdmin.i18n.requestFailed;
						msg.classList.add('is-error');
					}
				})
				.finally(function () { target.disabled = false; });
		});

		// Bulk optimize with progress polling.
		var bulkWrap = document.getElementById('cr-bulk-optimize');
		if (bulkWrap) {
			var bulkStart = document.getElementById('cr-bulk-start');
			var bulkStop = document.getElementById('cr-bulk-stop');
			var bulkFill = bulkWrap.querySelector('.cr-bulk__fill');
			var bulkStatus = bulkWrap.querySelector('.cr-bulk__status');
			var bulkRunning = false;
			var bulkTimer = null;

			var bulkRender = function (stats) {
				if (!stats) return;
				var total = stats.total || 0;
				var done = stats.optimized || 0;
				var pct = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;
				if (bulkFill) bulkFill.style.width = pct + '%';
				if (bulkStatus) bulkStatus.textContent = crFmt(cacherocketAdmin.i18n.bulkProgress, [done, total]);
				return total > 0 && done >= total;
			};

			var bulkStopRun = function (finished) {
				bulkRunning = false;
				if (bulkTimer) { clearTimeout(bulkTimer); bulkTimer = null; }
				if (bulkStart) { bulkStart.disabled = false; bulkStart.hidden = false; }
				if (bulkStop) bulkStop.hidden = true;
				if (finished && bulkStatus) bulkStatus.textContent = cacherocketAdmin.i18n.bulkDone;
			};

			var bulkTick = function () {
				if (!bulkRunning) return;
				crPost('cacherocket_bulk_status')
					.then(function (json) {
						if (!json || !json.success || !json.data) { bulkStopRun(false); return; }
						var stats = json.data.stats || {};
						if (bulkRender(stats)) { bulkStopRun(true); return; }
						var pending = stats.pending || 0;
						var next = function () { if (bulkRunning) { bulkTimer = setTimeout(bulkTick, 4000); } };
						if (pending < 5) {
							crPost('cacherocket_bulk_optimize', { batch: 10 })
								.then(function (queued) {
									if (queued && queued.success && queued.data && queued.data.stats) {
										if (bulkRender(queued.data.stats)) { bulkStopRun(true); return; }
									} else if (queued && !queued.success) {
										if (bulkStatus) {
											bulkStatus.textContent = (queued.data && queued.data.message) ? queued.data.message : cacherocketAdmin.i18n.failed;
											bulkStatus.classList.add('is-error');
										}
										bulkStopRun(false);
										return;
									}
									next();
								})
								.catch(function () { next(); });
						} else {
							next();
						}
					})
					.catch(function () { bulkStopRun(false); });
			};

			if (bulkStart) {
				bulkStart.addEventListener('click', function () {
					if (bulkRunning) return;
					bulkRunning = true;
					bulkStart.disabled = true;
					bulkStart.hidden = true;
					if (bulkStop) bulkStop.hidden = false;
					if (bulkStatus) { bulkStatus.classList.remove('is-error'); bulkStatus.textContent = cacherocketAdmin.i18n.bulkRunning; }
					bulkTick();
				});
			}
			if (bulkStop) {
				bulkStop.addEventListener('click', function () { bulkStopRun(false); });
			}
		}

		// Job history table.
		var jobHistory = document.getElementById('cr-job-history');
		if (jobHistory) {
			var jobsTable = jobHistory.querySelector('.cr-jobs');
			var jobsBody = jobHistory.querySelector('tbody');
			var jobsStatus = jobHistory.querySelector('.cr-jobs__status');
			var escapeHtml = function (value) {
				var div = document.createElement('div');
				div.textContent = value == null ? '' : String(value);
				return div.innerHTML;
			};
			crPost('cacherocket_list_jobs')
				.then(function (json) {
					if (!json || !json.success || !json.data || !Array.isArray(json.data.jobs)) {
						if (jobsStatus) jobsStatus.textContent = cacherocketAdmin.i18n.jobsError;
						return;
					}
					var jobs = json.data.jobs;
					if (!jobs.length) {
						if (jobsStatus) jobsStatus.textContent = cacherocketAdmin.i18n.jobsEmpty;
						return;
					}
					var rows = jobs.map(function (job) {
						var src = job.sourceUrl || '';
						var shortSrc = src.length > 60 ? '…' + src.slice(-57) : src;
						return '<tr>' +
							'<td>' + escapeHtml(job.kind) + '</td>' +
							'<td><span class="cr-job-status cr-job-status--' + escapeHtml(job.status) + '">' + escapeHtml(job.status) + '</span></td>' +
							'<td title="' + escapeHtml(src) + '">' + escapeHtml(shortSrc) + '</td>' +
							'<td>' + escapeHtml(job.updatedAt) + '</td>' +
							'</tr>';
					}).join('');
					if (jobsBody) jobsBody.innerHTML = rows;
					if (jobsTable) jobsTable.hidden = false;
					if (jobsStatus) jobsStatus.hidden = true;
				})
				.catch(function () {
					if (jobsStatus) jobsStatus.textContent = cacherocketAdmin.i18n.jobsError;
				});
		}

		// Directory optimize.
		var dirBtn = document.getElementById('cr-dir-optimize');
		var dirStatus = document.getElementById('cr-dir-status');
		if (dirBtn) {
			dirBtn.addEventListener('click', function () {
				dirBtn.disabled = true;
				if (dirStatus) { dirStatus.classList.remove('is-error', 'is-ok'); dirStatus.textContent = cacherocketAdmin.i18n.queuing; }
				crPost('cacherocket_optimize_directory')
					.then(function (json) {
						if (json && json.success && json.data) {
							if (dirStatus) {
								dirStatus.textContent = crFmt('%1$d queued / %2$d found', [json.data.queued || 0, json.data.found || 0]);
								dirStatus.classList.add('is-ok');
							}
						} else if (dirStatus) {
							dirStatus.textContent = (json && json.data && json.data.message) ? json.data.message : cacherocketAdmin.i18n.failed;
							dirStatus.classList.add('is-error');
						}
					})
					.catch(function () {
						if (dirStatus) { dirStatus.textContent = cacherocketAdmin.i18n.requestFailed; dirStatus.classList.add('is-error'); }
					})
					.finally(function () { dirBtn.disabled = false; });
			});
		}
	}
})();
