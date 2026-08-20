(function () {
	'use strict';

	var CONFIG_ID = 'vb-config';
	var configEl = document.getElementById(CONFIG_ID);
	if (!configEl) return;

	var cfg;
	try { cfg = JSON.parse(configEl.textContent); } catch (e) { return; }
	if (!cfg || !cfg.videos || !cfg.videos.length) return;

	var wrapper = document.getElementById('vb-wrapper');
	if (!wrapper) return;

	var settings = cfg.settings;
	var videos   = cfg.videos;

	// ── Respect a prior dismissal ─────────────────────────────────────────
	var storageKey = 'vb_dismissed';
	var wasDismissed =
		(settings.remember_closed === 'session' && !!sessionStorage.getItem(storageKey)) ||
		(settings.remember_closed === 'local' && !!localStorage.getItem(storageKey));

	// ── DOM refs ───────────────────────────────────────────────────────────
	var mediaEl      = document.getElementById('vb-media');
	var textEl       = document.getElementById('vb-text');
	var dismissBtn   = document.getElementById('vb-dismiss');
	var collapseBtn  = document.getElementById('vb-btn-collapse');
	var playBtn      = document.getElementById('vb-btn-play');
	var replayBtn    = document.getElementById('vb-btn-replay');
	var muteBtn      = document.getElementById('vb-btn-mute');
	var unmuteBtn    = document.getElementById('vb-btn-unmute');
	var expandBtn    = document.getElementById('vb-btn-expand');
	var nextListEl   = document.getElementById('vb-next-list');
	var nextBtn      = document.getElementById('vb-next-btn');

	var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	// ── YouTube IFrame API loader (lazy, only if needed) ────────────────────
	var ytApiPromise = null;
	function ensureYouTubeAPI() {
		if (ytApiPromise) return ytApiPromise;
		ytApiPromise = new Promise(function (resolve) {
			if (window.YT && window.YT.Player) { resolve(window.YT); return; }
			var prevCallback = window.onYouTubeIframeAPIReady;
			window.onYouTubeIframeAPIReady = function () {
				if (typeof prevCallback === 'function') prevCallback();
				resolve(window.YT);
			};
			if (!document.querySelector('script[data-vb-yt-api]')) {
				var tag = document.createElement('script');
				tag.src = 'https://www.youtube.com/iframe_api';
				tag.setAttribute('data-vb-yt-api', '1');
				document.head.appendChild(tag);
			}
		});
		return ytApiPromise;
	}

	// Sizes a 16:9 iframe to fully COVER its container (no letterboxing).
	function coverFitIframe(iframe, container) {
		if (!iframe || !container) return;
		var cw = container.clientWidth;
		var ch = container.clientHeight;
		if (!cw || !ch) return;

		var aspect = 16 / 9;
		var targetW, targetH;
		if (cw / ch > aspect) {
			// container is relatively wide - fit width, let height overflow/crop
			targetW = cw;
			targetH = cw / aspect;
		} else {
			// container is relatively tall/narrow - fit height, let width overflow/crop
			targetH = ch;
			targetW = ch * aspect;
		}
		iframe.style.width  = targetW + 'px';
		iframe.style.height = targetH + 'px';
	}

	// ── Unified player wrapper: <video> (mp4) or YT.Player (youtube) ────────
	function VBPlayer(el, opts) {
		this.el = el;
		this.opts = opts;
		this.video = null;
		this.yt = null;
		this.iframeEl = null; // tracked separately for runtime cover-fit sizing
		this.onPlay = null;
		this.onPause = null;
	}

	VBPlayer.prototype.load = function (data) {
		this.destroy();
		return data.type === 'youtube' ? this._loadYouTube(data) : this._loadMp4(data);
	};

	VBPlayer.prototype._loadMp4 = function (data) {
		var self = this;
		var video = document.createElement('video');
		video.playsInline = true;
		video.setAttribute('playsinline', '');
		video.muted = true;
		video.loop = !!this.opts.loop_bubble;
		video.preload = 'auto';
		video.src = data.url;
		video.addEventListener('play',  function () { if (self.onPlay)  self.onPlay(); });
		video.addEventListener('pause', function () { if (self.onPause) self.onPause(); });
		video.addEventListener('ended', function () { if (self.onPause) self.onPause(); });
		this.el.innerHTML = '';
		this.el.appendChild(video);
		this.video = video;
		return Promise.resolve();
	};

	VBPlayer.prototype._loadYouTube = function (data) {
		var self = this;
		this.el.innerHTML = '';
		var holder = document.createElement('div');
		this.el.appendChild(holder);

		return ensureYouTubeAPI().then(function (YT) {
			return new Promise(function (resolve) {
				var playerVars = {
					autoplay: 1,
					mute: 1,
					controls: 0,
					disablekb: 1,
					modestbranding: 1,
					rel: 0,
					playsinline: 1,
					fs: 0,
					iv_load_policy: 3
				};
				if (self.opts.loop_bubble) {
					playerVars.loop = 1;
					playerVars.playlist = data.source; // required for single-video looping
				}
				self.yt = new YT.Player(holder, {
					videoId: data.source,
					host: self.opts.use_nocookie_youtube ? 'https://www.youtube-nocookie.com' : undefined,
					playerVars: playerVars,
					events: {
						onReady: function () {
							self.iframeEl = self.yt.getIframe();
							coverFitIframe(self.iframeEl, self.el);
							resolve();
						},
						onStateChange: function (e) {
							if (e.data === YT.PlayerState.PLAYING && self.onPlay) self.onPlay();
							if ((e.data === YT.PlayerState.PAUSED || e.data === YT.PlayerState.ENDED) && self.onPause) self.onPause();
						}
					}
				});
			});
		});
	};

	VBPlayer.prototype.play = function () {
		if (this.video) { var p = this.video.play(); if (p && p.catch) p.catch(function () {}); }
		if (this.yt && this.yt.playVideo) this.yt.playVideo();
	};
	VBPlayer.prototype.pause = function () {
		if (this.video) this.video.pause();
		if (this.yt && this.yt.pauseVideo) this.yt.pauseVideo();
	};
	VBPlayer.prototype.mute = function () {
		if (this.video) this.video.muted = true;
		if (this.yt && this.yt.mute) this.yt.mute();
	};
	VBPlayer.prototype.unmute = function () {
		if (this.video) this.video.muted = false;
		if (this.yt && this.yt.unMute) this.yt.unMute();
	};
	VBPlayer.prototype.replay = function () {
		if (this.video) { this.video.currentTime = 0; }
		if (this.yt && this.yt.seekTo) { this.yt.seekTo(0, true); }
		this.play();
	};
	VBPlayer.prototype.destroy = function () {
		if (this.video) { this.video.pause(); this.video.removeAttribute('src'); this.video.load(); this.video = null; }
		if (this.yt && this.yt.destroy) { this.yt.destroy(); this.yt = null; }
		this.iframeEl = null;
		this.el.innerHTML = '';
	};

	// ── Controller state ────────────────────────────────────────────────────
	var player = new VBPlayer(mediaEl, settings);
	var currentIndex = 0;
	var isFull = false;

	player.onPlay  = function () { wrapper.classList.add('vb-playing'); };
	player.onPause = function () { wrapper.classList.remove('vb-playing'); };

	// Re-fit the iframe any time the media container's box changes size
	// (bubble <-> expanded toggle, breakpoint changes, etc).
	if (window.ResizeObserver) {
		var ro = new ResizeObserver(function () {
			if (player.iframeEl) coverFitIframe(player.iframeEl, mediaEl);
		});
		ro.observe(mediaEl);
	} else {
		window.addEventListener('resize', function () {
			if (player.iframeEl) coverFitIframe(player.iframeEl, mediaEl);
		}, { passive: true });
	}

	function loadVideo(index, opts) {
		opts = opts || {};
		currentIndex = index;
		var data = videos[index];
		return player.load(data).then(function () {
			player.mute();
			if (opts.unmute) player.unmute();
			player.play();
			updateNextListUI();
		});
	}

	function updateNextListUI() {
		if (!nextListEl) return;
		var btns = nextListEl.querySelectorAll('button');
		btns.forEach(function (b, i) {
			b.setAttribute('aria-current', i === currentIndex ? 'true' : 'false');
		});
	}

	// ── Dismiss (bubble state) vs Collapse (full state) ──────────────────────
	function dismiss() {
		wrapper.hidden = true;
		player.destroy();
		if (settings.remember_closed === 'session') sessionStorage.setItem(storageKey, '1');
		if (settings.remember_closed === 'local') localStorage.setItem(storageKey, '1');
	}

	function expand() {
		if (isFull) return;
		isFull = true;
		wrapper.classList.add('vb-full');
		wrapper.setAttribute('aria-label', 'Video message, expanded');
		if (player.video) player.video.currentTime = 0;
		if (player.yt && player.yt.seekTo) player.yt.seekTo(0, true);
		player.unmute();
		player.play();
		updateMuteButtons();
	}

	function collapse() {
		if (!isFull) return;
		isFull = false;
		wrapper.classList.remove('vb-full');
		wrapper.setAttribute('aria-label', 'Video message');
		player.mute();
		player.play();
		updateMuteButtons();
	}

	function togglePlay() {
		if (wrapper.classList.contains('vb-playing')) player.pause();
		else player.play();
	}

	function updateMuteButtons() {
		var muted = player.video ? player.video.muted : true; // best-effort; YT doesn't expose sync state
		if (muteBtn)   muteBtn.hidden = muted;
		if (unmuteBtn) unmuteBtn.hidden = !muted;
	}

	// ── Wire up events ────────────────────────────────────────────────────
	wrapper.setAttribute('role', 'button');
	wrapper.setAttribute('tabindex', '0');
	wrapper.setAttribute('aria-label', 'Video message');

	mediaEl.addEventListener('click', function () {
		if (!isFull) expand();
		else togglePlay();
	});
	if (textEl) textEl.addEventListener('click', expand);

	wrapper.addEventListener('keydown', function (e) {
		if ((e.key === 'Enter' || e.key === ' ') && !isFull && e.target === wrapper) {
			e.preventDefault();
			expand();
		} else if (e.key === 'Escape' && isFull) {
			collapse();
		}
	});

	// Clicking anywhere outside the widget while expanded collapses it back to the bubble
	document.addEventListener('click', function (e) {
		if (isFull && !wrapper.contains(e.target)) collapse();
	});

	if (dismissBtn) dismissBtn.addEventListener('click', function (e) { e.stopPropagation(); dismiss(); });
	if (collapseBtn) collapseBtn.addEventListener('click', function (e) { e.stopPropagation(); collapse(); });
	if (playBtn) playBtn.addEventListener('click', function (e) { e.stopPropagation(); player.play(); });
	if (replayBtn) replayBtn.addEventListener('click', function (e) { e.stopPropagation(); player.replay(); });
	if (muteBtn) muteBtn.addEventListener('click', function (e) { e.stopPropagation(); player.mute(); updateMuteButtons(); });
	if (unmuteBtn) unmuteBtn.addEventListener('click', function (e) { e.stopPropagation(); player.unmute(); updateMuteButtons(); });
	if (expandBtn) expandBtn.addEventListener('click', function (e) {
		e.stopPropagation();
		var target = mediaEl;
		if (target.requestFullscreen) target.requestFullscreen();
		else if (target.webkitRequestFullscreen) target.webkitRequestFullscreen();
	});

	if (nextListEl) {
		nextListEl.querySelectorAll('button').forEach(function (btn, i) {
			btn.addEventListener('click', function (e) {
				e.stopPropagation();
				if (i === currentIndex) return;
				loadVideo(i, { unmute: isFull });
			});
		});
	}

	if (nextBtn) {
		nextBtn.addEventListener('click', function (e) {
			e.stopPropagation();
			loadVideo((currentIndex + 1) % videos.length, { unmute: isFull });
		});
	}

	if (settings.pause_on_tab_switch) {
		document.addEventListener('visibilitychange', function () {
			if (document.hidden) player.pause();
			else player.play();
		});
	}

	// Scale down slightly on scroll (bubble state only) - skipped entirely under reduced-motion
	if (!reducedMotion) {
		var scrollTicking = false;
		window.addEventListener('scroll', function () {
			if (scrollTicking) return;
			scrollTicking = true;
			requestAnimationFrame(function () {
				wrapper.classList.toggle('vb-scrolled', window.scrollY > 1);
				scrollTicking = false;
			});
		}, { passive: true });
	}

	// ── Boot ──────────────────────────────────────────────────────────────
	function boot() {
		wrapper.hidden = false;
		wrapper.classList.remove('vb-pending');
		loadVideo(0, { unmute: false });
	}

	// Clears any remembered dismissal and brings the widget back (bubble state,
	// muted preview) - exposed so a link/button elsewhere on the page can call it.
	function reopen() {
		sessionStorage.removeItem(storageKey);
		localStorage.removeItem(storageKey);
		wrapper.classList.remove('vb-full');
		isFull = false;
		wrapper.hidden = false;
		wrapper.classList.remove('vb-pending');
		loadVideo(currentIndex, { unmute: false });
	}

	window.VideoBubble = window.VideoBubble || {};
	window.VideoBubble.reopen = reopen;

	// Auto-wire any element the theme marks up with data-vb-reopen (e.g. a
	// footer link) - see also the get_vbubble_reopen_link() template tag.
	document.querySelectorAll('[data-vb-reopen]').forEach(function (el) {
		el.addEventListener('click', function (e) {
			e.preventDefault();
			reopen();
		});
	});

	if (!wasDismissed) {
		var delay = Math.max(0, parseInt(settings.show_delay_seconds, 10) || 0);
		if (delay > 0) {
			wrapper.classList.add('vb-pending');
			wrapper.hidden = false;
			setTimeout(boot, delay * 1000);
		} else {
			boot();
		}
	}
	// If wasDismissed, the wrapper stays hidden (as server-rendered) until
	// reopen() is called - we don't autoplay/load a video until then.
})();
