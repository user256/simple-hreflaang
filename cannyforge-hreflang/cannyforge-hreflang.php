<?php
/**
 * Plugin Name: CannyForge Hreflang
 * Description: Manage small translation groups for pages and generate a dedicated hreflang XML sitemap.
 * Version: 0.1.1
 * Author: User256
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: cannyforge-hreflang
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CANNYFORGE_HREFLANG_VERSION', '0.1.1' );
define( 'CANNYFORGE_HREFLANG_FILE', __FILE__ );
define( 'CANNYFORGE_HREFLANG_PATH', plugin_dir_path( __FILE__ ) );
define( 'CANNYFORGE_HREFLANG_URL', plugin_dir_url( __FILE__ ) );

require_once CANNYFORGE_HREFLANG_PATH . 'includes/class-cannyforge-hreflang-helpers.php';
require_once CANNYFORGE_HREFLANG_PATH . 'includes/class-cannyforge-hreflang-repository.php';
require_once CANNYFORGE_HREFLANG_PATH . 'includes/class-cannyforge-hreflang-meta-box.php';
require_once CANNYFORGE_HREFLANG_PATH . 'includes/class-cannyforge-hreflang-settings.php';
require_once CANNYFORGE_HREFLANG_PATH . 'includes/class-cannyforge-hreflang-sitemap-provider.php';
require_once CANNYFORGE_HREFLANG_PATH . 'includes/class-cannyforge-hreflang-plugin.php';

function cannyforge_hreflang_boot_plugin() {
    $plugin = new CannyForge_Hreflang_Plugin();
    $plugin->boot();
}

cannyforge_hreflang_boot_plugin();
