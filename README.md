# Simple Popup Manager

A lightweight, production-ready WordPress plugin for creating and scheduling simple website popups, targeted to specific WordPress Pages.

## What the Plugin Does

Simple Popup Manager lets administrators create multiple popup entries using the native WordPress editor, target each popup to either **all WordPress Pages** or **one specific WordPress Page**, and optionally schedule when each popup is visible on the front end. When more than one popup is eligible for the same page, the plugin automatically selects the most recently published one and displays it as an accessible modal dialog.

The plugin intentionally does **not** include analytics, cookies, "show once" dismissal, frequency capping, exit-intent, device/role targeting, or any AJAX-based popup selection. It is designed to be a small, reusable building block rather than a full marketing platform.

## Requirements

- WordPress 6.0+
- PHP 8.0+
- No third-party libraries, page builders, or jQuery required

## Installation

1. Upload the `simple-popup-manager` folder to `/wp-content/plugins/`, or upload the plugin ZIP via **Plugins → Add New → Upload Plugin**.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. A new **Popups** menu item will appear in the WordPress admin sidebar.

## How to Create a Popup

1. Go to **Popups → Add New**.
2. Enter a **Title**. This title is for admin identification only — it is not automatically displayed inside the popup itself.
3. Enter your popup message using the standard WordPress editor. Standard formatting and supported embeds are rendered normally on the front end.
4. In the **Popup Settings** box (right-hand sidebar):
   - Choose a **Display Location** (see below).
   - Optionally set a **Start Date/Time** and/or **End Date/Time** (see below).
5. **Publish** the popup. Popups remain in **Draft** until you explicitly publish them — scheduling never changes this status automatically.

## Display Targeting

Two targeting modes are available:

- **All Pages** — the popup is eligible on every singular WordPress Page (`is_page()`). This does **not** include blog posts, archives, search results, the blog index, or other post types.
- **Specific Page** — the popup is eligible only on the one WordPress Page you select from the dropdown (published pages only). The page is stored by its post ID.

If a page selected for "Specific Page" targeting is later deleted, trashed, or unpublished, the popup fails safe: it stops matching entirely rather than becoming a site-wide popup.

## Start/End Scheduling

Each popup can optionally have a **Start Date/Time** and an **End Date/Time**. These fields control **front-end visibility only** — they never change the underlying WordPress Publish/Draft post status. A popup you leave in Draft will never appear on the front end regardless of its dates, and a Published popup whose schedule has expired stays Published (visible in the admin list) but is simply excluded from front-end selection.

Eligibility follows this rule, evaluated in the WordPress site's configured timezone (**Settings → General → Timezone**), not the visitor's browser timezone or the PHP server's timezone:

```
start <= current time < end
```

The end date is an **exclusive** boundary, so a popup stops displaying exactly at its end time rather than one second after.

| Start | End | Behavior |
|---|---|---|
| (none) | (none) | Active immediately after publishing, indefinitely. |
| Future | (none) | Becomes active once the start time is reached. |
| (none) | Future | Active immediately, stops at the end time. |
| Set | Set | Active only within that window. |
| Past | Past | Published but expired — excluded from selection. |

If both a start and end date are supplied and the end date is not later than the start date, the end date is rejected during save and an admin notice explains why. Empty or malformed stored dates are always treated safely as "no boundary" and never cause a front-end PHP error.

## Popup Priority (Multiple Matching Popups)

Only one popup displays per page view. When more than one popup could match the current page, the plugin:

1. Excludes any popup that isn't Published.
2. Excludes any popup whose targeting doesn't match the current page.
3. Excludes any popup whose start date hasn't arrived yet.
4. Excludes any popup that has reached or passed its end date.
5. Sorts the remaining popups by their WordPress **publish date**, newest first.
6. Selects only the single newest match.

Priority is based on the post's publish date — not its start date.

## Closing Behavior

The popup can be closed by:

- Activating the close button.
- Pressing the **Escape** key.
- Clicking on the overlay/backdrop outside the popup dialog.

Clicking inside the popup content does not close it. Closing a popup only affects the current page view — there is no cookie, `localStorage`, `sessionStorage`, or other persistence, and no "show once" behavior in this version.

## Accessibility

The popup is implemented as an accessible modal dialog:

- `role="dialog"` and `aria-modal="true"` on the dialog element, with `aria-labelledby` pointing at a visually-hidden heading built from the popup's admin title (for an accessible name without displaying the title in the visible content).
- Keyboard focus moves into the dialog when it opens and is returned to the previously focused element when it closes.
- Tab/Shift+Tab cycle (contain) focus within the dialog while it is open.
- Escape closes the dialog.
- Visible focus indicators are preserved (not suppressed) via `:focus-visible` styling.
- Popup content uses relative units and remains usable at high browser zoom levels.
- The fade-in transition is skipped entirely for visitors with `prefers-reduced-motion: reduce`.

## Plugin Architecture

```text
simple-popup-manager/
├── simple-popup-manager.php        Plugin bootstrap: constants, hooks, activation/deactivation
├── includes/
│   ├── class-popup-post-type.php   Registers the `spm_popup` custom post type
│   ├── class-popup-admin.php       Meta box, save/validation, admin list columns
│   ├── class-popup-query.php       Centralized eligibility + selection logic
│   └── class-popup-frontend.php    Conditional asset loading + front-end markup
├── assets/
│   ├── css/popup.css               Popup styles (overlay, dialog, focus states, reduced motion)
│   └── js/popup.js                 Vanilla JS: open/close, focus trap, Escape, overlay click
└── README.md
```

Responsibilities are kept separate on purpose: post type registration, admin/meta handling, eligibility/selection, and front-end rendering each live in their own class, and no other class duplicates the eligibility rules defined in `SPM_Popup_Query`.

## Development Notes

- Popup meta is stored with a consistent `_spm_` prefix: `_spm_target`, `_spm_target_page_id`, `_spm_start_date`, `_spm_end_date`.
- Dates are stored as `Y-m-d H:i:s` strings, always interpreted in the site's configured timezone via `wp_timezone()` / `current_datetime()`.
- Popup selection runs once per request (`SPM_Popup_Query::get_active_popup()` caches its result in a static property) so both the asset-loading check and the footer render reuse the same query instead of running it twice.
- No WP-Cron jobs are registered; scheduling is evaluated live against the current site time on each request.
- No AJAX requests are used to determine which popup displays — selection happens entirely in PHP during normal page rendering.
- Popup content is passed through the standard `the_content` filter so editor formatting/embeds behave normally; all dynamically generated attributes, IDs, and URLs are escaped with the appropriate `esc_*` functions.
- All admin-submitted values (targeting mode, page ID, dates) are sanitized, validated against an allowlist/format, and capability/nonce-checked before saving.

## Testing Instructions

### Automated / Static Checks Performed

- All PHP files were linted with `php -l` and contain no syntax errors.
- The bundled JavaScript was checked with `node --check` and contains no syntax errors.
- Code was reviewed against every rule in the functional specification (targeting, scheduling, priority, accessibility, security, performance, asset loading).

These checks confirm the code parses correctly and follows the intended logic on inspection. **They are not a substitute for running the plugin inside an actual WordPress installation**, since PHP linting cannot execute WordPress hooks, database queries, or browser-side behavior.

### Manual Testing Checklist (requires a real WordPress install)

**Publishing & Drafts**
- [ ] Create a popup, leave it as a Draft — confirm it never appears on the front end.
- [ ] Publish the popup — confirm it can now appear (subject to targeting/scheduling).

**Targeting**
- [ ] Set a popup to "All Pages" — confirm it appears on multiple different Pages.
- [ ] Confirm an "All Pages" popup does **not** appear on posts, the blog index, archives, or search results.
- [ ] Set a popup to "Specific Page" — confirm it appears only on that page and nowhere else.
- [ ] Delete or unpublish the page targeted by a "Specific Page" popup — confirm the popup no longer displays anywhere (fails safe, does not become site-wide).

**Scheduling**
- [ ] No start/end dates — popup is active immediately after publishing.
- [ ] Future start date — popup is not visible until that time is reached.
- [ ] Past end date — popup is Published in the admin but does not display on the front end.
- [ ] Start date in the past, end date in the future — popup is active.
- [ ] Enter an end date equal to or earlier than the start date — confirm the admin notice appears and the end date is rejected.
- [ ] Confirm dates are evaluated using the site's **Settings → General** timezone, not the browser's local timezone.

**Multiple-Popup Priority**
- [ ] Publish two popups that both target the same page and are both currently eligible — confirm only the more recently *published* one displays.

**Front-End Rendering & Behavior**
- [ ] Confirm popup CSS/JS only load on pages where a popup will actually render (inspect page source / network tab on a page with no eligible popup).
- [ ] Confirm the overlay, dialog, and close button render correctly.
- [ ] Close via the close button.
- [ ] Close via the Escape key.
- [ ] Close via clicking the overlay outside the dialog.
- [ ] Confirm clicking inside the dialog content does not close the popup.

**Accessibility**
- [ ] Confirm focus moves into the popup when it opens.
- [ ] Tab through the popup and confirm focus stays contained within it.
- [ ] Confirm focus returns to the triggering element after closing.
- [ ] Confirm visible focus outlines are present while tabbing.
- [ ] Test at 200%+ browser zoom to confirm the popup remains usable.
- [ ] Enable "reduce motion" at the OS level and confirm the popup's entrance transition is skipped.

**Responsive**
- [ ] Confirm the popup is usable and readable on small (mobile-width) viewports.

## What Could Not Be Automatically Tested

This environment does not include a running WordPress instance, MySQL database, or browser, so the following require manual verification inside a real WordPress site:

- Actual rendering and execution of PHP against WordPress core hooks (`init`, `save_post`, `wp_footer`, `wp_enqueue_scripts`, etc.).
- Database reads/writes for post meta (`update_post_meta`, `get_post_meta`, `WP_Query`).
- Live timezone behavior against a specific site's **Settings → General** configuration.
- Browser-based JavaScript behavior: focus trapping, Escape handling, overlay-click detection, and `prefers-reduced-motion` response.
- Visual/responsive appearance across real themes, screen sizes, and browser zoom levels.
- Screen reader behavior (e.g., with NVDA, JAWS, or VoiceOver).
