<?php
/**
 * Core loader for Tyese aiSite.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Tyese_AiSite {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'load' ) );
    }

    public function load() {
        require_once TYESE_AISITE_PATH . 'includes/class-tyese-aisite-widgets.php';
        require_once TYESE_AISITE_PATH . 'includes/class-tyese-aisite-blueprint.php';
        require_once TYESE_AISITE_PATH . 'includes/class-tyese-aisite-openai.php';
        require_once TYESE_AISITE_PATH . 'includes/class-tyese-aisite-elementor-builder.php';
        require_once TYESE_AISITE_PATH . 'includes/class-tyese-aisite-admin.php';

        add_action( 'admin_notices', array( $this, 'dependency_notices' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );

        if ( is_admin() ) {
            new Tyese_AiSite_Admin();
        }
    }

    public function admin_assets( $hook ) {
        if ( false === strpos( $hook, 'tyese-aisite' ) ) {
            return;
        }

        wp_enqueue_style( 'tyese-aisite-admin', TYESE_AISITE_URL . 'assets/css/admin.css', array(), TYESE_AISITE_VERSION );
        wp_enqueue_script( 'tyese-aisite-admin', TYESE_AISITE_URL . 'assets/js/admin.js', array(), TYESE_AISITE_VERSION, true );
    }

    public function dependency_notices() {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }

        if ( ! did_action( 'elementor/loaded' ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Tyese aiSite requires Elementor to be installed and activated.', 'tyese-aisite' ) . '</p></div>';
        }

        if ( ! class_exists( 'Tyese_Elementor_Addon' ) ) {
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'Tyese aiSite is designed to work with Tyese Addon for Elementor. Install and activate it for the best widget coverage.', 'tyese-aisite' ) . '</p></div>';
        }
    }
}
