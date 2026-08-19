# Video Bubble

A floating video greeting bubble for [GetSimpleCMS-CE](https://github.com/GetSimpleCMS-CE/GetSimpleCMS-CE). Supports mp4 and YouTube videos, multiple videos with a switcher, and a fully vanilla-JS front end — no jQuery required.

**Version:** 1.0
**Author:** risingisland ([ko-fi.com/ericmontgomery](https://ko-fi.com/ericmontgomery))
**Requires:** GetSimpleCMS-CE, PHP with SQLite3 extension

---

## Features

- Small circular autoplay-muted preview bubble that expands into a full video panel on click
- **mp4** (uploaded) and **YouTube** (privacy-enhanced `youtube-nocookie.com` embed) videos, freely mixed in one list
- Multiple videos with either a single "Next" button or a full list of per-video buttons
- Admin file browser for reusing already-uploaded mp4 files instead of re-uploading
- Optional "More Info" call-to-action button (opens in a new tab or the same tab)
- Configurable position, accent color, greet text, show-delay, and a "remember dismissed" option (per-session or persistent)
- Keyboard accessible (Enter/Space to open, Escape to close), click-outside-to-collapse, `prefers-reduced-motion` support
- A reopen link/button for bringing back a dismissed bubble (e.g. from your footer)

---

## Installation

1. Copy `VideoBubble.php` and the `VideoBubble/` folder into your site's `plugins/` directory as siblings:

   ```
   plugins/
     VideoBubble.php
     VideoBubble/
       admin/settings.php
       css/admin.css
       css/vbubble.css
       js/admin.js
       js/vbubble.js
       lang/en_US.php
       lang/es_ES.php
   ```

2. Activate **Video Bubble** from the GetSimpleCMS-CE admin plugin list.
3. Open the plugin's admin page (sidebar → Video Bubble) to add videos and configure settings.
4. Add the template tag (see below) to your theme.

The plugin creates its own SQLite database at `data/other/VideoBubble.db` and a public upload folder at `data/uploads/vbubble/` automatically on activation.

---

## Usage

### Show the bubble

Add this to a theme file (e.g. `header.php` or right before `</body>` in your footer template):

```php
<?php get_vbubble(); ?>
```

This queues its own CSS/JS and renders nothing if no videos are configured yet, so it's safe to leave in your theme permanently.

### Let visitors bring back a dismissed bubble

If a visitor closes the bubble (especially with "Remember Dismissal" enabled in settings), it stays hidden until they reload with storage cleared — or you give them a way back:

```php
<?php get_vbubble_reopen_link(); ?>
```

Renders `<a href="#" data-vb-reopen>Show video again</a>` (label is translatable). Pass your own label if you'd like:

```php
<?php get_vbubble_reopen_link('Watch the intro again'); ?>
```

Pass `false` as the second argument to get the HTML back as a string instead of echoing it:

```php
<?php $link = get_vbubble_reopen_link('Watch again', false); ?>
```

You can also wire up your own trigger element with the `data-vb-reopen` attribute, or call `VideoBubble.reopen()` directly from your own JavaScript.

---

## Admin settings reference

**Videos tab**
| Field | Notes |
|---|---|
| Video Type | mp4 or YouTube |
| Video File | Upload a new file, or choose one already uploaded (original filenames are kept; a numeric suffix is only added on a name collision) |
| YouTube URL / Video ID | Accepts a full URL (`watch?v=`, `youtu.be/`, `/embed/`, `/shorts/`) or a bare 11-character video ID |
| Label | Shown on the "Next Video" button(s); optional |
| Drag to reorder | The first video in the list plays first |

**Settings tab**
| Setting | Options | Default |
|---|---|---|
| Greet Text | free text | `Hey 👋` |
| Position | bottom-left / bottom-right | bottom-right |
| Accent Color | color picker | `#7432ff` |
| More Info Button | label + URL + link target (new tab / same tab) | off (blank) |
| Autoplay bubble preview (muted) | on/off | on |
| Loop bubble preview | on/off | on |
| Pause when tab is inactive | on/off | on |
| Show close (dismiss) button | on/off | on |
| Use youtube-nocookie.com | on/off | on |
| Remember Dismissal | never / this session / permanently | never |
| Show Delay (seconds) | 0–60 | 0 |
| Multiple Videos Display | single "Next" button / a button per video | single "Next" button |
| Custom CSS | free-form CSS, scoped by you (e.g. `.vb-wrapper { }`) | blank |
