<?php
/**
 * Plugin Name: Tyese aiSite
 * Description: AI website builder for Elementor that creates editable WordPress pages using Elementor, Tyese widgets, and installed compatible widgets.
 * Version: 0.2.0
 * Author: Leon C. Tyes
 * Text Domain: tyese-aisite
 * Requires at least: 6.4
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'TYESE_AISITE_VERSION', '0.2.0' );
define( 'TYESE_AISITE_FILE', __FILE__ );
define( 'TYESE_AISITE_PATH', plugin_dir_path( __FILE__ ) );
define( 'TYESE_AISITE_URL', plugin_dir_url( __FILE__ ) );

require_once TYESE_AISITE_PATH . 'includes/class-tyese-aisite.php';

Tyese_AiSite::instance();
