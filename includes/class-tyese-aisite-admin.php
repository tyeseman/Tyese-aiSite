<?php
/**
 * WordPress admin interface for Tyese aiSite.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Tyese_AiSite_Admin {
    const OPTION = 'tyese_aisite_settings';
    const LAST_BLUEPRINT = 'tyese_aisite_last_blueprint';
    const LAST_CREATED = 'tyese_aisite_last_created';
    const LAST_STATUS = 'tyese_aisite_last_status';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_post_tyese_aisite_save_settings', array( $this, 'save_settings' ) );
        add_action( 'admin_post_tyese_aisite_generate', array( $this, 'generate' ) );
    }

    public function menu() {
        add_menu_page(
            __( 'Tyese aiSite', 'tyese-aisite' ),
            __( 'Tyese aiSite', 'tyese-aisite' ),
            'manage_options',
            'tyese-aisite',
            array( $this, 'render' ),
            'dashicons-superhero',
            58
        );
    }

    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $settings  = $this->settings();
        $inventory = ( new Tyese_AiSite_Widgets() )->inventory();
        $site_scan = ( new Tyese_AiSite_Site_Scanner() )->scan();
        $blueprint = get_option( self::LAST_BLUEPRINT, array() );
        $created   = get_option( self::LAST_CREATED, array() );
        $status    = get_option( self::LAST_STATUS, array() );
        ?>
        <div class="wrap tyese-aisite-admin">
            <h1><?php esc_html_e( 'Tyese aiSite', 'tyese-aisite' ); ?></h1>
            <p class="tyese-aisite-lede"><?php esc_html_e( 'Build editable Elementor website drafts from a prompt using Elementor, Tyese widgets, and compatible installed widgets.', 'tyese-aisite' ); ?></p>

            <?php $this->render_notice(); ?>

            <div class="tyese-aisite-layout">
                <section class="tyese-aisite-panel">
                    <h2><?php esc_html_e( 'AI Builder', 'tyese-aisite' ); ?></h2>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'tyese_aisite_generate' ); ?>
                        <input type="hidden" name="action" value="tyese_aisite_generate">

                        <label>
                            <span><?php esc_html_e( 'Website Prompt', 'tyese-aisite' ); ?></span>
                            <textarea name="prompt" rows="8" required placeholder="<?php esc_attr_e( 'Example: Build a warm, modern five-page website for a church in Atlanta with livestream, sermons, events, giving, and contact sections.', 'tyese-aisite' ); ?>"></textarea>
                        </label>

                        <label>
                            <span><?php esc_html_e( 'Reference URL', 'tyese-aisite' ); ?></span>
                            <input type="url" name="reference_url" placeholder="https://example.com">
                            <small><?php esc_html_e( 'Used only for layout inspiration. Tyese aiSite will not copy protected branding, text, images, or IP.', 'tyese-aisite' ); ?></small>
                        </label>

                        <label>
                            <span><?php esc_html_e( 'Brand Context', 'tyese-aisite' ); ?></span>
                            <textarea name="brand_context" rows="5" placeholder="<?php esc_attr_e( 'Business name, audience, services, location, colors, tone, phone, email, social links, and must-have sections.', 'tyese-aisite' ); ?>"><?php echo esc_textarea( $settings['brand_context'] ?? '' ); ?></textarea>
                        </label>

                        <label class="tyese-aisite-check">
                            <input type="checkbox" name="build_pages" value="1" checked>
                            <span><?php esc_html_e( 'Create draft Elementor pages after generating the blueprint', 'tyese-aisite' ); ?></span>
                        </label>

                        <div class="tyese-aisite-build-options">
                            <h3><?php esc_html_e( 'Build Mode', 'tyese-aisite' ); ?></h3>
                            <label>
                                <span><?php esc_html_e( 'How should Tyese aiSite handle current pages?', 'tyese-aisite' ); ?></span>
                                <select name="build_mode">
                                    <option value="create_new"><?php esc_html_e( 'Create new draft pages and leave current pages alone', 'tyese-aisite' ); ?></option>
                                    <option value="update_existing"><?php esc_html_e( 'Create AI draft copies for selected existing pages', 'tyese-aisite' ); ?></option>
                                    <option value="replace_selected"><?php esc_html_e( 'Replace selected pages as drafts after saving a backup', 'tyese-aisite' ); ?></option>
                                </select>
                            </label>

                            <label>
                                <span><?php esc_html_e( 'Page template / theme handling', 'tyese-aisite' ); ?></span>
                                <select name="page_template">
                                    <option value="elementor_canvas"><?php esc_html_e( 'Elementor Canvas - ignore theme layout', 'tyese-aisite' ); ?></option>
                                    <option value="elementor_header_footer"><?php esc_html_e( 'Elementor Full Width - keep theme header/footer', 'tyese-aisite' ); ?></option>
                                    <option value="default"><?php esc_html_e( 'Theme default template', 'tyese-aisite' ); ?></option>
                                </select>
                            </label>

                            <label class="tyese-aisite-check">
                                <input type="checkbox" name="set_homepage" value="1">
                                <span><?php esc_html_e( 'Set the first generated page as the site homepage', 'tyese-aisite' ); ?></span>
                            </label>

                            <label class="tyese-aisite-check">
                                <input type="checkbox" name="backup_originals" value="1" checked>
                                <span><?php esc_html_e( 'Save backup metadata before touching selected existing pages', 'tyese-aisite' ); ?></span>
                            </label>

                            <?php $this->render_page_picker( $site_scan['pages'] ); ?>
                        </div>

                        <button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Generate Site Draft', 'tyese-aisite' ); ?></button>
                    </form>
                </section>

                <aside class="tyese-aisite-panel">
                    <h2><?php esc_html_e( 'Settings', 'tyese-aisite' ); ?></h2>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'tyese_aisite_save_settings' ); ?>
                        <input type="hidden" name="action" value="tyese_aisite_save_settings">

                        <label>
                            <span><?php esc_html_e( 'OpenAI API Key', 'tyese-aisite' ); ?></span>
                            <input type="password" name="api_key" value="" placeholder="<?php echo $settings['api_key'] ? esc_attr__( 'API key saved. Enter a new key to replace it.', 'tyese-aisite' ) : esc_attr__( 'sk-...', 'tyese-aisite' ); ?>">
                        </label>

                        <label>
                            <span><?php esc_html_e( 'Model', 'tyese-aisite' ); ?></span>
                            <input type="text" name="model" value="<?php echo esc_attr( $settings['model'] ?? 'gpt-5-mini' ); ?>">
                        </label>

                        <label>
                            <span><?php esc_html_e( 'Default Brand Context', 'tyese-aisite' ); ?></span>
                            <textarea name="brand_context" rows="4"><?php echo esc_textarea( $settings['brand_context'] ?? '' ); ?></textarea>
                        </label>

                        <button type="submit" class="button"><?php esc_html_e( 'Save Settings', 'tyese-aisite' ); ?></button>
                    </form>
                </aside>
            </div>

            <div class="tyese-aisite-layout">
                <section class="tyese-aisite-panel tyese-aisite-wide">
                    <h2><?php esc_html_e( 'Current Site Scan', 'tyese-aisite' ); ?></h2>
                    <?php $this->render_site_scan( $site_scan ); ?>
                </section>

                <section class="tyese-aisite-panel">
                    <h2><?php esc_html_e( 'Build Status', 'tyese-aisite' ); ?></h2>
                    <?php $this->render_status( $status, $created, $blueprint ); ?>
                </section>

                <section class="tyese-aisite-panel">
                    <h2><?php esc_html_e( 'Available Widget Inventory', 'tyese-aisite' ); ?></h2>
                    <p><?php echo esc_html( sprintf( __( '%d widgets available to the planner.', 'tyese-aisite' ), count( $inventory ) ) ); ?></p>
                    <div class="tyese-aisite-widget-list">
                        <?php foreach ( array_slice( $inventory, 0, 80 ) as $widget ) : ?>
                            <span><?php echo esc_html( $widget['name'] ); ?></span>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="tyese-aisite-panel tyese-aisite-wide">
                    <h2><?php esc_html_e( 'Last Build', 'tyese-aisite' ); ?></h2>
                    <?php if ( ! empty( $created ) ) : ?>
                        <ul class="tyese-aisite-created">
                            <?php foreach ( $created as $page ) : ?>
                                <li>
                                    <strong><?php echo esc_html( $page['title'] ); ?></strong>
                                    <a href="<?php echo esc_url( $page['edit'] ); ?>"><?php esc_html_e( 'Edit', 'tyese-aisite' ); ?></a>
                                    <a href="<?php echo esc_url( $page['view'] ); ?>"><?php esc_html_e( 'View', 'tyese-aisite' ); ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php elseif ( ! empty( $blueprint ) ) : ?>
                        <p><?php esc_html_e( 'A blueprint was generated, but no pages were created.', 'tyese-aisite' ); ?></p>
                    <?php else : ?>
                        <p><?php esc_html_e( 'No build has been generated yet.', 'tyese-aisite' ); ?></p>
                    <?php endif; ?>
                </section>
            </div>
        </div>
        <?php
    }

    public function save_settings() {
        if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'tyese_aisite_save_settings' ) ) {
            wp_die( esc_html__( 'You are not allowed to save these settings.', 'tyese-aisite' ) );
        }

        $settings = $this->settings();
        $api_key  = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );

        if ( $api_key ) {
            $settings['api_key'] = $api_key;
        }

        $settings['model']         = sanitize_text_field( wp_unslash( $_POST['model'] ?? 'gpt-5-mini' ) );
        $settings['brand_context'] = sanitize_textarea_field( wp_unslash( $_POST['brand_context'] ?? '' ) );

        update_option( self::OPTION, $settings, false );
        wp_safe_redirect( add_query_arg( 'tyese_aisite_notice', 'settings_saved', admin_url( 'admin.php?page=tyese-aisite' ) ) );
        exit;
    }

    public function generate() {
        if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'tyese_aisite_generate' ) ) {
            wp_die( esc_html__( 'You are not allowed to generate a site.', 'tyese-aisite' ) );
        }

        $settings      = $this->settings();
        $prompt        = sanitize_textarea_field( wp_unslash( $_POST['prompt'] ?? '' ) );
        $reference_url = esc_url_raw( wp_unslash( $_POST['reference_url'] ?? '' ) );
        $brand_context = sanitize_textarea_field( wp_unslash( $_POST['brand_context'] ?? $settings['brand_context'] ?? '' ) );
        $build_pages   = ! empty( $_POST['build_pages'] );
        $build_mode    = sanitize_key( wp_unslash( $_POST['build_mode'] ?? 'create_new' ) );
        $page_template = sanitize_key( wp_unslash( $_POST['page_template'] ?? 'elementor_canvas' ) );
        $selected_pages = array_map( 'absint', (array) ( $_POST['selected_pages'] ?? array() ) );
        $set_homepage  = ! empty( $_POST['set_homepage'] );
        $backup_originals = ! empty( $_POST['backup_originals'] );
        $widgets       = new Tyese_AiSite_Widgets();
        $scanner       = new Tyese_AiSite_Site_Scanner();
        $brand_context .= "\n\nCurrent WordPress site scan:\n" . $scanner->prompt_context();
        $client        = new Tyese_AiSite_OpenAI( $settings['api_key'] ?? '', $settings['model'] ?? 'gpt-5-mini' );
        $builder_args  = array(
            'mode'             => $build_mode,
            'selected_pages'   => $selected_pages,
            'page_template'    => $page_template,
            'set_homepage'     => $set_homepage,
            'backup_originals' => $backup_originals,
        );

        $blueprint = $client->generate_blueprint( $prompt, $reference_url, $widgets->names_for_prompt(), $brand_context );
        if ( is_wp_error( $blueprint ) ) {
            $error_message = $blueprint->get_error_message();
            $blueprint = ( new Tyese_AiSite_Blueprint() )->fallback_blueprint();
            $created   = $build_pages ? ( new Tyese_AiSite_Elementor_Builder() )->build( $blueprint, $builder_args ) : array();

            update_option( self::LAST_BLUEPRINT, $blueprint, false );
            update_option( self::LAST_CREATED, $created, false );
            update_option(
                self::LAST_STATUS,
                array(
                    'state'         => 'complete',
                    'source'        => 'fallback',
                    'message'       => __( 'OpenAI failed, so Tyese aiSite built a safe fallback draft.', 'tyese-aisite' ),
                    'error'         => $error_message,
                    'created_count' => count( $created ),
                    'build_mode'    => $build_mode,
                    'page_template' => $page_template,
                    'finished_at'   => current_time( 'mysql' ),
                ),
                false
            );

            wp_safe_redirect( add_query_arg( 'tyese_aisite_notice', 'fallback_used', admin_url( 'admin.php?page=tyese-aisite' ) ) );
            exit;
        }

        update_option( self::LAST_BLUEPRINT, $blueprint, false );
        $created = $build_pages ? ( new Tyese_AiSite_Elementor_Builder() )->build( $blueprint, $builder_args ) : array();
        update_option( self::LAST_CREATED, $created, false );
        update_option(
            self::LAST_STATUS,
            array(
                'state'         => 'complete',
                'source'        => 'openai',
                'message'       => __( 'OpenAI generated a blueprint and Tyese aiSite finished the draft build.', 'tyese-aisite' ),
                'error'         => '',
                'created_count' => count( $created ),
                'build_mode'    => $build_mode,
                'page_template' => $page_template,
                'finished_at'   => current_time( 'mysql' ),
            ),
            false
        );

        wp_safe_redirect( add_query_arg( 'tyese_aisite_notice', 'site_generated', admin_url( 'admin.php?page=tyese-aisite' ) ) );
        exit;
    }

    private function settings() {
        $defaults = array(
            'api_key'       => '',
            'model'         => 'gpt-5-mini',
            'brand_context' => '',
        );

        return wp_parse_args( get_option( self::OPTION, array() ), $defaults );
    }

    private function render_notice() {
        $notice = sanitize_key( $_GET['tyese_aisite_notice'] ?? '' );
        if ( ! $notice ) {
            return;
        }

        $messages = array(
            'settings_saved' => __( 'Settings saved.', 'tyese-aisite' ),
            'site_generated' => __( 'Site draft generated. Review the draft pages below before publishing.', 'tyese-aisite' ),
            'fallback_used'  => __( 'OpenAI could not generate a blueprint. Tyese aiSite finished and used the safe fallback draft instead; see Build Status below for the real error.', 'tyese-aisite' ),
            'failed'         => __( 'Tyese aiSite could not generate a blueprint.', 'tyese-aisite' ),
        );

        if ( isset( $messages[ $notice ] ) ) {
            echo '<div class="notice notice-success"><p>' . esc_html( $messages[ $notice ] ) . '</p></div>';
        }
    }

    private function render_status( $status, $created, $blueprint ) {
        if ( empty( $status ) ) {
            echo '<p>' . esc_html__( 'No build is running. No completed build has been recorded yet.', 'tyese-aisite' ) . '</p>';
            return;
        }

        $source = $status['source'] ?? 'unknown';
        $badge  = 'fallback' === $source ? __( 'Fallback draft', 'tyese-aisite' ) : __( 'AI draft', 'tyese-aisite' );
        echo '<p><span class="tyese-aisite-status-badge">' . esc_html__( 'Complete', 'tyese-aisite' ) . '</span> <strong>' . esc_html( $badge ) . '</strong></p>';
        echo '<p>' . esc_html( $status['message'] ?? '' ) . '</p>';

        if ( ! empty( $status['finished_at'] ) ) {
            echo '<p><strong>' . esc_html__( 'Finished:', 'tyese-aisite' ) . '</strong> ' . esc_html( $status['finished_at'] ) . '</p>';
        }

        if ( ! empty( $status['build_mode'] ) ) {
            echo '<p><strong>' . esc_html__( 'Build mode:', 'tyese-aisite' ) . '</strong> ' . esc_html( str_replace( '_', ' ', $status['build_mode'] ) ) . '</p>';
        }

        if ( ! empty( $status['page_template'] ) ) {
            echo '<p><strong>' . esc_html__( 'Page template:', 'tyese-aisite' ) . '</strong> ' . esc_html( str_replace( '_', ' ', $status['page_template'] ) ) . '</p>';
        }

        echo '<p><strong>' . esc_html__( 'Draft pages created:', 'tyese-aisite' ) . '</strong> ' . esc_html( (string) count( (array) $created ) ) . '</p>';

        if ( ! empty( $status['error'] ) ) {
            echo '<div class="tyese-aisite-error"><strong>' . esc_html__( 'OpenAI error:', 'tyese-aisite' ) . '</strong><br>' . esc_html( $status['error'] ) . '</div>';
        }

        if ( ! empty( $blueprint['pages'] ) ) {
            echo '<p><strong>' . esc_html__( 'Blueprint pages:', 'tyese-aisite' ) . '</strong> ' . esc_html( implode( ', ', wp_list_pluck( $blueprint['pages'], 'title' ) ) ) . '</p>';
        }
    }

    private function render_page_picker( $pages ) {
        if ( empty( $pages ) ) {
            echo '<p class="tyese-aisite-muted">' . esc_html__( 'No existing pages found. New Site Mode is the right choice.', 'tyese-aisite' ) . '</p>';
            return;
        }

        echo '<div class="tyese-aisite-page-picker">';
        echo '<strong>' . esc_html__( 'Existing pages Tyese aiSite may work from:', 'tyese-aisite' ) . '</strong>';
        foreach ( $pages as $page ) {
            echo '<label class="tyese-aisite-page-row">';
            echo '<input type="checkbox" name="selected_pages[]" value="' . esc_attr( $page['id'] ) . '">';
            echo '<span>' . esc_html( $page['title'] ) . ' <small>' . esc_html( '#' . $page['id'] . ' - ' . $page['status'] . ' - ' . ( $page['is_elementor'] ? 'Elementor' : 'Classic/unknown' ) ) . '</small></span>';
            echo '</label>';
        }
        echo '</div>';
    }

    private function render_site_scan( $scan ) {
        echo '<div class="tyese-aisite-scan-grid">';
        echo '<div><strong>' . esc_html__( 'Theme', 'tyese-aisite' ) . '</strong><span>' . esc_html( $scan['theme']['name'] . ' ' . $scan['theme']['version'] ) . '</span></div>';
        echo '<div><strong>' . esc_html__( 'Homepage', 'tyese-aisite' ) . '</strong><span>' . esc_html( 'page' === $scan['homepage']['show_on_front'] ? '#' . $scan['homepage']['page_on_front'] : __( 'Latest posts', 'tyese-aisite' ) ) . '</span></div>';
        echo '<div><strong>' . esc_html__( 'Pages', 'tyese-aisite' ) . '</strong><span>' . esc_html( (string) count( $scan['pages'] ) ) . '</span></div>';
        echo '<div><strong>' . esc_html__( 'Menus', 'tyese-aisite' ) . '</strong><span>' . esc_html( (string) count( $scan['menus'] ) ) . '</span></div>';
        echo '</div>';
        echo '<p class="tyese-aisite-muted">' . esc_html__( 'When the installed theme does not match the requested design direction, choose Elementor Canvas or Elementor Full Width so the generated page controls more of the layout.', 'tyese-aisite' ) . '</p>';
    }
}
