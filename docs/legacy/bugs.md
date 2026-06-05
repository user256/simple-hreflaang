# CannyForge Hreflang - WordPress.org Prefix Compliance Review

Review Date: April 30, 2026
WordPress.org Review Guidelines: Generic function/class/define/namespace/option names must be unique.

---

## Summary

The WordPress.org review tool reported potential prefix issues. After manual review of the codebase, **all prefix issues have been resolved**. The plugin consistently uses the `cannyforge_hreflang_` prefix throughout.

---

## WordPress.org Original Report

```
# This plugin is using the prefixes "cannyforge_hreflang", "canny_forge_hreflang" for 16 element(s).
# Using the common word "simple" as a prefix.
includes/class-cannyforge-hreflang-repository.php:620 set_transient($cache_key, array(), 10 * MINUTE_IN_SECONDS);
includes/class-cannyforge-hreflang-repository.php:627 set_transient($cache_key, array(), 10 * MINUTE_IN_SECONDS);
includes/class-cannyforge-hreflang-repository.php:673 set_transient($cache_key, $rules, 10 * MINUTE_IN_SECONDS);
```

---

## Detailed Findings

### 1. ✅ "simple" Prefix Issue - RESOLVED

**Original Issue:** The WordPress.org tool detected usage of the common word "simple" as a prefix.

**Current Status:** The `$cache_key` variable referenced in the report is defined at line 605 as:
```php
$cache_key = 'cannyforge_hreflang_robots_rules';
```

This is the correct plugin-specific prefix. The transient operations at lines 620, 627, and 673 all use this correctly-prefixed variable.

**Verification:**
- ✅ Line 605: `$cache_key = 'cannyforge_hreflang_robots_rules';`
- ✅ Line 620: `set_transient( $cache_key, array(), 10 * MINUTE_IN_SECONDS );`
- ✅ Line 627: `set_transient( $cache_key, array(), 10 * MINUTE_IN_SECONDS );`
- ✅ Line 673: `set_transient( $cache_key, $rules, 10 * MINUTE_IN_SECONDS );`

**Search Results:**
- No occurrences of "simple" prefix found in current codebase
- No occurrences of "sh_" prefix found in current codebase

---

### 2. ✅ Transient Keys - COMPLIANT

All transient keys use the `cannyforge_hreflang_` prefix:

| File | Line | Transient Key |
|------|------|---------------|
| `class-cannyforge-hreflang-repository.php` | 488 | `cannyforge_hreflang_probe_{md5}` |
| `class-cannyforge-hreflang-repository.php` | 605 | `cannyforge_hreflang_robots_rules` |
| `class-cannyforge-hreflang-settings.php` | 146 | `cannyforge_hreflang_audit_results` |

---

### 3. ✅ Option Keys - COMPLIANT

All option keys use the `cannyforge_hreflang_` prefix:

| File | Line | Option Key |
|------|------|------------|
| `class-cannyforge-hreflang-repository.php` | 21 | `cannyforge_hreflang_settings` |

---

### 4. ✅ Nonce Names - COMPLIANT

All nonce actions and field names use the `cannyforge_hreflang_` prefix:

| File | Action/Field |
|------|--------------|
| `class-cannyforge-hreflang-meta-box.php` | `cannyforge_hreflang_save_meta` |
| `class-cannyforge-hreflang-meta-box.php` | `cannyforge_hreflang_nonce` |
| `class-cannyforge-hreflang-settings.php` | `cannyforge_hreflang_flush_rules` |
| `class-cannyforge-hreflang-settings.php` | `cannyforge_hreflang_flush_nonce` |
| `class-cannyforge-hreflang-settings.php` | `cannyforge_hreflang_delete_all_groups` |
| `class-cannyforge-hreflang-settings.php` | `cannyforge_hreflang_nonce_delete` |
| `class-cannyforge-hreflang-settings.php` | `cannyforge_hreflang_set_x_default` |
| `class-cannyforge-hreflang-settings.php` | `cannyforge_hreflang_set_default_nonce` |
| `class-cannyforge-hreflang-settings.php` | `cannyforge_hreflang_add_to_group` |
| `class-cannyforge-hreflang-settings.php` | `cannyforge_hreflang_nonce_add` |
| `class-cannyforge-hreflang-settings.php` | `cannyforge_hreflang_run_indexability_audit` |
| `class-cannyforge-hreflang-settings.php` | `cannyforge_hreflang_audit_nonce` |

---

### 5. ✅ AJAX Actions - COMPLIANT

All AJAX actions use the `cannyforge_hreflang_` prefix:

| File | Line | AJAX Action |
|------|------|-------------|
| `class-cannyforge-hreflang-plugin.php` | 28 | `cannyforge_hreflang_add_to_group` |
| `class-cannyforge-hreflang-plugin.php` | 29 | `cannyforge_hreflang_delete_all_groups` |
| `class-cannyforge-hreflang-plugin.php` | 30 | `cannyforge_hreflang_set_x_default` |

---

### 6. ✅ Constants - COMPLIANT

All constants use the `CANNYFORGE_HREFLANG_` prefix:

| File | Line | Constant |
|------|------|----------|
| `cannyforge-hreflang.php` | 18 | `CANNYFORGE_HREFLANG_VERSION` |
| `cannyforge-hreflang.php` | 19 | `CANNYFORGE_HREFLANG_FILE` |
| `cannyforge-hreflang.php` | 20 | `CANNYFORGE_HREFLANG_PATH` |
| `cannyforge-hreflang.php` | 21 | `CANNYFORGE_HREFLANG_URL` |

---

### 7. ✅ Class Names - COMPLIANT

All class names use the `CannyForge_Hreflang_` prefix:

| Class Name |
|------------|
| `CannyForge_Hreflang_Helpers` |
| `CannyForge_Hreflang_Repository` |
| `CannyForge_Hreflang_Meta_Box` |
| `CannyForge_Hreflang_Settings` |
| `CannyForge_Hreflang_Sitemap_Provider` |
| `CannyForge_Hreflang_Plugin` |

---

### 8. ✅ CSS Classes - COMPLIANT

All CSS classes use the `cannyforge-hreflang-` prefix (kebab-case):

- `cannyforge-hreflang-post-type-option`
- `cannyforge-hreflang-inline-form`
- `cannyforge-hreflang-toolbar`
- `cannyforge-hreflang-modal`
- `cannyforge-hreflang-modal__dialog`
- etc.

---

### 9. ✅ Query Variables - COMPLIANT

The rewrite query variable uses the correct prefix:

| File | Line | Query Var |
|------|------|-----------|
| `class-cannyforge-hreflang-sitemap-provider.php` | 15 | `cannyforge_hreflang_sitemap` |

---

## Conclusion

**Status: COMPLIANT**

All prefix-related issues have been resolved. The plugin consistently uses:
- Function/variable prefix: `cannyforge_hreflang_`
- Class prefix: `CannyForge_Hreflang_`
- Constant prefix: `CANNYFORGE_HREFLANG_`
- CSS class prefix: `cannyforge-hreflang-`

No reserved prefixes (`wp_`, `_`, `__`) are used. No common/generic words are used as prefixes.
