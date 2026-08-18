(function () {
	'use strict';

	var app = document.getElementById('vb-admin-app');
	if (!app) return;

	var AJAX_URL   = app.dataset.ajaxUrl;
	var NONCE      = app.dataset.nonce;
	var toastEl    = document.getElementById('vb-toast');
	var videoList  = document.getElementById('vb-video-list');
	var videoEmpty = document.getElementById('vb-video-empty');
	var videoForm  = document.getElementById('vb-video-form');
	var formHeading = document.getElementById('vb-form-heading');
	var cancelBtn  = document.getElementById('vb-cancel-edit');
	var submitBtn  = document.getElementById('vb-submit-video');
	var settingsForm = document.getElementById('vb-settings-form');
	var existingFilesEl = document.getElementById('vb-existing-files');
	var existingEmptyEl = document.getElementById('vb-existing-empty');
	var existingFileHidden = videoForm.querySelector('input[name="existing_file"]');

	var videos = [];
	try {
		videos = JSON.parse(document.getElementById('vb-initial-videos').textContent || '[]');
	} catch (e) { videos = []; }

	// Explicit source of truth for "are we editing, and what id" - NOT the hidden
	// DOM field, which can be stale after form.reset()/browser autofill/async re-render.
	var currentEditId = null; // null = adding a new video
	var uploadedFilesCache = null; // lazy-loaded list of existing files, refreshed on demand

	// ── Toast ──────────────────────────────────────────────────────────────
	var toastTimer = null;
	function toast(msg, isError) {
		toastEl.textContent = msg;
		toastEl.classList.toggle('vb-toast-error', !!isError);
		toastEl.hidden = false;
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () { toastEl.hidden = true; }, 3500);
	}

	function escapeHtml(str) {
		var div = document.createElement('div');
		div.textContent = str == null ? '' : String(str);
		return div.innerHTML;
	}

	function formatBytes(bytes) {
		if (bytes < 1024) return bytes + ' B';
		if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' KB';
		return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
	}

	// ── Tabs ───────────────────────────────────────────────────────────────
	document.querySelectorAll('.vb-tab-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			document.querySelectorAll('.vb-tab-btn').forEach(function (b) { b.classList.remove('vb-active'); });
			document.querySelectorAll('.vb-tab-panel').forEach(function (p) { p.classList.remove('vb-active'); });
			btn.classList.add('vb-active');
			document.getElementById('vb-tab-' + btn.dataset.tab).classList.add('vb-active');
		});
	});

	// ── Fetch helper ──────────────────────────────────────────────────────
	function postForm(action, formData) {
		formData = formData || new FormData();
		formData.append('vb_action', action);
		formData.append('nonce', NONCE);
		return fetch(AJAX_URL + '&vb_action=' + encodeURIComponent(action), {
			method: 'POST',
			body: formData
		}).then(function (r) { return r.json(); });
	}

	function getJSON(action) {
		return fetch(AJAX_URL + '&vb_action=' + encodeURIComponent(action))
			.then(function (r) { return r.json(); });
	}

	// ── Video type toggle (mp4 vs youtube fields) ───────────────────────────
	videoForm.querySelectorAll('input[name="type"]').forEach(function (radio) {
		radio.addEventListener('change', function () {
			var type = videoForm.querySelector('input[name="type"]:checked').value;
			videoForm.querySelectorAll('[data-type-field]').forEach(function (field) {
				field.hidden = (field.dataset.typeField !== type);
			});
		});
	});

	// ── mp4 source mode toggle (upload vs existing) ─────────────────────────
	videoForm.querySelectorAll('input[name="source_mode"]').forEach(function (radio) {
		radio.addEventListener('change', function () {
			var mode = videoForm.querySelector('input[name="source_mode"]:checked').value;
			videoForm.querySelectorAll('[data-source-mode]').forEach(function (field) {
				field.hidden = (field.dataset.sourceMode !== mode);
			});
			if (mode === 'existing') loadExistingFiles();
		});
	});

	function loadExistingFiles(forceRefresh) {
		if (uploadedFilesCache && !forceRefresh) {
			renderExistingFiles(uploadedFilesCache);
			return;
		}
		existingFilesEl.innerHTML = '<p class="vb-hint">Loading…</p>';
		getJSON('list_uploads').then(function (res) {
			if (!res.ok) { toast('Could not load existing files.', true); return; }
			uploadedFilesCache = res.files;
			renderExistingFiles(res.files);
		});
	}

	function renderExistingFiles(files) {
		existingFilesEl.innerHTML = '';
		existingEmptyEl.hidden = files.length > 0;
		var selected = existingFileHidden.value;

		files.forEach(function (f) {
			var row = document.createElement('label');
			row.className = 'vb-existing-file-row';
			if (f.filename === selected) row.classList.add('vb-selected');

			row.innerHTML =
				'<input type="radio" name="existing_file_picker" value="' + escapeHtml(f.filename) + '"' + (f.filename === selected ? ' checked' : '') + '>' +
				'<video src="' + escapeHtml(f.url) + '#t=0.5" preload="metadata" muted></video>' +
				'<span class="vb-existing-file-info"><strong>' + escapeHtml(f.filename) + '</strong><small>' + formatBytes(f.size) + '</small></span>';

			row.querySelector('input').addEventListener('change', function () {
				existingFileHidden.value = f.filename;
				existingFilesEl.querySelectorAll('.vb-existing-file-row').forEach(function (r) { r.classList.remove('vb-selected'); });
				row.classList.add('vb-selected');
			});

			existingFilesEl.appendChild(row);
		});
	}

	// ── Render video list ────────────────────────────────────────────────
	function iconSvg(name) {
		var icons = {
			edit: '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
			trash: '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
			drag: '<circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/>'
		};
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + (icons[name] || '') + '</svg>';
	}

	function renderVideos() {
		videoList.innerHTML = '';
		videoEmpty.hidden = videos.length > 0;

		videos.forEach(function (v) {
			var li = document.createElement('li');
			li.className = 'vb-video-item';
			li.draggable = true;
			li.dataset.id = v.id;

			var typeBadge = v.type === 'youtube' ? 'YouTube' : 'MP4';

			li.innerHTML =
				'<span class="vb-drag-handle" title="Drag to reorder">' + iconSvg('drag') + '</span>' +
				'<span class="vb-video-badge vb-badge-' + v.type + '">' + typeBadge + '</span>' +
				'<span class="vb-video-info">' +
					'<strong>' + escapeHtml(v.label || '(untitled)') + '</strong>' +
					'<small>' + escapeHtml(v.source) + '</small>' +
				'</span>' +
				'<button type="button" class="vb-icon-btn" data-edit="' + v.id + '" title="Edit">' + iconSvg('edit') + '</button>' +
				'<button type="button" class="vb-icon-btn vb-icon-danger" data-delete="' + v.id + '" title="Delete">' + iconSvg('trash') + '</button>';

			videoList.appendChild(li);
		});
	}

	renderVideos();

	// ── Add / Edit video ─────────────────────────────────────────────────
	videoForm.addEventListener('submit', function (e) {
		e.preventDefault();
		var fd = new FormData(videoForm);

		// currentEditId (JS state), not the DOM hidden field, decides insert vs update
		fd.set('id', currentEditId ? String(currentEditId) : '0');

		submitBtn.disabled = true;

		postForm('save_video', fd).then(function (res) {
			submitBtn.disabled = false;
			if (!res.ok) { toast(res.msg || 'Error saving video', true); return; }

			var idx = videos.findIndex(function (v) { return String(v.id) === String(res.row.id); });
			if (idx >= 0) videos[idx] = res.row; else videos.push(res.row);
			renderVideos();
			resetVideoForm();
			uploadedFilesCache = null; // list may have changed (new upload, or old file cleaned up)
			toast('Video saved.');
		}).catch(function () {
			submitBtn.disabled = false;
			toast('Network error saving video.', true);
		});
	});

	function resetVideoForm() {
		currentEditId = null;
		videoForm.reset();
		videoForm.querySelector('input[name="id"]').value = 0;
		videoForm.querySelector('input[name="type"][value="mp4"]').checked = true;
		videoForm.querySelector('input[name="source_mode"][value="upload"]').checked = true;
		existingFileHidden.value = '';
		videoForm.querySelectorAll('[data-type-field]').forEach(function (field) {
			field.hidden = (field.dataset.typeField !== 'mp4');
		});
		videoForm.querySelectorAll('[data-source-mode]').forEach(function (field) {
			field.hidden = (field.dataset.sourceMode !== 'upload');
		});
		videoForm.querySelector('[data-existing-file]').textContent = '';
		cancelBtn.hidden = true;
		submitBtn.textContent = 'Save video';
		formHeading.textContent = 'Add a Video';
	}

	cancelBtn.addEventListener('click', resetVideoForm);

	// ── Edit / Delete (event delegation) ─────────────────────────────────
	videoList.addEventListener('click', function (e) {
		var editBtn = e.target.closest('[data-edit]');
		var delBtn  = e.target.closest('[data-delete]');

		if (editBtn) {
			var v = videos.find(function (x) { return String(x.id) === editBtn.dataset.edit; });
			if (!v) return;

			currentEditId = v.id;
			videoForm.querySelector('input[name="id"]').value = v.id;
			videoForm.querySelector('input[name="type"][value="' + v.type + '"]').checked = true;
			videoForm.querySelectorAll('[data-type-field]').forEach(function (field) {
				field.hidden = (field.dataset.typeField !== v.type);
			});
			videoForm.querySelector('input[name="label"]').value = v.label || '';

			if (v.type === 'youtube') {
				videoForm.querySelector('input[name="youtube_url"]').value = v.source;
			} else {
				// mp4: default to "existing" mode, pre-selecting the current file,
				// so re-saving without touching anything doesn't re-upload/rename it.
				videoForm.querySelector('input[name="source_mode"][value="existing"]').checked = true;
				videoForm.querySelectorAll('[data-source-mode]').forEach(function (field) {
					field.hidden = (field.dataset.sourceMode !== 'existing');
				});
				existingFileHidden.value = v.source;
				loadExistingFiles(true);
			}

			cancelBtn.hidden = false;
			submitBtn.textContent = 'Update video';
			formHeading.textContent = 'Editing: ' + (v.label || v.source);
			videoForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}

		if (delBtn) {
			if (!confirm('Delete this video?')) return;
			var fd = new FormData();
			fd.append('id', delBtn.dataset.delete);
			postForm('delete_video', fd).then(function (res) {
				if (!res.ok) { toast(res.msg || 'Error deleting video', true); return; }
				videos = videos.filter(function (v) { return String(v.id) !== delBtn.dataset.delete; });
				renderVideos();
				uploadedFilesCache = null;
				toast('Video deleted.');
				if (currentEditId != null && String(currentEditId) === delBtn.dataset.delete) resetVideoForm();
			});
		}
	});

	// ── Native HTML5 drag-and-drop reordering ────────────────────────────
	var dragSrcId = null;

	videoList.addEventListener('dragstart', function (e) {
		var li = e.target.closest('.vb-video-item');
		if (!li) return;
		dragSrcId = li.dataset.id;
		e.dataTransfer.effectAllowed = 'move';
		li.classList.add('vb-dragging');
	});

	videoList.addEventListener('dragend', function (e) {
		var li = e.target.closest('.vb-video-item');
		if (li) li.classList.remove('vb-dragging');
	});

	videoList.addEventListener('dragover', function (e) {
		e.preventDefault();
		var over = e.target.closest('.vb-video-item');
		if (!over || over.dataset.id === dragSrcId) return;

		var rect = over.getBoundingClientRect();
		var before = (e.clientY - rect.top) < (rect.height / 2);
		var dragging = videoList.querySelector('[data-id="' + dragSrcId + '"]');
		if (!dragging) return;

		if (before) {
			over.parentNode.insertBefore(dragging, over);
		} else {
			over.parentNode.insertBefore(dragging, over.nextSibling);
		}
	});

	videoList.addEventListener('drop', function (e) {
		e.preventDefault();
		var newOrder = Array.prototype.map.call(
			videoList.querySelectorAll('.vb-video-item'),
			function (li) { return li.dataset.id; }
		);
		var fd = new FormData();
		fd.append('order', JSON.stringify(newOrder));
		postForm('reorder_videos', fd).then(function (res) {
			if (res.ok) {
				videos.sort(function (a, b) { return newOrder.indexOf(String(a.id)) - newOrder.indexOf(String(b.id)); });
				toast('Order saved.');
			} else {
				toast('Error saving order.', true);
			}
		});
	});

	// ── Settings save ─────────────────────────────────────────────────────
	settingsForm.addEventListener('submit', function (e) {
		e.preventDefault();
		var fd = new FormData(settingsForm);
		var settings = {};
		fd.forEach(function (value, key) { settings[key] = value; });

		// checkboxes absent from FormData when unchecked - normalize explicitly
		['autoplay_muted', 'loop_bubble', 'pause_on_tab_switch', 'show_close_button', 'use_nocookie_youtube'].forEach(function (key) {
			settings[key] = !!settingsForm.querySelector('[name="' + key + '"]').checked;
		});

		var body = new FormData();
		body.append('settings', JSON.stringify(settings));

		var settingsSubmitBtn = settingsForm.querySelector('button[type="submit"]');
		settingsSubmitBtn.disabled = true;

		postForm('save_settings', body).then(function (res) {
			settingsSubmitBtn.disabled = false;
			if (!res.ok) { toast(res.msg || 'Error saving settings', true); return; }
			toast('Settings saved.');
		}).catch(function () {
			settingsSubmitBtn.disabled = false;
			toast('Network error saving settings.', true);
		});
	});

})();
