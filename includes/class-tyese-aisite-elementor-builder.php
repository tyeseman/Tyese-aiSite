<?php
/**
 * Converts normalized blueprints into editable Elementor draft pages.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Tyese_AiSite_Elementor_Builder {
    public function build( $blueprint, $args = array() ) {
        $args = wp_parse_args(
            $args,
            array(
                'mode'              => 'create_new',
                'selected_pages'    => array(),
                'page_template'     => 'elementor_canvas',
                'set_homepage'      => false,
                'backup_originals'  => true,
            )
        );

        $created = array();
        $selected_pages = array_values( array_filter( array_map( 'absint', (array) $args['selected_pages'] ) ) );

        foreach ( array_values( $blueprint['pages'] ?? array() ) as $index => $page ) {
            $target_id = $selected_pages[ $index ] ?? 0;
            $post_id   = $this->prepare_post_for_page( $page, $target_id, $args );

            if ( is_wp_error( $post_id ) ) {
                continue;
            }

            $elementor_data = $this->page_elements( $page );

            update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
            update_post_meta( $post_id, '_elementor_template_type', 'wp-page' );
            update_post_meta( $post_id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );
            update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $elementor_data ) ) );
            update_post_meta( $post_id, '_wp_page_template', $this->allowed_template( $args['page_template'] ) );
            update_post_meta( $post_id, '_tyese_aisite_blueprint', wp_slash( wp_json_encode( $page ) ) );
            update_post_meta( $post_id, '_tyese_aisite_build_mode', sanitize_key( $args['mode'] ) );

            $created[] = array(
                'id'    => $post_id,
                'title' => get_the_title( $post_id ),
                'edit'  => get_edit_post_link( $post_id, '' ),
                'view'  => get_permalink( $post_id ),
            );
        }

        if ( ! empty( $args['set_homepage'] ) && ! empty( $created[0]['id'] ) ) {
            update_option( 'show_on_front', 'page' );
            update_option( 'page_on_front', absint( $created[0]['id'] ) );
        }

        return $created;
    }

    private function prepare_post_for_page( $page, $target_id, $args ) {
        $mode = sanitize_key( $args['mode'] );

        if ( $target_id && in_array( $mode, array( 'update_existing', 'replace_selected' ), true ) ) {
            $target = get_post( $target_id );
            if ( ! $target || 'page' !== $target->post_type ) {
                return new WP_Error( 'tyese_aisite_invalid_target', __( 'Selected target page is not valid.', 'tyese-aisite' ) );
            }

            if ( ! empty( $args['backup_originals'] ) ) {
                $this->save_backup_meta( $target_id );
            }

            if ( 'update_existing' === $mode ) {
                return wp_insert_post(
                    array(
                        'post_title'   => sprintf( __( '%s - AI Draft', 'tyese-aisite' ), $target->post_title ),
                        'post_name'    => sanitize_title( $target->post_name . '-ai-draft' ),
                        'post_type'    => 'page',
                        'post_status'  => 'draft',
                        'post_parent'  => $target_id,
                        'post_content' => '',
                    ),
                    true
                );
            }

            wp_update_post(
                array(
                    'ID'          => $target_id,
                    'post_status' => 'draft',
                    'post_title'  => $page['title'],
                    'post_name'   => $page['slug'],
                )
            );

            return $target_id;
        }

        return wp_insert_post(
            array(
                'post_title'   => $page['title'],
                'post_name'    => $page['slug'],
                'post_type'    => 'page',
                'post_status'  => 'draft',
                'post_content' => '',
            ),
            true
        );
    }

    private function save_backup_meta( $post_id ) {
        $backup = array(
            'time'                 => current_time( 'mysql' ),
            'post_title'           => get_the_title( $post_id ),
            'post_content'         => get_post_field( 'post_content', $post_id ),
            '_elementor_data'      => get_post_meta( $post_id, '_elementor_data', true ),
            '_elementor_edit_mode' => get_post_meta( $post_id, '_elementor_edit_mode', true ),
            '_wp_page_template'    => get_post_meta( $post_id, '_wp_page_template', true ),
        );

        update_post_meta( $post_id, '_tyese_aisite_backup', wp_slash( wp_json_encode( $backup ) ) );
    }

    private function allowed_template( $template ) {
        $template = sanitize_key( $template );
        $allowed  = array( 'elementor_canvas', 'elementor_header_footer', 'default' );

        return in_array( $template, $allowed, true ) ? $template : 'elementor_canvas';
    }

    private function page_elements( $page ) {
        $elements = array();

        foreach ( $page['sections'] ?? array() as $section ) {
            $elements[] = array(
                'id'       => $this->element_id(),
                'elType'   => 'section',
                'settings' => array(
                    'layout' => 'full_width',
                    'gap'    => 'no',
                ),
                'elements' => array(
                    array(
                        'id'       => $this->element_id(),
                        'elType'   => 'column',
                        'settings' => array( '_column_size' => 100 ),
                        'elements' => $this->section_widgets( $section ),
                    ),
                ),
            );
        }

        return $elements;
    }

    private function section_widgets( $section ) {
        $widgets = array();
        $requested = $section['widgets'] ?? array();

        if ( empty( $requested ) ) {
            $requested = array(
                array( 'widget' => 'heading', 'settings' => array() ),
                array( 'widget' => 'text-editor', 'settings' => array() ),
            );
        }

        foreach ( $requested as $widget ) {
            $widgets[] = $this->widget_element( $widget['widget'], $section, $widget['settings'] ?? array() );
        }

        return $widgets;
    }

    private function widget_element( $widget_type, $section, $settings ) {
        $widget_type = sanitize_key( $widget_type );
        $settings    = $this->settings_for_widget( $widget_type, $section, $settings );

        return array(
            'id'         => $this->element_id(),
            'elType'     => 'widget',
            'widgetType' => $widget_type,
            'settings'   => $settings,
            'elements'   => array(),
        );
    }

    private function settings_for_widget( $widget_type, $section, $settings ) {
        $headline = $section['headline'] ?? '';
        $body     = $section['body'] ?? '';
        $cta_text = $section['cta_text'] ?? '';
        $cta_url  = $section['cta_url'] ?? '';

        if ( 0 === strpos( $widget_type, 'tyese_' ) ) {
            return array_merge(
                array(
                    'title'       => $headline,
                    'subtitle'    => ucwords( str_replace( '_', ' ', $section['type'] ?? 'section' ) ),
                    'content'     => $body,
                    'button_text' => $cta_text ?: __( 'Learn More', 'tyese-aisite' ),
                    'link'        => array( 'url' => $cta_url ?: '#' ),
                    'items'       => $this->items_from_body( $body ),
                ),
                (array) $settings
            );
        }

        switch ( $widget_type ) {
            case 'heading':
                return array_merge( array( 'title' => $headline, 'header_size' => 'h2' ), (array) $settings );
            case 'text-editor':
                return array_merge( array( 'editor' => wpautop( $body ) ), (array) $settings );
            case 'button':
                return array_merge( array( 'text' => $cta_text ?: __( 'Learn More', 'tyese-aisite' ), 'link' => array( 'url' => $cta_url ?: '#' ) ), (array) $settings );
            case 'icon-list':
                return array_merge( array( 'icon_list' => $this->icon_list_from_body( $body ) ), (array) $settings );
            case 'html':
                return array_merge( array( 'html' => '<div>' . esc_html( $headline ) . '</div>' ), (array) $settings );
            default:
                return array_merge( array( 'title' => $headline, 'editor' => wpautop( $body ) ), (array) $settings );
        }
    }

    private function items_from_body( $body ) {
        $sentences = preg_split( '/(?<=[.!?])\s+/', wp_strip_all_tags( $body ) );
        $items     = array();

        foreach ( array_slice( array_filter( $sentences ), 0, 4 ) as $sentence ) {
            $items[] = array(
                'item_title' => wp_trim_words( $sentence, 5, '' ),
                'item_text'  => $sentence,
            );
        }

        return $items;
    }

    private function icon_list_from_body( $body ) {
        $items = array();
        foreach ( $this->items_from_body( $body ) as $item ) {
            $items[] = array(
                'text'          => $item['item_text'],
                'selected_icon' => array( 'value' => 'fas fa-check', 'library' => 'fa-solid' ),
            );
        }

        return $items;
    }

    private function element_id() {
        return substr( md5( uniqid( 'tyese', true ) ), 0, 7 );
    }
}
