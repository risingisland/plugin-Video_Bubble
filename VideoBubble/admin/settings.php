<?php if (!defined('IN_GS')) { die('You cannot load this page directly.'); }

$vb_settings = VideoBubble_get_settings();
$vb_videos   = VideoBubble_get_videos();
$vb_nonce    = get_nonce('vb_ajax', VideoBubble_SLUG);
$vb_plugin_url = VideoBubble_plugin_url();
?>
<div id="vb-admin-app" class="vb-admin-app"
	 data-nonce="<?= htmlspecialchars($vb_nonce) ?>"
	 data-ajax-url="?id=<?= VideoBubble_SLUG ?>"
	 data-plugin-url="<?= htmlspecialchars($vb_plugin_url) ?>">

	<h3><svg xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle;" width="28" height="28" viewBox="0 0 20 20"><path d="M0 0h20v20H0z" fill="none"/><path fill="#416cc9" d="M6 8a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1zm6 1l1.146-1.146a.5.5 0 0 1 .854.353v3.586a.5.5 0 0 1-.854.353L12 11zm-2-7a8 8 0 1 1-3.613 15.14l-.121-.065l-3.645.91a.5.5 0 0 1-.62-.441v-.082l.014-.083l.91-3.644l-.063-.12a8 8 0 0 1-.83-2.887l-.025-.382L2 10a8 8 0 0 1 8-8m0 1a7 7 0 0 0-6.106 10.425a.5.5 0 0 1 .063.272l-.014.094l-.756 3.021l3.024-.754a.5.5 0 0 1 .188-.01l.091.021l.087.039A7 7 0 1 0 10 3"/></svg> <?= i18n_r('VideoBubble/lang_Menu_Title') ?></h3>
	<p class="vb-sub"><?= i18n_r('VideoBubble/lang_Template_Tag_Hint') ?> <code>&lt;?php get_vbubble(); ?&gt;</code></p>

	<div id="vb-toast" class="vb-toast" hidden></div>

	<div class="vb-tabs">
		<button type="button" class="vb-tab-btn vb-active" data-tab="videos"><?= i18n_r('VideoBubble/lang_Tab_Videos') ?></button>
		<button type="button" class="vb-tab-btn" data-tab="settings"><?= i18n_r('VideoBubble/lang_Tab_Settings') ?></button>
	</div>

	<!-- ===================== VIDEOS TAB ===================== -->
	<section id="vb-tab-videos" class="vb-tab-panel vb-active">

		<div class="vb-panel">
			<h4 id="vb-form-heading"><?= i18n_r('VideoBubble/lang_Add_Video') ?></h4>
			<form id="vb-video-form" class="vb-form" autocomplete="off">
				<input type="hidden" name="id" value="0">

				<div class="vb-field">
					<label><?= i18n_r('VideoBubble/lang_Video_Type') ?></label>
					<div class="vb-radio-row">
						<label><input type="radio" name="type" value="mp4" checked> MP4</label>
						<label><input type="radio" name="type" value="youtube"> YouTube</label>
					</div>
				</div>

				<div class="vb-field" data-type-field="mp4">
					<label><?= i18n_r('VideoBubble/lang_Video_File') ?></label>
					<div class="vb-radio-row">
						<label><input type="radio" name="source_mode" value="upload" checked> <?= i18n_r('VideoBubble/lang_Upload_New') ?></label>
						<label><input type="radio" name="source_mode" value="existing"> <?= i18n_r('VideoBubble/lang_Choose_Existing') ?></label>
					</div>

					<div data-source-mode="upload">
						<input type="file" name="file" accept=".mp4,.webm,.ogg">
						<p class="vb-hint"><?= i18n_r('VideoBubble/lang_Upload_Filename_Hint') ?></p>
						<p class="vb-hint" data-existing-file></p>
					</div>

					<div data-source-mode="existing" hidden>
						<div id="vb-existing-files" class="vb-existing-files"></div>
						<p id="vb-existing-empty" class="vb-hint" hidden><?= i18n_r('VideoBubble/lang_No_Existing_Files') ?></p>
						<input type="hidden" name="existing_file" value="">
					</div>
				</div>

				<div class="vb-field" data-type-field="youtube" hidden>
					<label><?= i18n_r('VideoBubble/lang_YouTube_URL') ?></label>
					<input type="text" name="youtube_url" placeholder="https://www.youtube.com/watch?v=... or video ID">
				</div>

				<div class="vb-field">
					<label><?= i18n_r('VideoBubble/lang_Video_Label') ?></label>
					<input type="text" name="label" maxlength="60" placeholder="<?= i18n_r('VideoBubble/lang_Video_Label_Placeholder') ?>">
					<p class="vb-hint"><?= i18n_r('VideoBubble/lang_Video_Label_Hint') ?></p>
				</div>

				<div class="vb-actions">
					<button type="submit" id="vb-submit-video" class="button primary"><?= i18n_r('VideoBubble/lang_Save_Video') ?></button>
					<button type="button" id="vb-cancel-edit" class="button" hidden><?= i18n_r('VideoBubble/lang_Cancel') ?></button>
				</div>
			</form>
		</div>

		<div class="vb-panel">
			<h4><?= i18n_r('VideoBubble/lang_Video_List') ?></h4>
			<p class="vb-hint"><?= i18n_r('VideoBubble/lang_Drag_Hint') ?></p>
			<ul id="vb-video-list" class="vb-video-list"></ul>
			<p id="vb-video-empty" class="vb-hint" hidden><?= i18n_r('VideoBubble/lang_No_Videos') ?></p>
		</div>
	</section>

	<!-- ===================== SETTINGS TAB ===================== -->
	<section id="vb-tab-settings" class="vb-tab-panel">
		<form id="vb-settings-form" class="vb-form vb-panel">

			<div class="vb-field">
				<label for="vb-greet_text"><?= i18n_r('VideoBubble/lang_Greet_Text') ?></label>
				<input type="text" id="vb-greet_text" name="greet_text" maxlength="60" value="<?= htmlspecialchars($vb_settings['greet_text']) ?>">
			</div>

			<div class="vb-field">
				<label for="vb-position"><?= i18n_r('VideoBubble/lang_Position') ?></label>
				<select id="vb-position" name="position">
					<option value="right" <?= $vb_settings['position']==='right'?'selected':'' ?>><?= i18n_r('VideoBubble/lang_Position_Right') ?></option>
					<option value="left" <?= $vb_settings['position']==='left'?'selected':'' ?>><?= i18n_r('VideoBubble/lang_Position_Left') ?></option>
				</select>
			</div>

			<div class="vb-field">
				<label for="vb-accent_color"><?= i18n_r('VideoBubble/lang_Accent_Color') ?></label>
				<input type="color" id="vb-accent_color" name="accent_color" value="<?= htmlspecialchars($vb_settings['accent_color']) ?>">
			</div>

			<div class="vb-field-row">
				<div class="vb-field">
					<label for="vb-more_info_label"><?= i18n_r('VideoBubble/lang_More_Info_Label') ?></label>
					<input type="text" id="vb-more_info_label" name="more_info_label" maxlength="40" value="<?= htmlspecialchars($vb_settings['more_info_label']) ?>">
				</div>
				<div class="vb-field">
					<label for="vb-more_info_url"><?= i18n_r('VideoBubble/lang_More_Info_URL') ?></label>
					<input type="text" id="vb-more_info_url" name="more_info_url" value="<?= htmlspecialchars($vb_settings['more_info_url']) ?>">
				</div>
				<div class="vb-field">
					<label for="vb-more_info_target"><?= i18n_r('VideoBubble/lang_More_Info_Target') ?></label>
					<select id="vb-more_info_target" name="more_info_target">
						<option value="_blank" <?= $vb_settings['more_info_target']==='_blank'?'selected':'' ?>><?= i18n_r('VideoBubble/lang_Target_New_Tab') ?></option>
						<option value="_self" <?= $vb_settings['more_info_target']==='_self'?'selected':'' ?>><?= i18n_r('VideoBubble/lang_Target_Same_Tab') ?></option>
					</select>
				</div>
			</div>
			<p class="vb-hint"><?= i18n_r('VideoBubble/lang_More_Info_Hint') ?></p>

			<div class="vb-field">
				<label class="vb-checkbox"><input type="checkbox" name="autoplay_muted" <?= $vb_settings['autoplay_muted']?'checked':'' ?>> <?= i18n_r('VideoBubble/lang_Autoplay_Muted') ?></label>
				<label class="vb-checkbox"><input type="checkbox" name="loop_bubble" <?= $vb_settings['loop_bubble']?'checked':'' ?>> <?= i18n_r('VideoBubble/lang_Loop_Bubble') ?></label>
				<label class="vb-checkbox"><input type="checkbox" name="pause_on_tab_switch" <?= $vb_settings['pause_on_tab_switch']?'checked':'' ?>> <?= i18n_r('VideoBubble/lang_Pause_On_Tab_Switch') ?></label>
				<label class="vb-checkbox"><input type="checkbox" name="show_close_button" <?= $vb_settings['show_close_button']?'checked':'' ?>> <?= i18n_r('VideoBubble/lang_Show_Close_Button') ?></label>
				<label class="vb-checkbox"><input type="checkbox" name="use_nocookie_youtube" <?= $vb_settings['use_nocookie_youtube']?'checked':'' ?>> <?= i18n_r('VideoBubble/lang_Use_Nocookie') ?></label>
			</div>

			<div class="vb-field-row">
				<div class="vb-field">
					<label for="vb-remember_closed"><?= i18n_r('VideoBubble/lang_Remember_Closed') ?></label>
					<select id="vb-remember_closed" name="remember_closed">
						<option value="off" <?= $vb_settings['remember_closed']==='off'?'selected':'' ?>><?= i18n_r('VideoBubble/lang_Remember_Off') ?></option>
						<option value="session" <?= $vb_settings['remember_closed']==='session'?'selected':'' ?>><?= i18n_r('VideoBubble/lang_Remember_Session') ?></option>
						<option value="local" <?= $vb_settings['remember_closed']==='local'?'selected':'' ?>><?= i18n_r('VideoBubble/lang_Remember_Local') ?></option>
					</select>
				</div>
				<div class="vb-field">
					<label for="vb-show_delay_seconds"><?= i18n_r('VideoBubble/lang_Show_Delay') ?></label>
					<input type="number" id="vb-show_delay_seconds" name="show_delay_seconds" min="0" max="60" value="<?= (int)$vb_settings['show_delay_seconds'] ?>">
				</div>
			</div>

			<div class="vb-field">
				<label for="vb-video_switcher_style"><?= i18n_r('VideoBubble/lang_Switcher_Style') ?></label>
				<select id="vb-video_switcher_style" name="video_switcher_style">
					<option value="next" <?= $vb_settings['video_switcher_style']==='next'?'selected':'' ?>><?= i18n_r('VideoBubble/lang_Switcher_Next') ?></option>
					<option value="all" <?= $vb_settings['video_switcher_style']==='all'?'selected':'' ?>><?= i18n_r('VideoBubble/lang_Switcher_All') ?></option>
				</select>
				<p class="vb-hint"><?= i18n_r('VideoBubble/lang_Switcher_Hint') ?></p>
			</div>

			<div class="vb-field">
				<label for="vb-custom_css"><?= i18n_r('VideoBubble/lang_Custom_CSS') ?></label>
				<textarea id="vb-custom_css" name="custom_css" rows="6" placeholder=".vb-wrapper { }"><?= htmlspecialchars($vb_settings['custom_css']) ?></textarea>
			</div>

			<div class="vb-actions">
				<button type="submit" id="vb-save-settings" class="button primary"><?= i18n_r('VideoBubble/lang_Save_Settings') ?></button>
			</div>
		</form>
	</section>

</div>

<script type="application/json" id="vb-initial-videos"><?= json_encode($vb_videos, JSON_UNESCAPED_SLASHES) ?></script>
<script src="<?= htmlspecialchars($vb_plugin_url) ?>js/admin.js"></script>
<link rel="stylesheet" href="<?= htmlspecialchars($vb_plugin_url) ?>css/admin.css">
