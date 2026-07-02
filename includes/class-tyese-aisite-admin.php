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
    const PENDING_JOB = 'tyese_aisite_pending_job';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_init', array( $this, 'maybe_repair_legacy_pages' ) );
        add_action( 'admin_post_tyese_aisite_save_settings', array( $this, 'save_settings' ) );
        add_action( 'admin_post_tyese_aisite_generate', array( $this, 'generate' ) );
        add_action( 'admin_post_tyese_aisite_repair_last_build', array( $this, 'repair_last_build' ) );
        add_action( 'tyese_aisite_run_build', array( $this, 'run_queued_build' ) );
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
        <div class="wrap tyese-aisite-admin" data-tyese-build-state="<?php echo esc_attr( $status['state'] ?? 'idle' ); ?>">
            <h1><?php esc_html_e( 'Tyese aiSite', 'tyese-aisite' ); ?></h1>
            <p class="tyese-aisite-lede"><?php esc_html_e( 'Build editable Elementor website drafts from a prompt using Elementor, Tyese widgets, and compatible installed widgets.', 'tyese-aisite' ); ?></p>

            <?php $this->render_notice(); ?>

            <div class="tyese-aisite-layout">
                <section class="tyese-aisite-panel">
                    <h2><?php esc_html_e( 'AI Builder', 'tyese-aisite' ); ?></h2>
                    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
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
                            <span><?php esc_html_e( 'Reference Design Screenshot', 'tyese-aisite' ); ?></span>
                            <input type="file" name="reference_image" accept="image/png,image/jpeg,image/webp">
                            <small><?php esc_html_e( 'Optional. Upload a homepage or page design image so Tyese aiSite can follow its layout, spacing, hierarchy, and image ratios while keeping the result editable in Elementor.', 'tyese-aisite' ); ?></small>
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
                        <form class="tyese-aisite-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <?php wp_nonce_field( 'tyese_aisite_repair_last_build' ); ?>
                            <input type="hidden" name="action" value="tyese_aisite_repair_last_build">
                            <button type="submit" class="button"><?php esc_html_e( 'Repair Last Generated Pages', 'tyese-aisite' ); ?></button>
                        </form>
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
        $reference_image_id = $this->handle_reference_image_upload();
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

        $job = array(
            'prompt'        => $prompt,
            'reference_url' => $reference_url,
            'reference_image_id' => $reference_image_id,
            'brand_context' => $brand_context,
            'build_pages'   => $build_pages,
            'builder_args'  => $builder_args,
            'settings'      => array(
                'api_key' => $settings['api_key'] ?? '',
                'model'   => $settings['model'] ?? 'gpt-5-mini',
            ),
            'created_at'    => current_time( 'mysql' ),
        );

        update_option( self::PENDING_JOB, $job, false );
        update_option(
            self::LAST_STATUS,
            array(
                'state'         => 'queued',
                'source'        => 'pending',
                'message'       => __( 'Build queued. You can stay on this page; Tyese aiSite will refresh the status while the background job runs.', 'tyese-aisite' ),
                'error'         => '',
                'created_count' => 0,
                'build_mode'    => $build_mode,
                'page_template' => $page_template,
                'queued_at'     => current_time( 'mysql' ),
            ),
            false
        );

        if ( ! wp_next_scheduled( 'tyese_aisite_run_build' ) ) {
            wp_schedule_single_event( time() + 1, 'tyese_aisite_run_build' );
        }

        if ( function_exists( 'spawn_cron' ) ) {
            spawn_cron( time() );
        }

        wp_safe_redirect( add_query_arg( 'tyese_aisite_notice', 'build_queued', admin_url( 'admin.php?page=tyese-aisite' ) ) );
        exit;
    }

    public function repair_last_build() {
        if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'tyese_aisite_repair_last_build' ) ) {
            wp_die( esc_html__( 'You are not allowed to repair generated pages.', 'tyese-aisite' ) );
        }

        $count = $this->repair_generated_pages();
        wp_safe_redirect( add_query_arg( array( 'tyese_aisite_notice' => 'pages_repaired', 'tyese_aisite_repaired' => $count ), admin_url( 'admin.php?page=tyese-aisite' ) ) );
        exit;
    }

    public function maybe_repair_legacy_pages() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $stored_version = get_option( 'tyese_aisite_repair_version', '' );
        if ( version_compare( (string) $stored_version, TYESE_AISITE_VERSION, '>=' ) ) {
            return;
        }

        $count = $this->repair_generated_pages();
        update_option( 'tyese_aisite_repair_version', TYESE_AISITE_VERSION, false );

        if ( $count > 0 ) {
            $status = get_option( self::LAST_STATUS, array() );
            if ( ! is_array( $status ) ) {
                $status = array();
            }
            $status['repair_message'] = sprintf( __( 'Tyese aiSite repaired %d generated Elementor page(s) after the plugin update.', 'tyese-aisite' ), $count );
            update_option( self::LAST_STATUS, $status, false );
        }
    }

    public function run_queued_build() {
        $job = get_option( self::PENDING_JOB, array() );
        if ( empty( $job ) || ! is_array( $job ) ) {
            return;
        }

        update_option(
            self::LAST_STATUS,
            array(
                'state'         => 'running',
                'source'        => 'pending',
                'message'       => __( 'Tyese aiSite is generating the blueprint and building draft pages in the background.', 'tyese-aisite' ),
                'error'         => '',
                'created_count' => 0,
                'build_mode'    => $job['builder_args']['mode'] ?? 'create_new',
                'page_template' => $job['builder_args']['page_template'] ?? 'elementor_canvas',
                'started_at'    => current_time( 'mysql' ),
            ),
            false
        );

        $widgets = new Tyese_AiSite_Widgets();
        $client  = new Tyese_AiSite_OpenAI( $job['settings']['api_key'] ?? '', $job['settings']['model'] ?? 'gpt-5-mini' );
        $this->execute_build( $client, $widgets, $job );
        delete_option( self::PENDING_JOB );
    }

    private function execute_build( $client, $widgets, $job ) {
        $build_pages  = ! empty( $job['build_pages'] );
        $builder_args = is_array( $job['builder_args'] ?? null ) ? $job['builder_args'] : array();
        $reference_image = ! empty( $job['reference_image_id'] ) ? $this->reference_image_data_url( absint( $job['reference_image_id'] ) ) : '';

        $blueprint = $client->generate_blueprint( $job['prompt'] ?? '', $job['reference_url'] ?? '', $widgets->names_for_prompt(), $job['brand_context'] ?? '', $reference_image );
        if ( is_wp_error( $blueprint ) ) {
            $error_message = $blueprint->get_error_message();
            $blueprint = ( new Tyese_AiSite_Blueprint() )->fallback_blueprint();
            $builder   = new Tyese_AiSite_Elementor_Builder();
            $created   = $build_pages ? $builder->build( $blueprint, $builder_args ) : array();
            $warnings  = $builder->get_warnings();

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
                    'build_mode'    => $builder_args['mode'] ?? 'create_new',
                    'page_template' => $builder_args['page_template'] ?? 'elementor_canvas',
                    'warnings'      => $warnings,
                    'finished_at'   => current_time( 'mysql' ),
                ),
                false
            );
            return;
        }

        update_option( self::LAST_BLUEPRINT, $blueprint, false );
        $builder = new Tyese_AiSite_Elementor_Builder();
        $created = $build_pages ? $builder->build( $blueprint, $builder_args ) : array();
        $warnings = $builder->get_warnings();
        update_option( self::LAST_CREATED, $created, false );
        update_option(
            self::LAST_STATUS,
            array(
                'state'         => 'complete',
                'source'        => 'openai',
                'message'       => __( 'OpenAI generated a blueprint and Tyese aiSite finished the draft build.', 'tyese-aisite' ),
                'error'         => '',
                'created_count' => count( $created ),
                'build_mode'    => $builder_args['mode'] ?? 'create_new',
                'page_template' => $builder_args['page_template'] ?? 'elementor_canvas',
                'warnings'      => $warnings,
                'finished_at'   => current_time( 'mysql' ),
            ),
            false
        );
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
            'build_queued'   => __( 'Build queued. This page will return immediately and refresh while the background job runs.', 'tyese-aisite' ),
            'site_generated' => __( 'Site draft generated. Review the draft pages below before publishing.', 'tyese-aisite' ),
            'fallback_used'  => __( 'OpenAI could not generate a blueprint. Tyese aiSite finished and used the safe fallback draft instead; see Build Status below for the real error.', 'tyese-aisite' ),
            'failed'         => __( 'Tyese aiSite could not generate a blueprint.', 'tyese-aisite' ),
            'pages_repaired' => sprintf( __( 'Tyese aiSite repaired %d generated page(s).', 'tyese-aisite' ), absint( $_GET['tyese_aisite_repaired'] ?? 0 ) ),
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

        $state = $status['state'] ?? 'complete';
        $source = $status['source'] ?? 'unknown';
        $badge  = 'fallback' === $source ? __( 'Fallback draft', 'tyese-aisite' ) : ( 'pending' === $source ? __( 'Background build', 'tyese-aisite' ) : __( 'AI draft', 'tyese-aisite' ) );
        echo '<p><span class="tyese-aisite-status-badge is-' . esc_attr( $state ) . '">' . esc_html( ucwords( $state ) ) . '</span> <strong>' . esc_html( $badge ) . '</strong></p>';
        echo '<p>' . esc_html( $status['message'] ?? '' ) . '</p>';

        if ( ! empty( $status['queued_at'] ) ) {
            echo '<p><strong>' . esc_html__( 'Queued:', 'tyese-aisite' ) . '</strong> ' . esc_html( $status['queued_at'] ) . '</p>';
        }

        if ( ! empty( $status['started_at'] ) ) {
            echo '<p><strong>' . esc_html__( 'Started:', 'tyese-aisite' ) . '</strong> ' . esc_html( $status['started_at'] ) . '</p>';
        }

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

        if ( ! empty( $status['warnings'] ) && is_array( $status['warnings'] ) ) {
            echo '<div class="tyese-aisite-warning"><strong>' . esc_html__( 'Build warnings:', 'tyese-aisite' ) . '</strong><ul>';
            foreach ( $status['warnings'] as $warning ) {
                echo '<li>' . esc_html( $warning ) . '</li>';
            }
            echo '</ul></div>';
        }

        if ( ! empty( $status['repair_message'] ) ) {
            echo '<div class="tyese-aisite-warning">' . esc_html( $status['repair_message'] ) . '</div>';
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

    private function handle_reference_image_upload() {
        if ( empty( $_FILES['reference_image']['name'] ) || ! empty( $_FILES['reference_image']['error'] ) ) {
            return 0;
        }

        $file  = $_FILES['reference_image'];
        $check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
        $mime  = $check['type'] ?? '';

        if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_handle_upload( 'reference_image', 0 );
        return is_wp_error( $attachment_id ) ? 0 : absint( $attachment_id );
    }

    private function reference_image_data_url( $attachment_id ) {
        $path = get_attached_file( $attachment_id );
        if ( ! $path || ! file_exists( $path ) || filesize( $path ) > 8 * MB_IN_BYTES ) {
            return '';
        }

        $mime = get_post_mime_type( $attachment_id );
        if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
            return '';
        }

        $contents = file_get_contents( $path );
        if ( false === $contents ) {
            return '';
        }

        return 'data:' . $mime . ';base64,' . base64_encode( $contents );
    }

    private function repair_generated_pages() {
        $ids = array();

        foreach ( (array) get_option( self::LAST_CREATED, array() ) as $page ) {
            if ( ! empty( $page['id'] ) ) {
                $ids[] = absint( $page['id'] );
            }
        }

        $query = new WP_Query(
            array(
                'post_type'      => 'page',
                'post_status'    => 'any',
                'fields'         => 'ids',
                'posts_per_page' => 100,
                'meta_query'     => array(
                    array(
                        'key'     => '_tyese_aisite_blueprint',
                        'compare' => 'EXISTS',
                    ),
                ),
            )
        );

        $ids = array_values( array_unique( array_merge( $ids, array_map( 'absint', $query->posts ) ) ) );
        $count = 0;

        foreach ( $ids as $post_id ) {
            if ( $this->repair_elementor_page( $post_id ) ) {
                $count++;
            }
        }

        return $count;
    }

    private function repair_elementor_page( $post_id ) {
        $raw = get_post_meta( $post_id, '_elementor_data', true );
        if ( ! $raw ) {
            return false;
        }

        $data = json_decode( $raw, true );
        if ( ! is_array( $data ) ) {
            $data = json_decode( wp_unslash( $raw ), true );
        }

        if ( ! is_array( $data ) ) {
            return false;
        }

        $changed = false;
        $data = $this->flatten_legacy_sections( $data, $changed );
        $data = $this->sanitize_legacy_elementor_elements( $data, $changed );

        if ( ! $changed ) {
            return false;
        }

        update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
        update_post_meta(
            $post_id,
            '_elementor_page_settings',
            wp_slash(
                wp_json_encode(
                    array(
                        'background_background' => 'classic',
                        'background_color'      => '#ffffff',
                        'hide_title'            => 'yes',
                    )
                )
            )
        );
        update_post_meta( $post_id, '_tyese_aisite_repaired_at', current_time( 'mysql' ) );
        return true;
    }

    private function sanitize_legacy_elementor_elements( $elements, &$changed ) {
        $clean = array();

        foreach ( (array) $elements as $element ) {
            if ( ! is_array( $element ) || empty( $element['elType'] ) ) {
                continue;
            }

            if ( ! empty( $element['elements'] ) ) {
                $element['elements'] = $this->sanitize_legacy_elementor_elements( $element['elements'], $changed );
            }

            if ( 'section' === $element['elType'] ) {
                $element['settings'] = $this->safe_section_settings( $element['settings'] ?? array(), $changed );
            } elseif ( 'column' === $element['elType'] ) {
                $element['settings'] = $this->safe_column_settings( $element['settings'] ?? array(), $changed );
            } elseif ( 'widget' === $element['elType'] ) {
                $element = $this->safe_widget_element( $element, $changed );
            }

            $clean[] = $element;
        }

        return $clean;
    }

    private function safe_section_settings( $settings, &$changed ) {
        $allowed = array(
            'layout',
            'gap',
            'css_id',
            'css_classes',
            'padding',
            'background_background',
            'background_color',
            'z_index',
            'box_shadow_box_shadow_type',
            'box_shadow_box_shadow',
        );

        return $this->filter_settings( $settings, $allowed, $changed );
    }

    private function safe_column_settings( $settings, &$changed ) {
        $allowed = array(
            '_column_size',
            'content_position',
            'space_between_widgets',
            'padding',
        );

        return $this->filter_settings( $settings, $allowed, $changed );
    }

    private function filter_settings( $settings, $allowed, &$changed ) {
        $settings = (array) $settings;
        $filtered = array_intersect_key( $settings, array_flip( $allowed ) );

        if ( count( $filtered ) !== count( $settings ) ) {
            $changed = true;
        }

        return $filtered;
    }

    private function safe_widget_element( $element, &$changed ) {
        $widget_type = $element['widgetType'] ?? '';

        if ( 'html' === $widget_type ) {
            $html = $element['settings']['html'] ?? '';
            $element['widgetType'] = false !== strpos( $html, '<nav' ) ? 'text-editor' : 'spacer';
            $element['settings'] = 'text-editor' === $element['widgetType']
                ? array( 'editor' => wp_kses_post( $html ) )
                : array( 'space' => array( 'unit' => 'px', 'size' => 240 ) );
            $changed = true;
            return $element;
        }

        if ( 'divider' === $widget_type ) {
            $element['settings'] = array(
                'weight' => array( 'unit' => 'px', 'size' => 2 ),
            );
            $changed = true;
            return $element;
        }

        if ( 'spacer' === $widget_type && empty( $element['settings']['space'] ) ) {
            $element['settings'] = array( 'space' => array( 'unit' => 'px', 'size' => 240 ) );
            $changed = true;
        }

        return $element;
    }

    private function flatten_legacy_sections( $elements, &$changed ) {
        $clean = array();

        foreach ( (array) $elements as $element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }

            if ( 'section' === ( $element['elType'] ?? '' ) && $this->is_section_group_wrapper( $element ) ) {
                foreach ( $element['elements'][0]['elements'] as $child ) {
                    if ( is_array( $child ) && 'section' === ( $child['elType'] ?? '' ) ) {
                        $clean[] = $child;
                        $changed = true;
                    }
                }
                continue;
            }

            $clean[] = $element;
        }

        return $clean;
    }

    private function is_section_group_wrapper( $element ) {
        if ( empty( $element['elements'][0] ) || 'column' !== ( $element['elements'][0]['elType'] ?? '' ) ) {
            return false;
        }

        $children = $element['elements'][0]['elements'] ?? array();
        if ( empty( $children ) ) {
            return false;
        }

        foreach ( $children as $child ) {
            if ( is_array( $child ) && 'section' === ( $child['elType'] ?? '' ) ) {
                return true;
            }
        }

        return false;
    }
}
