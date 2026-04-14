<?php
/**
 * Plugin Name: Simple Hreflang
 * Description: Manage small translation groups for pages and generate a dedicated hreflang XML sitemap.
 * Version: 0.1.0
 * Author: OpenAI
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: simple-hreflang
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SIMPLE_HREFLANG_VERSION', '0.1.0' );
define( 'SIMPLE_HREFLANG_FILE', __FILE__ );
define( 'SIMPLE_HREFLANG_PATH', plugin_dir_path( __FILE__ ) );
define( 'SIMPLE_HREFLANG_URL', plugin_dir_url( __FILE__ ) );

require_once SIMPLE_HREFLANG_PATH . 'includes/class-simple-hreflang-helpers.php';
require_once SIMPLE_HREFLANG_PATH . 'includes/class-simple-hreflang-repository.php';
require_once SIMPLE_HREFLANG_PATH . 'includes/class-simple-hreflang-meta-box.php';
require_once SIMPLE_HREFLANG_PATH . 'includes/class-simple-hreflang-settings.php';
require_once SIMPLE_HREFLANG_PATH . 'includes/class-simple-hreflang-sitemap-provider.php';
require_once SIMPLE_HREFLANG_PATH . 'includes/class-simple-hreflang-plugin.php';

function simple_hreflang_boot_plugin() {
    $plugin = new Simple_Hreflang_Plugin();
    $plugin->boot();
}

simple_hreflang_boot_plugin();
