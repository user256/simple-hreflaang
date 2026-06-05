# Review Cleanup Plan

This file lists the cleanup tickets to complete before replying to the WordPress.org reviewer and uploading the next build.

Goal:

- eliminate the same review themes that already hit another plugin
- close obvious manual-review gaps before the assigned reviewer looks deeper
- leave a short evidence trail so the resubmission reply can be brief and defensible

Ready-to-execute rules:

- Every ticket closes with evidence (command output, file diff note, or manual test note).
- No ticket is marked done unless all acceptance criteria are met.
- If a ticket changes persisted keys/options/meta, record migration impact before merge.

## Ticket T1 — Final Prefix / Namespace Audit

**Why**

The original review feedback focused on generic or insufficiently distinctive prefixes. The current code has largely moved to `cannyforge_hreflang` / `CannyForge_Hreflang`, but this needs one explicit final audit so the reviewer cannot find a stray old key, handle, nonce, AJAX action, or transient name.

**Current code notes**

- Main constants and bootstrap function are prefixed in [cannyforge-hreflang.php](/home/user256/GitRepos/cannyforge-hreflang/cannyforge-hreflang/cannyforge-hreflang.php:18)
- Stored option and post meta keys are prefixed in [class-cannyforge-hreflang-repository.php](/home/user256/GitRepos/cannyforge-hreflang/cannyforge-hreflang/includes/class-cannyforge-hreflang-repository.php:7)
- AJAX actions are prefixed in [class-cannyforge-hreflang-plugin.php](/home/user256/GitRepos/cannyforge-hreflang/cannyforge-hreflang/includes/class-cannyforge-hreflang-plugin.php:28)
- Transient keys are prefixed in [class-cannyforge-hreflang-repository.php](/home/user256/GitRepos/cannyforge-hreflang/cannyforge-hreflang/includes/class-cannyforge-hreflang-repository.php:488) and [class-cannyforge-hreflang-settings.php](/home/user256/GitRepos/cannyforge-hreflang/cannyforge-hreflang/includes/class-cannyforge-hreflang-settings.php:9)

**Work**

- Search for any remaining:
  - old `simple_*` or `simple hreflang` identifiers
  - generic helper names
  - unprefixed globals
  - unprefixed script/style handles
  - unprefixed nonce actions / request keys / query args
- Verify all stored identifiers use a distinctive plugin-owned prefix:
  - options
  - post meta
  - transients
  - rewrite vars
  - AJAX actions
- Decide whether to keep the full `cannyforge_hreflang` prefix or introduce a shorter but still distinctive internal prefix such as `cfhr_`.
  - If changed, document migration impact before touching persisted keys.

**Acceptance criteria**

- No old `simple_*` identifiers remain anywhere in the shipped plugin.
- No plugin-owned identifier begins with `_`, `wp_`, or a generic/common word.
- A grep/export can be attached to the reviewer response if needed.

## Ticket T2 — Remove Remaining Inline Admin CSS

**Why**

The reviewer already raised enqueue/API concerns. The settings screen still emits a raw `<style>` block, which is an easy repeat finding.

**Current code notes**

- Inline admin CSS still exists in [class-cannyforge-hreflang-settings.php](/home/user256/GitRepos/cannyforge-hreflang/cannyforge-hreflang/includes/class-cannyforge-hreflang-settings.php:149)
- The plugin already has an admin stylesheet and enqueue path in [class-cannyforge-hreflang-settings.php](/home/user256/GitRepos/cannyforge-hreflang/cannyforge-hreflang/includes/class-cannyforge-hreflang-settings.php:66)

**Work**

- Move the remaining header/wrapper CSS from `render_page()` into `assets/css/cannyforge-hreflang-admin.css`
- If any tiny dynamic CSS is truly necessary, attach it with `wp_add_inline_style()` to the registered handle
- Re-scan for any raw `<style>` output in plugin PHP

**Acceptance criteria**

- No raw `<style>` tags remain in plugin-generated admin HTML
- Admin CSS is loaded only through WordPress enqueue APIs
- Assets stay scoped to the plugin’s own settings page / metabox screens

## Ticket T3 — Rewrite Flush Discipline

**Why**

This is not the issue the reviewer explicitly listed, but it is the kind of thing a manual reviewer can still flag. Calling `flush_rewrite_rules()` during content/meta updates is expensive and avoidable.

**Current code notes**

- `update_post_meta()` may flush rewrite rules on ordinary content changes in [class-cannyforge-hreflang-repository.php](/home/user256/GitRepos/cannyforge-hreflang/cannyforge-hreflang/includes/class-cannyforge-hreflang-repository.php:62)
- Activation/deactivation already flush correctly in [class-cannyforge-hreflang-sitemap-provider.php](/home/user256/GitRepos/cannyforge-hreflang/cannyforge-hreflang/includes/class-cannyforge-hreflang-sitemap-provider.php:76)
- A manual “Flush Rewrite Rules” button already exists in settings

**Work**

- Remove opportunistic rewrite flushing from ordinary metadata saves
- Keep flushes only on:
  - activation
  - deactivation
  - explicit admin action if still needed
- Verify the sitemap route still works after activation and after manual flush

**Acceptance criteria**

- No rewrite flush runs on normal post save / group-edit workflows
- The plugin still provides a clear recovery path if permalinks need refreshing

## Ticket T4 — Reviewer-Facing Identity Alignment

**Why**

The review thread shows naming/slug concerns. Even if the current display name is improved, the next upload must be internally consistent so the reviewer does not see mixed branding or stale metadata.

**Current code notes**

- Plugin header display name: `CannyForge Hreflang` in [cannyforge-hreflang.php](/home/user256/GitRepos/cannyforge-hreflang/cannyforge-hreflang/cannyforge-hreflang.php:3)
- Readme display name matches in [README.txt](/home/user256/GitRepos/cannyforge-hreflang/cannyforge-hreflang/README.txt:1)
- Text domain is `cannyforge-hreflang`
- Plugin header author is currently `OpenAI` in [cannyforge-hreflang.php](/home/user256/GitRepos/cannyforge-hreflang/cannyforge-hreflang/cannyforge-hreflang.php:6)

**Work**

- Decide the exact submission identity:
  - display name
  - slug requested from the reviewer
  - text domain strategy
- Ensure these are aligned everywhere:
  - plugin header
  - readme
  - asset names if relevant
  - user-facing admin labels
- Replace `Author: OpenAI` with the real author/owner identity before submission
- Prepare the one-line slug reservation request for the reviewer reply

**Acceptance criteria**

- No mismatched plugin names appear across code/readme/submission package
- No placeholder or third-party author identity remains
- Reviewer can reserve the new slug without ambiguity

## Ticket T5 — Admin Scope / Guideline 11 Verification

**Why**

The review mail also hinted at potential admin-hijack patterns. Even if the plugin is probably fine, this should be verified intentionally.

**Current code notes**

- Success notices are rendered inline inside the plugin settings screen in [class-cannyforge-hreflang-settings.php](/home/user256/GitRepos/cannyforge-hreflang/cannyforge-hreflang/includes/class-cannyforge-hreflang-settings.php:217)
- Admin assets are gated by hook suffix in [class-cannyforge-hreflang-settings.php](/home/user256/GitRepos/cannyforge-hreflang/cannyforge-hreflang/includes/class-cannyforge-hreflang-settings.php:516)

**Work**

- Confirm no global `admin_notices` output remains
- Confirm no assets load on unrelated admin screens
- Confirm no upsell, nag, or broad-scope notice appears outside the plugin page
- Confirm the metabox script loads only where needed

**Acceptance criteria**

- Dashboard / Plugins / Posts / Pages / unrelated admin screens stay untouched
- All plugin feedback remains local to the plugin’s own screens

## Ticket T6 — Submission Metadata and Compliance Pass

**Why**

A reviewer looking for one issue often catches adjacent metadata problems. Clean these now rather than waiting for a second round.

**Current code notes**

- `README.txt` still uses `Contributors: User256` in [README.txt](/home/user256/GitRepos/cannyforge-hreflang/cannyforge-hreflang/README.txt:2)
- Plugin/readme license is `MIT` in [cannyforge-hreflang.php](/home/user256/GitRepos/cannyforge-hreflang/cannyforge-hreflang/cannyforge-hreflang.php:9) and [README.txt](/home/user256/GitRepos/cannyforge-hreflang/cannyforge-hreflang/README.txt:8)

**Work**

- Verify readme formatting against current wp.org parser expectations
- Verify contributor casing / naming
- Decide whether to keep MIT or move to GPLv2+ / compatible wording for simpler wp.org review expectations
- Check screenshots/assets/readme copy for stale plugin naming

**Acceptance criteria**

- No obvious metadata-only follow-up issue remains for the reviewer to raise
- Submission package looks intentional and internally consistent

## Suggested Order

1. T4 Reviewer-facing identity alignment
2. T1 Final prefix / namespace audit
3. T2 Remove remaining inline admin CSS
4. T3 Rewrite flush discipline
5. T5 Admin scope / Guideline 11 verification
6. T6 Submission metadata and compliance pass

## Definition of Done (Release Gate)

- All six tickets complete with evidence notes.
- Final verification checklist passes on the same build candidate.
- Reviewer reply can cite concrete changes without extra investigation.
- Zip artifact contents and plugin metadata are internally consistent.

## Final Verification Checklist

- Search the shipped plugin for:
  - `simple_`
  - `Simple Hreflang`
  - raw `<style>`
  - raw `<script>`
  - `admin_notices`
  - `flush_rewrite_rules(`
- Test:
  - activation
  - settings save
  - sitemap route
  - add to group
  - delete all groups
  - set x-default
  - metabox save
- Inspect unrelated admin pages and confirm:
  - no notices
  - no plugin CSS/JS
- Build the zip and compare displayed name/readme/header/slug request before upload
- Capture a short evidence bundle for reply:
  - grep/search snippets for key checks
  - 1-2 screenshots or notes for admin-scope verification
  - final zip filename and checksum (optional but useful)

## Not In This File

- Reviewer reply draft
- Actual code changes
- Slug reservation email text
