<?php if (!defined('IN_GS')) { die('You cannot load this page directly.'); }

/*
Plugin Name: Video Bubble
Description: Floating video greeting bubble (mp4 + YouTube) built with vanilla JS.
Version: 1.0
Author: risingisland
Author URI: https://ko-fi.com/ericmontgomery
*/

i18n_merge('VideoBubble') || i18n_merge('VideoBubble', 'en_US');

// ─── Constants ───────────────────────────────────────────────────────────────
define('VideoBubble_DB_PATH',    GSDATAOTHERPATH . 'VideoBubble.db');
define('VideoBubble_SLUG',       basename(__FILE__, '.php'));
define('VideoBubble_PLUGIN_DIR', GSPLUGINPATH . VideoBubble_SLUG . '/');
define('VideoBubble_UPLOAD_DIR', GSDATAUPLOADPATH . 'vbubble/'); // legacy default - public, served directly by <video>
define('VideoBubble_UPLOAD_ROOT', GSDATAUPLOADPATH); // browsable root for the file picker - all of data/uploads/

// ─── Bootstrap ───────────────────────────────────────────────────────────────

register_plugin(
	VideoBubble_SLUG,
	'Video Bubble',
	'1.0',
	'risingisland',
	'https://ko-fi.com/ericmontgomery',
	'Floating video greeting bubble (mp4 + YouTube), vanilla JS front-end.',
	'pages',
	'VideoBubble_admin_page'
);

add_action('pages-sidebar', 'createSideMenu', array(
	VideoBubble_SLUG,
	i18n_r('VideoBubble/lang_Menu_Title') . ' <svg xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle;" width="22" height="22" viewBox="0 0 20 20"><path d="M0 0h20v20H0z" fill="none"/><path fill="#416cc9" d="M6 8a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1zm6 1l1.146-1.146a.5.5 0 0 1 .854.353v3.586a.5.5 0 0 1-.854.353L12 11zm-2-7a8 8 0 1 1-3.613 15.14l-.121-.065l-3.645.91a.5.5 0 0 1-.62-.441v-.082l.014-.083l.91-3.644l-.063-.12a8 8 0 0 1-.83-2.887l-.025-.382L2 10a8 8 0 0 1 8-8m0 1a7 7 0 0 0-6.106 10.425a.5.5 0 0 1 .063.272l-.014.094l-.756 3.021l3.024-.754a.5.5 0 0 1 .188-.01l.091.021l.087.039A7 7 0 1 0 10 3"/></svg>',
	'?id=' . VideoBubble_SLUG
));

VideoBubble_init_db();

// ── Early AJAX intercept ──────────────────────────────────────────────────────
// Must run before GS outputs any HTML. Only fires when:
//   (a) we are on the Video Bubble admin page (?id=VideoBubble), AND
//   (b) a vb_action param is present
if (
	!empty($_REQUEST['vb_action']) &&
	isset($_GET['id']) && $_GET['id'] === VideoBubble_SLUG
) {
	VideoBubble_handle_ajax($_REQUEST['vb_action']);
	// VideoBubble_handle_ajax() always exit()s - this is a safety net:
	exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// SITEURL helper - guards against http/https scheme mismatch (known GS quirk)
// ─────────────────────────────────────────────────────────────────────────────

function VideoBubble_site_url() {
	global $SITEURL;
	$url = $SITEURL;

	$is_https = (
		(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
		(!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
		(!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
	);

	if ($is_https && strpos($url, 'http://') === 0) {
		$url = 'https://' . substr($url, 7);
	}

	return $url;
}

function VideoBubble_plugin_url() {
	return VideoBubble_site_url() . 'plugins/' . VideoBubble_SLUG . '/';
}

function VideoBubble_upload_url() {
	return VideoBubble_site_url() . 'data/uploads/';
}

// ─────────────────────────────────────────────────────────────────────────────
// DATABASE
// ─────────────────────────────────────────────────────────────────────────────

function VideoBubble_db() {
	static $db = null;
	if ($db === null) {
		$db = new SQLite3(VideoBubble_DB_PATH);
		$db->busyTimeout(5000);
		$db->exec('PRAGMA journal_mode=WAL;');
	}
	return $db;
}

function VideoBubble_default_settings() {
	return [
		'greet_text'          => 'Hey 👋',
		'position'             => 'right',		// 'right' | 'left'
		'accent_color'         => '#7432ff',
		'more_info_label'      => '',
		'more_info_url'        => '',
		'more_info_target'     => '_blank',		// '_blank' | '_self'
		'autoplay_muted'       => true,
		'loop_bubble'          => true,
		'pause_on_tab_switch'  => true,
		'show_close_button'    => true,
		'remember_closed'      => 'off',		// 'off' | 'session' | 'local'
		'show_delay_seconds'   => 0,
		'use_nocookie_youtube' => true,
		'video_switcher_style' => 'next',		// 'all' | 'next'
		'custom_css'           => '',
	];
}

function VideoBubble_init_db() {
	$db = VideoBubble_db();
	$db->exec("
		CREATE TABLE IF NOT EXISTS vb_videos (
			id          INTEGER PRIMARY KEY AUTOINCREMENT,
			type        TEXT    NOT NULL DEFAULT 'mp4',
			source      TEXT    NOT NULL,
			label       TEXT    NOT NULL DEFAULT '',
			sort_order  INTEGER NOT NULL DEFAULT 0,
			created     INTEGER NOT NULL DEFAULT (strftime('%s','now'))
		);
		CREATE TABLE IF NOT EXISTS vb_settings (
			id       INTEGER PRIMARY KEY CHECK (id = 1),
			settings TEXT NOT NULL DEFAULT '{}'
		);
	");

	// Seed the singleton settings row if it doesn't exist yet
	$res = $db->query("SELECT id FROM vb_settings WHERE id = 1 LIMIT 1");
	if (!$res || !$res->fetchArray()) {
		$stmt = $db->prepare("INSERT INTO vb_settings (id, settings) VALUES (1, :s)");
		$stmt->bindValue(':s', json_encode(VideoBubble_default_settings()), SQLITE3_TEXT);
		$stmt->execute();
	}

	// Ensure upload directory exists - this one is PUBLIC (served by <video src>)
	if (!is_dir(VideoBubble_UPLOAD_DIR)) {
		mkdir(VideoBubble_UPLOAD_DIR, 0755, true);
	}
}

function VideoBubble_get_settings() {
	$db  = VideoBubble_db();
	$res = $db->query("SELECT settings FROM vb_settings WHERE id = 1 LIMIT 1");
	$row = $res ? $res->fetchArray(SQLITE3_ASSOC) : null;
	$stored = $row ? (json_decode($row['settings'], true) ?: []) : [];
	// merge over defaults so newly-added settings keys always have a value
	return array_merge(VideoBubble_default_settings(), $stored);
}

function VideoBubble_save_settings($incoming) {
	$defaults = VideoBubble_default_settings();
	$clean    = [];

	$clean['greet_text']		= trim(strip_tags((string)($incoming['greet_text'] ?? $defaults['greet_text'])));
	$clean['position']			= in_array($incoming['position'] ?? '', ['left', 'right'], true) ? $incoming['position'] : $defaults['position'];
	$clean['accent_color']		= preg_match('/^#[0-9a-fA-F]{3,8}$/', (string)($incoming['accent_color'] ?? '')) ? $incoming['accent_color'] : $defaults['accent_color'];
	$clean['more_info_label']	= trim(strip_tags((string)($incoming['more_info_label'] ?? '')));
	$clean['more_info_url']		= trim((string)($incoming['more_info_url'] ?? ''));
	$clean['more_info_target']	= in_array($incoming['more_info_target'] ?? '', ['_blank', '_self'], true) ? $incoming['more_info_target'] : $defaults['more_info_target'];
	$clean['autoplay_muted']	= !empty($incoming['autoplay_muted']);
	$clean['loop_bubble']	= !empty($incoming['loop_bubble']);
	$clean['pause_on_tab_switch'] = !empty($incoming['pause_on_tab_switch']);
	$clean['show_close_button']	= !empty($incoming['show_close_button']);
	$clean['remember_closed']	= in_array($incoming['remember_closed'] ?? '', ['off', 'session', 'local'], true) ? $incoming['remember_closed'] : $defaults['remember_closed'];
	$clean['show_delay_seconds'] = max(0, (int)($incoming['show_delay_seconds'] ?? 0));
	$clean['use_nocookie_youtube'] = !empty($incoming['use_nocookie_youtube']);
	$clean['video_switcher_style'] = in_array($incoming['video_switcher_style'] ?? '', ['all', 'next'], true) ? $incoming['video_switcher_style'] : $defaults['video_switcher_style'];
	// custom_css is intentionally NOT strip_tags'd (it's CSS), but strip any accidental </style> break-out
	$clean['custom_css']		= str_ireplace('</style', '', (string)($incoming['custom_css'] ?? ''));

	$db   = VideoBubble_db();
	$stmt = $db->prepare("UPDATE vb_settings SET settings = :s WHERE id = 1");
	$stmt->bindValue(':s', json_encode($clean), SQLITE3_TEXT);
	$stmt->execute();

	return $clean;
}

function VideoBubble_get_videos() {
	$db  = VideoBubble_db();
	$res = $db->query("SELECT * FROM vb_videos ORDER BY sort_order ASC, id ASC");
	$out = [];
	while ($row = $res->fetchArray(SQLITE3_ASSOC)) { $out[] = $row; }
	return $out;
}

function VideoBubble_get_video($id) {
	$db   = VideoBubble_db();
	$stmt = $db->prepare("SELECT * FROM vb_videos WHERE id = :id LIMIT 1");
	$stmt->bindValue(':id', (int)$id, SQLITE3_INTEGER);
	$res  = $stmt->execute();
	return $res ? $res->fetchArray(SQLITE3_ASSOC) : null;
}

// Extract an 11-char YouTube video ID from a pasted URL, or pass through if already an ID
function VideoBubble_parse_youtube_id($input) {
	$input = trim((string)$input);
	if ($input === '') return '';

	// Already looks like a bare video ID
	if (preg_match('/^[A-Za-z0-9_-]{11}$/', $input)) return $input;

	$patterns = [
		'/[?&]v=([A-Za-z0-9_-]{11})/',       // watch?v=ID
		'#youtu\.be/([A-Za-z0-9_-]{11})#',   // youtu.be/ID
		'#/embed/([A-Za-z0-9_-]{11})#',      // /embed/ID
		'#/shorts/([A-Za-z0-9_-]{11})#',     // /shorts/ID
	];
	foreach ($patterns as $p) {
		if (preg_match($p, $input, $m)) return $m[1];
	}
	return '';
}

// Clean up a user-supplied relative folder/file path: normalise slashes, drop any
// '..' or empty segments (rather than erroring out), and strip characters that
// aren't safe in a folder/file name. Used any time a path comes from $_POST/$_GET.
function VideoBubble_sanitize_relpath($path) {
	$path = str_replace('\\', '/', (string)$path);
	$parts = explode('/', $path);
	$clean = [];
	foreach ($parts as $p) {
		$p = trim($p);
		if ($p === '' || $p === '.' || $p === '..') continue;
		$p = preg_replace('/[^A-Za-z0-9 _\-\.]/', '-', $p);
		$clean[] = $p;
	}
	return implode('/', $clean);
}

// Resolve a video row's stored 'source' value to an absolute filesystem path.
function VideoBubble_resolve_source_path($source) {
	$source = (string)$source;
	if ($source === '') return '';

	$root_path = VideoBubble_UPLOAD_ROOT . VideoBubble_sanitize_relpath($source);
	if (strpos($source, '/') === false && !file_exists($root_path)) {
		return VideoBubble_UPLOAD_DIR . $source;
	}
	return $root_path;
}

// Returns the public URL
function VideoBubble_resolve_source_url($source) {
	$source = (string)$source;
	if ($source === '') return '';

	$rel = VideoBubble_sanitize_relpath($source);
	if (strpos($source, '/') === false && !file_exists(VideoBubble_UPLOAD_ROOT . $rel)) {
		$rel = 'vbubble/' . $rel; // legacy bare filename - resolve against its old home
	}
	$parts = array_map('rawurlencode', explode('/', $rel));
	return VideoBubble_upload_url() . implode('/', $parts);
}

// Sanitize an uploaded filename while KEEPING the original name where possible.
function VideoBubble_safe_upload_filename($original, $dir) {
	$original = basename($original);
	$ext  = strtolower(pathinfo($original, PATHINFO_EXTENSION));
	$base = pathinfo($original, PATHINFO_FILENAME);

	$base = preg_replace('/[^A-Za-z0-9_\-]+/', '-', $base);
	$base = trim($base, '-');
	if ($base === '') $base = 'video';

	$candidate = $base . '.' . $ext;
	$i = 1;
	while (file_exists($dir . $candidate)) {
		$candidate = $base . '-' . $i . '.' . $ext;
		$i++;
	}
	return $candidate;
}

// True if any OTHER video row (besides $exclude_id) still references this source path.
function VideoBubble_file_in_use($source, $exclude_id = 0) {
	$db   = VideoBubble_db();
	$stmt = $db->prepare("SELECT id FROM vb_videos WHERE type='mp4' AND source = :s AND id != :ex LIMIT 1");
	$stmt->bindValue(':s',  $source, SQLITE3_TEXT);
	$stmt->bindValue(':ex', (int)$exclude_id, SQLITE3_INTEGER);
	$res = $stmt->execute();
	return (bool)($res ? $res->fetchArray() : false);
}

// Recursively list every mp4/webm/ogg file anywhere under data/uploads/
function VideoBubble_list_uploaded_files() {
	$files   = [];
	$allowed = ['mp4', 'webm', 'ogg'];
	$root    = VideoBubble_UPLOAD_ROOT;

	$scan = function ($dir, $rel) use (&$scan, &$files, $allowed) {
		$items = @scandir($dir);
		if ($items === false) return;
		foreach ($items as $item) {
			if ($item === '' || $item[0] === '.') continue; // skip dotfiles/.htaccess/hidden
			$full = $dir . $item;
			$relPath = $rel !== '' ? $rel . '/' . $item : $item;
			if (is_dir($full)) {
				$scan($full . '/', $relPath);
			} else {
				$ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
				if (!in_array($ext, $allowed, true)) continue;
				$files[] = [
					'filename' => $relPath,
					'basename' => $item,
					'size'     => filesize($full),
					'url'      => VideoBubble_resolve_source_url($relPath),
				];
			}
		}
	};
	$scan($root, '');

	usort($files, function ($a, $b) { return strcasecmp($a['filename'], $b['filename']); });
	return $files;
}

// ─────────────────────────────────────────────────────────────────────────────
// FRONT-END TEMPLATE TAG
// Usage in a theme file: call get_vbubble() wrapped in PHP open/close tags
// ─────────────────────────────────────────────────────────────────────────────

function VideoBubble_icon($name) {
	$icons = [
		'close'    => '<path d="M18 6 6 18"/><path d="M6 6l12 12"/>',
		'play'     => '<polygon points="6 3 20 12 6 21 6 3"/>',
		'replay'   => '<path d="M21 12a9 9 0 1 1-3-6.7"/><polyline points="21 3 21 9 15 9"/>',
		'volume'   => '<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M18.5 5.5a9 9 0 0 1 0 13"/>',
		'mute'     => '<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/>',
		'expand'   => '<path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/>',
		'next'     => '<polyline points="9 18 15 12 9 6"/>',
	];
	if (!isset($icons[$name])) return '';
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $icons[$name] . '</svg>';
}

function get_vbubble() {
	static $rendered = false;
	if ($rendered) return; // only one instance per page is supported
	$rendered = true;

	$settings = VideoBubble_get_settings();
	$videos   = VideoBubble_get_videos();
	if (empty($videos)) return; // nothing configured yet - render nothing

	$plugin_url = VideoBubble_plugin_url();

	// Build the front-end payload: only what JS needs, with resolved public URLs
	$payload_videos = [];
	foreach ($videos as $v) {
		$payload_videos[] = [
			'type'   => $v['type'],
			'source' => $v['source'],
			'label'  => $v['label'],
			'url'    => $v['type'] === 'mp4' ? VideoBubble_resolve_source_url($v['source']) : null,
		];
	}

	$has_cta   = ($settings['more_info_label'] !== '' && $settings['more_info_url'] !== '');
	$has_next  = count($videos) > 1;
	$wrap_cls  = 'vb-wrapper vb-position-' . ($settings['position'] === 'left' ? 'left' : 'right');

	echo '<link rel="stylesheet" href="' . htmlspecialchars($plugin_url) . 'css/vbubble.css">' . "\n";
	?>
	<div class="<?= $wrap_cls ?>" id="vb-wrapper" hidden>
		<button type="button" class="vb-dismiss" id="vb-dismiss" aria-label="<?= i18n_r('VideoBubble/lang_Aria_Dismiss') ?>"><?= VideoBubble_icon('close') ?></button>

		<div class="vb-media" id="vb-media"></div>

		<?php if ($settings['greet_text'] !== ''): ?>
		<p class="vb-text" id="vb-text"><?= htmlspecialchars($settings['greet_text']) ?></p>
		<?php endif; ?>

		<button type="button" class="vb-icon-btn vb-btn-collapse" id="vb-btn-collapse" aria-label="<?= i18n_r('VideoBubble/lang_Aria_Collapse') ?>"><?= VideoBubble_icon('close') ?></button>
		<button type="button" class="vb-icon-btn vb-btn-play" id="vb-btn-play" aria-label="<?= i18n_r('VideoBubble/lang_Aria_Play') ?>"><?= VideoBubble_icon('play') ?></button>

		<div class="vb-media-actions">
			<button type="button" class="vb-icon-btn vb-btn-replay" id="vb-btn-replay" aria-label="<?= i18n_r('VideoBubble/lang_Aria_Replay') ?>"><?= VideoBubble_icon('replay') ?></button>
			<button type="button" class="vb-icon-btn vb-btn-mute" id="vb-btn-mute" aria-label="<?= i18n_r('VideoBubble/lang_Aria_Mute') ?>"><?= VideoBubble_icon('volume') ?></button>
			<button type="button" class="vb-icon-btn vb-btn-unmute" id="vb-btn-unmute" hidden aria-label="<?= i18n_r('VideoBubble/lang_Aria_Unmute') ?>"><?= VideoBubble_icon('mute') ?></button>
			<button type="button" class="vb-icon-btn vb-btn-expand" id="vb-btn-expand" aria-label="<?= i18n_r('VideoBubble/lang_Aria_Fullscreen') ?>"><?= VideoBubble_icon('expand') ?></button>
		</div>

		<?php if ($has_cta || $has_next): ?>
		<div class="vb-bottom-actions" id="vb-bottom-actions">
			<?php if ($has_next && $settings['video_switcher_style'] === 'all'): ?>
			<div class="vb-next-list" id="vb-next-list">
				<?php foreach ($videos as $i => $v): ?>
				<button type="button" aria-current="<?= $i === 0 ? 'true' : 'false' ?>"><?= htmlspecialchars($v['label'] !== '' ? $v['label'] : ('Video ' . ($i + 1))) ?></button>
				<?php endforeach; ?>
			</div>
			<?php elseif ($has_next): ?>
			<button type="button" class="vb-next-single" id="vb-next-btn">
				<?= VideoBubble_icon('next') ?>
				<span><?= i18n_r('VideoBubble/lang_Next_Video') ?></span>
			</button>
			<?php endif; ?>

			<?php if ($has_cta): ?>
			<div class="vb-cta">
				<a href="<?= htmlspecialchars($settings['more_info_url']) ?>" target="<?= htmlspecialchars($settings['more_info_target']) ?>"<?= $settings['more_info_target'] === '_blank' ? ' rel="noopener"' : '' ?>><?= htmlspecialchars($settings['more_info_label']) ?></a>
			</div>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<?php if ($settings['custom_css'] !== ''): ?>
		<style><?= $settings['custom_css'] /* pre-sanitized on save: </style break-out already stripped */ ?></style>
		<?php endif; ?>
	</div>
	<script type="application/json" id="vb-config"><?= json_encode([
		'settings' => [
			'loop_bubble'          => $settings['loop_bubble'],
			'use_nocookie_youtube' => $settings['use_nocookie_youtube'],
			'pause_on_tab_switch'  => $settings['pause_on_tab_switch'],
			'remember_closed'      => $settings['remember_closed'],
			'show_delay_seconds'   => $settings['show_delay_seconds'],
		],
		'videos' => $payload_videos,
	], JSON_UNESCAPED_SLASHES) ?></script>
	<script src="<?= htmlspecialchars($plugin_url) ?>js/vbubble.js" defer></script>
	<?php
}

// ─────────────────────────────────────────────────────────────────────────────
// FRONT-END TEMPLATE TAG - reopen link
// Brings a dismissed bubble back (bubble/preview state).
// Usage in a theme file, e.g. the footer: call get_vbubble_reopen_link()
// ─────────────────────────────────────────────────────────────────────────────

function get_vbubble_reopen_link($label = null, $echo = true) {
	if ($label === null) $label = i18n_r('VideoBubble/lang_Reopen_Link');
	$html = '<a href="#" data-vb-reopen>' . htmlspecialchars($label) . ' <svg xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle;" width="1.5em" height="1.5em" viewBox="0 0 20 20"><path d="M0 0h20v20H0z" fill="none"/><path fill="#416cc9" d="M6 8a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1zm6 1l1.146-1.146a.5.5 0 0 1 .854.353v3.586a.5.5 0 0 1-.854.353L12 11zm-2-7a8 8 0 1 1-3.613 15.14l-.121-.065l-3.645.91a.5.5 0 0 1-.62-.441v-.082l.014-.083l.91-3.644l-.063-.12a8 8 0 0 1-.83-2.887l-.025-.382L2 10a8 8 0 0 1 8-8m0 1a7 7 0 0 0-6.106 10.425a.5.5 0 0 1 .063.272l-.014.094l-.756 3.021l3.024-.754a.5.5 0 0 1 .188-.01l.091.021l.087.039A7 7 0 1 0 10 3"/></svg></a>';
	if ($echo) { echo $html; return; }
	return $html;
}

function VideoBubble_admin_page() {
	require VideoBubble_PLUGIN_DIR . 'admin/settings.php';
}

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN AJAX ENDPOINTS  (only reached from VideoBubble_admin_page())
// ─────────────────────────────────────────────────────────────────────────────

function VideoBubble_check_nonce_or_die() {
	$nonce = $_POST['nonce'] ?? $_GET['nonce'] ?? '';
	if (!check_nonce($nonce, 'vb_ajax', VideoBubble_SLUG)) {
		header('Content-Type: application/json');
		echo json_encode(['ok' => false, 'msg' => i18n_r('VideoBubble/lang_Invalid_Nonce')]);
		exit;
	}
}

function VideoBubble_handle_ajax($action) {
	header('Content-Type: application/json');

	switch ($action) {

		case 'list_videos': {
			echo json_encode(['ok' => true, 'rows' => VideoBubble_get_videos()]);
			exit;
		}

		case 'list_uploads': {
			echo json_encode(['ok' => true, 'files' => VideoBubble_list_uploaded_files()]);
			exit;
		}

		case 'upload_file': {
			VideoBubble_check_nonce_or_die();

			if (empty($_FILES['file']['name'])) {
				echo json_encode(['ok' => false, 'msg' => i18n_r('VideoBubble/lang_File_Required')]);
				exit;
			}

			$original  = basename($_FILES['file']['name']);
			$ext       = strtolower(pathinfo($original, PATHINFO_EXTENSION));
			$allowed   = ['mp4', 'webm', 'ogg'];
			$max_bytes = 50 * 1024 * 1024; // 50MB

			if (!in_array($ext, $allowed, true)) {
				echo json_encode(['ok' => false, 'msg' => i18n_r('VideoBubble/lang_Invalid_File_Type')]);
				exit;
			}
			if ($_FILES['file']['size'] > $max_bytes) {
				echo json_encode(['ok' => false, 'msg' => i18n_r('VideoBubble/lang_File_Too_Large')]);
				exit;
			}

			// Folder is optional (blank = uploads root) and may not exist yet.
			$folder     = VideoBubble_sanitize_relpath($_POST['folder'] ?? '');
			$target_dir = VideoBubble_UPLOAD_ROOT . ($folder !== '' ? $folder . '/' : '');

			if (!is_dir($target_dir) && !mkdir($target_dir, 0755, true)) {
				echo json_encode(['ok' => false, 'msg' => i18n_r('VideoBubble/lang_Folder_Create_Failed')]);
				exit;
			}

			// Confirm the resolved directory actually stays inside the uploads root.
			$root_real = realpath(VideoBubble_UPLOAD_ROOT);
			$dir_real  = realpath($target_dir);
			if (!$root_real || !$dir_real || strpos($dir_real, $root_real) !== 0) {
				echo json_encode(['ok' => false, 'msg' => i18n_r('VideoBubble/lang_Invalid_Folder')]);
				exit;
			}

			$safe = VideoBubble_safe_upload_filename($original, $target_dir);
			if (!move_uploaded_file($_FILES['file']['tmp_name'], $target_dir . $safe)) {
				echo json_encode(['ok' => false, 'msg' => i18n_r('VideoBubble/lang_Upload_Failed')]);
				exit;
			}

			$rel = ($folder !== '' ? $folder . '/' : '') . $safe;
			echo json_encode(['ok' => true, 'filename' => $rel, 'url' => VideoBubble_resolve_source_url($rel)]);
			exit;
		}

		case 'get_settings': {
			echo json_encode(['ok' => true, 'settings' => VideoBubble_get_settings()]);
			exit;
		}

		case 'save_settings': {
			VideoBubble_check_nonce_or_die();
			$incoming = json_decode($_POST['settings'] ?? '{}', true) ?: [];
			$saved    = VideoBubble_save_settings($incoming);
			echo json_encode(['ok' => true, 'settings' => $saved]);
			exit;
		}

		case 'save_video': {
			VideoBubble_check_nonce_or_die();

			$id    = (int)($_POST['id'] ?? 0);
			$type  = ($_POST['type'] ?? 'mp4') === 'youtube' ? 'youtube' : 'mp4';
			$label = trim(strip_tags($_POST['label'] ?? ''));

			$db = VideoBubble_db();

			if ($type === 'youtube') {
				$vid_id = VideoBubble_parse_youtube_id($_POST['youtube_url'] ?? '');
				if (!$vid_id) {
					echo json_encode(['ok' => false, 'msg' => i18n_r('VideoBubble/lang_Invalid_YouTube_URL')]);
					exit;
				}
				$source = $vid_id;
			} else {
				// 'video_source' is a path relative to data/uploads/.
				$rel = trim(str_replace('\\', '/', $_POST['video_source'] ?? ''), '/');
				$rel = $rel !== '' ? VideoBubble_sanitize_relpath($rel) : '';

				if ($rel === '' && $id) {
					// Editing, no new selection posted - keep whatever this row already had
					$existing = VideoBubble_get_video($id);
					$rel      = $existing['source'] ?? '';
				}

				if ($rel === '' || !file_exists(VideoBubble_resolve_source_path($rel))) {
					echo json_encode(['ok' => false, 'msg' => i18n_r('VideoBubble/lang_File_Required')]);
					exit;
				}
				$source = $rel;

				// If editing and the file changed, clean up the OLD file.
				if ($id) {
					$existing_row = VideoBubble_get_video($id);
					if ($existing_row && $existing_row['type'] === 'mp4' && $existing_row['source'] && $existing_row['source'] !== $source) {
						if (!VideoBubble_file_in_use($existing_row['source'], $id)) {
							$old_path = VideoBubble_resolve_source_path($existing_row['source']);
							if ($old_path && file_exists($old_path)) @unlink($old_path);
						}
					}
				}
			}

			if ($id) {
				$stmt = $db->prepare("UPDATE vb_videos SET type=:t, source=:s, label=:l WHERE id=:id");
				$stmt->bindValue(':t',  $type,   SQLITE3_TEXT);
				$stmt->bindValue(':s',  $source, SQLITE3_TEXT);
				$stmt->bindValue(':l',  $label,  SQLITE3_TEXT);
				$stmt->bindValue(':id', $id,     SQLITE3_INTEGER);
				$stmt->execute();
			} else {
				$max = $db->querySingle("SELECT MAX(sort_order) FROM vb_videos");
				$stmt = $db->prepare("INSERT INTO vb_videos (type, source, label, sort_order) VALUES (:t,:s,:l,:o)");
				$stmt->bindValue(':t', $type,   SQLITE3_TEXT);
				$stmt->bindValue(':s', $source, SQLITE3_TEXT);
				$stmt->bindValue(':l', $label,  SQLITE3_TEXT);
				$stmt->bindValue(':o', (int)$max + 1, SQLITE3_INTEGER);
				$stmt->execute();
				$id = $db->lastInsertRowID();
			}

			echo json_encode(['ok' => true, 'row' => VideoBubble_get_video($id)]);
			exit;
		}

		case 'delete_video': {
			VideoBubble_check_nonce_or_die();

			$id  = (int)($_POST['id'] ?? 0);
			$row = VideoBubble_get_video($id);
			if (!$row) { echo json_encode(['ok' => false, 'msg' => 'Not found']); exit; }

			if ($row['type'] === 'mp4' && $row['source'] && !VideoBubble_file_in_use($row['source'], $id)) {
				$path = VideoBubble_resolve_source_path($row['source']);
				if ($path && file_exists($path)) @unlink($path);
			}

			$db   = VideoBubble_db();
			$stmt = $db->prepare("DELETE FROM vb_videos WHERE id=:id");
			$stmt->bindValue(':id', $id, SQLITE3_INTEGER);
			$stmt->execute();

			echo json_encode(['ok' => true]);
			exit;
		}

		case 'reorder_videos': {
			VideoBubble_check_nonce_or_die();

			$order = json_decode($_POST['order'] ?? '[]', true) ?: [];
			$db    = VideoBubble_db();
			$db->exec('BEGIN');
			foreach ($order as $i => $vid_id) {
				$stmt = $db->prepare("UPDATE vb_videos SET sort_order=:o WHERE id=:id");
				$stmt->bindValue(':o',  $i, SQLITE3_INTEGER);
				$stmt->bindValue(':id', (int)$vid_id, SQLITE3_INTEGER);
				$stmt->execute();
			}
			$db->exec('COMMIT');

			echo json_encode(['ok' => true]);
			exit;
		}

		default:
			echo json_encode(['ok' => false, 'msg' => 'Unknown action']);
			exit;
	}
}
