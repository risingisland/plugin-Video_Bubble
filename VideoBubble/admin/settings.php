<?php if (!defined('IN_GS')) { die('You cannot load this page directly.'); }

$vb_settings = VideoBubble_get_settings();
$vb_videos   = VideoBubble_get_videos();
$vb_nonce    = VideoBubble_get_csrf_token();
$vb_plugin_url = VideoBubble_plugin_url();
?>
<div id="vb-admin-app" class="vb-admin-app"
	 data-nonce="<?= htmlspecialchars($vb_nonce) ?>"
	 data-ajax-url="?id=<?= VideoBubble_SLUG ?>"
	 data-plugin-url="<?= htmlspecialchars($vb_plugin_url) ?>">

	<h3><svg xmlns="http://www.w3.org/2000/svg" style="vertical-align:middle;" width="28" height="28" viewBox="0 0 20 20"><path d="M0 0h20v20H0z" fill="none"/><path fill="#416cc9" d="M6 8a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1zm6 1l1.146-1.146a.5.5 0 0 1 .854.353v3.586a.5.5 0 0 1-.854.353L12 11zm-2-7a8 8 0 1 1-3.613 15.14l-.121-.065l-3.645.91a.5.5 0 0 1-.62-.441v-.082l.014-.083l.91-3.644l-.063-.12a8 8 0 0 1-.83-2.887l-.025-.382L2 10a8 8 0 0 1 8-8m0 1a7 7 0 0 0-6.106 10.425a.5.5 0 0 1 .063.272l-.014.094l-.756 3.021l3.024-.754a.5.5 0 0 1 .188-.01l.091.021l.087.039A7 7 0 1 0 10 3"/></svg> <?= i18n_r('VideoBubble/lang_Menu_Title') ?></h3>
	<p class="vb-sub"><?= i18n_r('VideoBubble/lang_Template_Tag_Hint') ?> <code>&lt;?php get_vbubble(); ?&gt;</code></p>

	<div id="vb-toast" class="vb-toast" hidden></div>

	<div class="vb-modal-bg" id="vb-fb-bg" hidden>
		<div class="vb-modal vb-modal-lg">
			<div class="vb-modal-head">
				<span><?= i18n_r('VideoBubble/lang_Select_Video') ?></span>
				<button type="button" class="vb-icon-btn" onclick="vbFbClose()">&#10005;</button>
			</div>
			<div class="vb-modal-body">
				<div class="vb-fb-tabs">
					<button type="button" class="vb-fb-tab vb-active" onclick="vbFbTab('browse', this)"><?= i18n_r('VideoBubble/lang_Browse_Uploads') ?></button>
					<button type="button" class="vb-fb-tab" onclick="vbFbTab('upload', this)"><?= i18n_r('VideoBubble/lang_Upload_New') ?></button>
				</div>

				<div id="vb-fb-browse">
					<div class="vb-fb-crumb" id="vb-fb-crumb"></div>
					<div class="vb-fb-search-row">
						<input type="text" id="vb-fb-search" placeholder="<?= i18n_r('VideoBubble/lang_Filter_Videos') ?>" oninput="vbFbFilter()">
					</div>
					<div class="vb-fb-grid" id="vb-fb-grid">
						<div class="vb-hint"><?= i18n_r('VideoBubble/lang_Loading_Videos') ?></div>
					</div>
				</div>

				<div id="vb-fb-upload" hidden>
					<div class="vb-field">
						<label for="vb-fb-folder"><?= i18n_r('VideoBubble/lang_Upload_Folder') ?></label>
						<input type="text" id="vb-fb-folder" placeholder="<?= i18n_r('VideoBubble/lang_Upload_Folder_Placeholder') ?>">
						<p class="vb-hint"><?= i18n_r('VideoBubble/lang_Upload_Folder_Hint') ?></p>
					</div>
					<div class="vb-fb-upload-area" id="vb-fb-dropzone">
						<p><?= i18n_r('VideoBubble/lang_Drop_Here') ?></p>
						<label class="button vb-fb-upload-btn-label" for="vb-fb-file-input"><?= i18n_r('VideoBubble/lang_Choose_File') ?></label>
						<input type="file" id="vb-fb-file-input" accept=".mp4,.webm,.ogg" hidden>
						<p class="vb-hint"><?= i18n_r('VideoBubble/lang_Upload_Hint') ?></p>
					</div>
					<p id="vb-fb-upload-progress" class="vb-hint" hidden></p>
				</div>
			</div>
		</div>
	</div>

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
					<div class="vb-picker-row">
						<button type="button" id="vb-pick-video-btn" class="button" onclick="vbFbOpen()"><?= i18n_r('VideoBubble/lang_Choose_Video') ?></button>
						<span id="vb-picked-file" class="vb-hint"><?= i18n_r('VideoBubble/lang_No_File_Selected') ?></span>
					</div>
					<input type="hidden" name="video_source" id="vb-video-source" value="">
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
	
	<footer>
		<span class="donate">Support my plugin development: <a href="https://ko-fi.com/ericmontgomery" target="_blank" class="donate"><svg xmlns="http://www.w3.org/2000/svg" width="1.5em" height="1.5em" viewBox="0 0 36 36"><path d="M0 0h36v36H0z" fill="none"/><ellipse cx="18" cy="30.54" fill="#3b88c3" rx="18" ry="5.36"/><path fill="#88c9f9" d="M33.812 28.538c0 1.616-2.5 2.587-14.482 2.587c-10.925 0-13.612-.971-13.612-2.587s5.683-2.926 13.612-2.926s14.482 1.31 14.482 2.926"/><path fill="#f4900c" d="M7 28.25c0-1 1-5 11-5c12 0 15 4 15 5s0 2-14 2c-12 0-12-1-12-2"/><circle cx="7" cy="6.25" r="5" fill="#ffcc4d"/><circle cx="7" cy="6.25" r="4" fill="#ffac33"/><path fill="#c1694f" d="M26.949 14.467a21 21 0 0 0-.524-3.459a22 22 0 0 0-1.001-3.158c-.377-.929-.674-1.46-.674-1.46l-2.583.333s.298.622.621 1.841c.131.495.265 1.09.386 1.784a26 26 0 0 1 .343 2.948q.036.555.053 1.158c.004.146.011.287.013.437c.007.469-.026.946-.084 1.422q-.081.644-.223 1.279a19 19 0 0 1-.479 1.77c-.149.46-.308.901-.468 1.316a35 35 0 0 1-.707 1.687c-.358.798-.622 1.33-.622 1.441c0 .351.25 1.007 2 1.444c2.736.684 3-.444 3-.444s.174-.528.376-1.567c.1-.513.206-1.147.303-1.915q.088-.689.158-1.513a44 44 0 0 0 .157-3.478c.002-.176.006-.346.006-.527c0-.459-.021-.903-.051-1.339"/><path fill="#d99e82" d="M24.324 21.362c-1.003-.175-1.643-.467-1.994-.686a35 35 0 0 1-.707 1.687c.655.356 1.487.64 2.389.796a9 9 0 0 0 1.523.136c.296 0 .574-.023.841-.057c.1-.513.206-1.147.303-1.915c-.318.113-1.107.257-2.355.039m.758-3.342c-.782-.077-1.383-.249-1.805-.43a19 19 0 0 1-.479 1.77a8.4 8.4 0 0 0 2.106.476q.462.045.908.045c.359 0 .7-.028 1.026-.071q.074-.857.118-1.863c-.45.098-1.08.152-1.874.073m.221-1.524c.588 0 1.165-.059 1.692-.163c.001-.176.005-.346.005-.527c0-.458-.021-.902-.051-1.339c-.378.099-.919.194-1.682.205c-.775-.003-1.32-.111-1.698-.219c.004.146.011.287.013.437c.007.469-.026.946-.084 1.422a9 9 0 0 0 1.76.184zm-.178-3.273a8.7 8.7 0 0 0 1.648-.418a22 22 0 0 0-.347-1.797a6.4 6.4 0 0 1-1.56.409a6.5 6.5 0 0 1-1.522.055q.109.838.173 1.823c.109.004.207.021.319.021c.42 0 .857-.031 1.289-.093m.909-3.658a22 22 0 0 0-.61-1.715a6.7 6.7 0 0 1-1.242.471a6.6 6.6 0 0 1-1.395.243c.131.495.265 1.09.386 1.784a9 9 0 0 0 1.497-.269a9 9 0 0 0 1.364-.514"/><path fill="#3e721d" d="M34.549 2.943c-.032-.042-3.202-4.283-7.313-2.423c-1.847.835-3.038 2.2-3.469 3.963c-.804-1.125-2.026-1.839-3.644-2.127c-4.421-.788-7.092 4.123-7.118 4.172a.174.174 0 0 0 .256.224c.02-.015 2.037-1.427 5.396-.828a97 97 0 0 1 5.864 1.217a.18.18 0 0 0 .172-.051c.022-.024 2.303-2.475 4.4-3.423c3.104-1.404 5.221-.472 5.24-.462a.176.176 0 0 0 .216-.262"/><path fill="#5c913b" d="M31.129 5.401c-3.134-1.655-5.57-.831-7.083.174c-1.813-.351-5.201-.357-8.072 3.324c-3.525 4.518-.433 10.152-.402 10.209c.031.056.09.089.153.089l.021-.001a.18.18 0 0 0 .147-.128c.011-.039 1.097-3.967 3.843-7.27c1.501-1.805 3.293-3.474 4.554-4.573c1.27.211 3.136.678 4.969 1.702c3.573 1.998 5.212 5.379 5.228 5.413c.032.066.101.112.177.099a.17.17 0 0 0 .151-.134c.014-.063 1.392-6.222-3.686-8.904"/></svg> RisingIsland</a></span>
	</footer>

</div>

<script type="application/json" id="vb-initial-videos"><?= json_encode($vb_videos, JSON_UNESCAPED_SLASHES) ?></script>
<script src="<?= htmlspecialchars($vb_plugin_url) ?>js/admin.js"></script>
<link rel="stylesheet" href="<?= htmlspecialchars($vb_plugin_url) ?>css/admin.css">
