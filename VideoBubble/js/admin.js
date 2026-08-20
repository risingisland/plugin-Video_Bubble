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
	var videoSourceHidden = document.getElementById('vb-video-source');
	var pickedFileEl = document.getElementById('vb-picked-file');
	var pickVideoBtn = document.getElementById('vb-pick-video-btn');

	var videos = [];
	try {
		videos = JSON.parse(document.getElementById('vb-initial-videos').textContent || '[]');
	} catch (e) { videos = []; }

	var currentEditId = null; // null = adding a new video
	var fbFiles = null;  // lazy-loaded, recursive list of every video file under data/uploads/
	var fbFolder = '';   // folder currently open in the Browse tab (relative to data/uploads/)

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

	// ── Video file picker button + selection label ───────────────────────────
	function setPickedFile(relPath) {
		videoSourceHidden.value = relPath || '';
		if (relPath) {
			pickedFileEl.textContent = relPath;
			pickVideoBtn.textContent = 'Change video';
		} else {
			pickedFileEl.textContent = 'No file selected yet.';
			pickVideoBtn.textContent = 'Choose video…';
		}
	}

	// ── File browser modal (Browse whole data/uploads/ tree, or Upload New) ──
	var fbBg       = document.getElementById('vb-fb-bg');
	var fbCrumb    = document.getElementById('vb-fb-crumb');
	var fbGrid     = document.getElementById('vb-fb-grid');
	var fbSearch   = document.getElementById('vb-fb-search');
	var fbBrowseEl = document.getElementById('vb-fb-browse');
	var fbUploadEl = document.getElementById('vb-fb-upload');
	var fbFolderInput = document.getElementById('vb-fb-folder');
	var fbFileInput   = document.getElementById('vb-fb-file-input');
	var fbDropzone    = document.getElementById('vb-fb-dropzone');
	var fbProgress    = document.getElementById('vb-fb-upload-progress');

	window.vbFbOpen = function () {
		fbFolder = '';
		fbBg.hidden = false;
		document.querySelectorAll('.vb-fb-tab').forEach(function (b, i) { b.classList.toggle('vb-active', i === 0); });
		fbBrowseEl.hidden = false;
		fbUploadEl.hidden = true;
		if (fbSearch) fbSearch.value = '';
		fbLoadFiles();
	};

	window.vbFbClose = function () {
		fbBg.hidden = true;
	};

	window.vbFbTab = function (tab, btn) {
		document.querySelectorAll('.vb-fb-tab').forEach(function (b) { b.classList.remove('vb-active'); });
		btn.classList.add('vb-active');
		fbBrowseEl.hidden = (tab !== 'browse');
		fbUploadEl.hidden = (tab !== 'upload');
		if (tab === 'upload') fbFolderInput.value = fbFolder; // default to wherever the user was browsing
	};

	function fbLoadFiles(force) {
		if (fbFiles && !force) { fbRenderGrid(); return; }
		fbGrid.innerHTML = '<div class="vb-hint">Loading…</div>';
		getJSON('list_uploads').then(function (res) {
			if (!res.ok) { fbGrid.innerHTML = '<p class="vb-fb-empty">Could not load files.</p>'; return; }
			fbFiles = res.files;
			fbRenderGrid();
		});
	}

	function fbRenderGrid() {
		var filter = (fbSearch ? fbSearch.value : '').toLowerCase().trim();

		var files, folders;
		if (filter) {
			files = fbFiles.filter(function (f) { return f.filename.toLowerCase().indexOf(filter) !== -1; });
			folders = [];
		} else {
			var seen = {};
			folders = [];
			files = [];
			fbFiles.forEach(function (f) {
				if (fbFolder && f.filename.indexOf(fbFolder + '/') !== 0) return;
				var rel = fbFolder ? f.filename.slice(fbFolder.length + 1) : f.filename;
				var parts = rel.split('/');
				if (parts.length > 1) {
					var sub = (fbFolder ? fbFolder + '/' : '') + parts[0];
					if (!seen[sub]) { seen[sub] = true; folders.push(parts[0]); }
				} else {
					files.push(f);
				}
			});
		}

		// Breadcrumb
		var crumbHtml = '<span class="vb-fb-crumb-item' + (fbFolder === '' ? ' vb-active' : '') + '" onclick="vbFbNav(\'\')">uploads</span>';
		if (fbFolder) {
			var built = '';
			fbFolder.split('/').forEach(function (p, i, arr) {
				built = built ? built + '/' + p : p;
				var snap = built;
				crumbHtml += ' / <span class="vb-fb-crumb-item' + (i === arr.length - 1 ? ' vb-active' : '') + '" onclick="vbFbNav(\'' + snap.replace(/'/g, "\\'") + '\')">' + escapeHtml(p) + '</span>';
			});
		}
		fbCrumb.innerHTML = crumbHtml;

		if (!folders.length && !files.length) {
			fbGrid.innerHTML = '<p class="vb-fb-empty">No video files found.</p>';
			return;
		}

		var html = '';
		folders.sort().forEach(function (f) {
			var fullPath = (fbFolder ? fbFolder + '/' : '') + f;
			var count = fbFiles.filter(function (file) { return file.filename.indexOf(fullPath + '/') === 0; }).length;
			html += '<div class="vb-fb-folder" onclick="vbFbNav(\'' + fullPath.replace(/'/g, "\\'") + '\')">' +
				'<svg width="32" height="32" viewBox="0 0 24 24" fill="#f59e0b"><path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>' +
				'<span class="vb-fb-name">' + escapeHtml(f) + '</span>' +
				'<span class="vb-fb-folder-count">' + count + '</span>' +
				'</div>';
		});
		files.forEach(function (f) {
			html += '<div class="vb-fb-file" onclick="vbFbSelect(\'' + f.filename.replace(/'/g, "\\'") + '\')" title="' + escapeHtml(f.filename) + '">' +
				'<video src="' + escapeHtml(f.url) + '#t=0.5" preload="metadata" muted></video>' +
				'<span class="vb-fb-name">' + escapeHtml(f.basename) + '</span>' +
				'<span class="vb-fb-size">' + formatBytes(f.size) + '</span>' +
				'</div>';
		});
		fbGrid.innerHTML = html;
	}

	window.vbFbNav = function (folder) {
		fbFolder = folder;
		if (fbSearch) fbSearch.value = '';
		fbRenderGrid();
	};

	window.vbFbFilter = function () {
		fbRenderGrid();
	};

	window.vbFbSelect = function (relPath) {
		setPickedFile(relPath);
		vbFbClose();
	};

	fbFileInput.addEventListener('change', function () {
		if (this.files && this.files.length) fbUpload(this.files[0]);
	});

	['dragover', 'dragenter'].forEach(function (evt) {
		fbDropzone.addEventListener(evt, function (e) { e.preventDefault(); fbDropzone.classList.add('vb-dragover'); });
	});
	['dragleave', 'drop'].forEach(function (evt) {
		fbDropzone.addEventListener(evt, function (e) { e.preventDefault(); fbDropzone.classList.remove('vb-dragover'); });
	});
	fbDropzone.addEventListener('drop', function (e) {
		var f = e.dataTransfer.files && e.dataTransfer.files[0];
		if (f) fbUpload(f);
	});

	function fbUpload(file) {
		var fd = new FormData();
		fd.append('file', file);
		fd.append('folder', fbFolderInput.value.trim());

		fbProgress.hidden = false;
		fbProgress.textContent = 'Uploading…';

		postForm('upload_file', fd).then(function (res) {
			fbProgress.hidden = true;
			fbFileInput.value = '';
			if (!res.ok) { toast(res.msg || 'Upload failed.', true); return; }
			fbFiles = null; // list has changed - refetch next time browse is opened
			setPickedFile(res.filename);
			toast('Video uploaded.');
			vbFbClose();
		}).catch(function () {
			fbProgress.hidden = true;
			toast('Network error uploading video.', true);
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
			fbFiles = null; // list may have changed (old file cleaned up on edit)
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
		setPickedFile('');
		videoForm.querySelectorAll('[data-type-field]').forEach(function (field) {
			field.hidden = (field.dataset.typeField !== 'mp4');
		});
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
				// mp4: pre-fill the picker with the current file, so re-saving without
				// touching it just keeps referencing the same file (no re-upload).
				setPickedFile(v.source);
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
				fbFiles = null;
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
