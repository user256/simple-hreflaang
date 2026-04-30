# Review Remediation Plan

This file captures a proposed plan only. It does not make any plugin changes.

## Scope

Focus on the three review items called out in `review.md`:

1. Guideline 11: avoid hijacking the admin dashboard.
2. Use WordPress enqueue APIs for JS and CSS.
3. Use prefixes consistently for declarations, globals, and stored data.

## Current State Summary

Based on the current codebase:

- The admin settings screen is registered under `Settings > CannyForge Hreflang` in `cannyforge-hreflang/includes/class-cannyforge-hreflang-settings.php`.
- The settings page currently outputs inline `<style>` and `<script>` blocks directly from `render_page()`.
- A rewrite-flush success message is attached through `admin_notices` during settings handling.
- Stored data already appears partially prefixed:
  - option: `simple_hreflang_settings`
  - post meta: `_simple_hreflang_group`, `_simple_hreflang_lang`, `_simple_hreflang_region`, `_simple_hreflang_is_default`
- PHP classes, constants, functions, AJAX actions, and DOM ids/classes also use the `simple_hreflang` / `Simple_Hreflang` prefix pattern, but this should be audited end-to-end.

## Plan

### 1. Guideline 11: Limit admin notices and plugin UI scope

Goal: ensure plugin UI and feedback only appear on this plugin’s own admin surfaces and only when relevant.

Proposed work:

- Audit every admin-facing output:
  - `admin_notices`
  - settings page banners/messages
  - metabox UI
  - any redirect or activation-time messaging
- Replace broad notice behavior with page-scoped feedback where practical.
- For the rewrite flush flow, avoid a generic dashboard-level notice pattern if it can appear outside the plugin page lifecycle.
- Prefer rendering success/error feedback inside the plugin’s own settings page, or only on the exact matching screen.
- Confirm the plugin does not add nags, upgrade prompts, or persistent notices on unrelated admin screens.
- Verify AJAX success/error messaging remains local to the settings page UI and does not spill into global admin notices.

Implementation notes:

- Introduce an explicit screen check using the current admin page hook or `get_current_screen()` where needed.
- Keep any remaining notices dismissible, minimal, and event-driven.

Acceptance criteria:

- No plugin notice is shown on Dashboard, Posts, Pages, Plugins, or any unrelated admin screen.
- Feedback for actions like rewrite flush is visible only when the user is interacting with this plugin.

### 2. Move JS/CSS loading to enqueue APIs

Goal: remove direct `<script>` and `<style>` output and load assets through WordPress APIs.

Proposed work:

- Extract the inline JavaScript from `Simple_Hreflang_Settings::render_page()` into an admin script file.
- Extract the inline CSS into an admin stylesheet file.
- Register and enqueue both only on this plugin’s settings screen.
- Pass dynamic PHP data to JavaScript using WordPress APIs instead of embedding large JS blobs directly in the page.
- Keep truly tiny dynamic fragments inline only if justified, and attach them via `wp_add_inline_script()` or `wp_add_inline_style()`.
- Review the metabox screen as well to determine whether it also needs its own enqueued assets.

Implementation notes:

- Use `admin_enqueue_scripts`.
- Gate loading by the plugin page hook suffix so assets are not loaded across wp-admin.
- Use `wp_register_script()`, `wp_enqueue_script()`, `wp_register_style()`, and `wp_enqueue_style()`.
- Use `wp_localize_script()` or a small config object via `wp_add_inline_script()` for:
  - AJAX URL
  - nonces
  - translated strings
  - group/language data currently emitted by PHP

Acceptance criteria:

- No raw `<script>` or `<style>` tags remain in plugin-rendered admin HTML for the settings page.
- Assets load only on the plugin’s own admin pages.
- Existing add/edit/delete/x-default interactions still receive the data they need through enqueued assets.

### 3. Prefix audit for declarations, globals, and stored data

Goal: ensure the plugin uses a unique, consistent namespace across PHP, JS, hooks, and persisted data.

Proposed work:

- Audit all declarations and identifiers for prefixing:
  - PHP functions
  - classes
  - constants
  - hooks and callbacks
  - AJAX action names
  - nonce action names and fields
  - script/style handles
  - JavaScript globals
  - HTML ids/classes where collisions are plausible
- Audit all stored data:
  - options
  - post meta
  - query vars
  - rewrite tags/rules
- Decide whether the current `simple_hreflang` prefix is sufficient or whether a more distinctive project prefix should be adopted as part of the broader rename/slug work mentioned in `review.md`.
- If the prefix changes, prepare a compatibility strategy for existing saved data and hooks.

Implementation notes:

- Current stored keys are already reasonably prefixed, so this is likely an audit-and-tighten task rather than a full rewrite.
- The highest-risk gaps are usually script/style handles, JS globals, DOM ids, and any generic helper function names.
- If the plugin is renamed for directory approval, keep code prefix decisions intentional rather than automatically coupling them to the slug.

Acceptance criteria:

- All plugin-owned identifiers and stored keys are uniquely prefixed.
- No generic global JS symbol or unprefixed handle remains.
- Any prefix migration path is documented before implementation if persisted keys must change.

## Suggested Execution Order

1. Complete the prefix audit first so any asset handles, globals, or notice helpers are named correctly from the start.
2. Refactor admin CSS/JS to enqueue-based loading.
3. Tighten admin notice scope and validate Guideline 11 compliance after the asset and screen-hook refactor.
4. Run a final review pass against `review.md` and prepare the reviewer response.

## Verification Checklist

- Open wp-admin Dashboard and confirm no plugin notice appears there.
- Open unrelated edit screens and confirm no plugin assets load.
- Open `Settings > Simple Hreflang` and confirm assets load correctly.
- Test:
  - save settings
  - flush rewrite rules
  - add page to group
  - edit hreflang values
  - set x-default
  - delete all groups
- Confirm option/meta keys and AJAX actions remain prefixed and functional.
- Search the codebase for raw `<script>` / `<style>` output and unprefixed declarations before resubmission.

## Out of Scope For This Plan

- Renaming the plugin display name or slug.
- Implementing any of the above changes.
- Drafting the reviewer reply email.
