<?php
/**
 * Converts normalized blueprints into editable Elementor draft pages.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Tyese_AiSite_Elementor_Builder {
    public function build( $blueprint ) {
        $created = array();

        foreach ( $blueprint['pages'] ?? array() as $page ) {
            $post_id = wp_insert_post(
                array(
                    'post_title'   => $page['title'],
                    'post_name'    => $page['slug'],
                    'post_type'    => 'page',
                    'post_status'  => 'draft',
                    'post_content' => '',
                ),
                true
            );

            if ( is_wp_error( $post_id ) ) {
                continue;
            }

            $elementor_data = $this->page_elements( $page );

            update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
            update_post_meta( $post_id, '_elementor_template_type', 'wp-page' );
            update_post_meta( $post_id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );
            update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $elementor_data ) ) );
            update_post_meta( $post_id, '_wp_page_template', 'elementor_canvas' );
            update_post_meta( $post_id, '_tyese_aisite_blueprint', wp_slash( wp_json_encode( $page ) ) );

            $created[] = array(
                'id'    => $post_id,
                'title' => get_the_title( $post_id ),
                'edit'  => get_edit_post_link( $post_id, '' ),
                'view'  => get_permalink( $post_id ),
            );
        }

        return $created;
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
