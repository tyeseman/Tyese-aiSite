<?php
/**
 * Discovers Elementor widgets available to the AI builder.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Tyese_AiSite_Widgets {
    public function inventory() {
        $widgets = array();

        if ( did_action( 'elementor/loaded' ) && class_exists( '\Elementor\Plugin' ) ) {
            $manager = \Elementor\Plugin::instance()->widgets_manager;
            if ( $manager && method_exists( $manager, 'get_widget_types' ) ) {
                foreach ( $manager->get_widget_types() as $name => $widget ) {
                    $widgets[ $name ] = array(
                        'name'       => $name,
                        'title'      => method_exists( $widget, 'get_title' ) ? $widget->get_title() : $name,
                        'categories' => method_exists( $widget, 'get_categories' ) ? $widget->get_categories() : array(),
                        'source'     => 0 === strpos( $name, 'tyese_' ) ? 'tyese' : 'elementor',
                    );
                }
            }
        }

        foreach ( $this->fallback_widgets() as $name => $label ) {
            if ( ! isset( $widgets[ $name ] ) ) {
                $widgets[ $name ] = array(
                    'name'       => $name,
                    'title'      => $label,
                    'categories' => array( 'basic' ),
                    'source'     => 0 === strpos( $name, 'tyese_' ) ? 'tyese-fallback' : 'elementor-fallback',
                );
            }
        }

        return $widgets;
    }

    public function names_for_prompt() {
        $names = array();
        foreach ( $this->inventory() as $widget ) {
            $names[] = $widget['name'] . ' (' . $widget['title'] . ')';
        }

        return array_slice( $names, 0, 160 );
    }

    private function fallback_widgets() {
        return array(
            'heading'                  => __( 'Heading', 'tyese-aisite' ),
            'text-editor'              => __( 'Text Editor', 'tyese-aisite' ),
            'image'                    => __( 'Image', 'tyese-aisite' ),
            'button'                   => __( 'Button', 'tyese-aisite' ),
            'icon-list'                => __( 'Icon List', 'tyese-aisite' ),
            'divider'                  => __( 'Divider', 'tyese-aisite' ),
            'spacer'                   => __( 'Spacer', 'tyese-aisite' ),
            'google_maps'              => __( 'Google Maps', 'tyese-aisite' ),
            'shortcode'                => __( 'Shortcode', 'tyese-aisite' ),
            'html'                     => __( 'HTML', 'tyese-aisite' ),
            'tyese_hero_block'         => __( 'Tyese Hero Block', 'tyese-aisite' ),
            'tyese_business_hero'      => __( 'Tyese Business Hero', 'tyese-aisite' ),
            'tyese_church_hero'        => __( 'Tyese Church Hero', 'tyese-aisite' ),
            'tyese_ministry_cards'     => __( 'Tyese Ministry Cards', 'tyese-aisite' ),
            'tyese_sermon_grid'        => __( 'Tyese Sermon Grid', 'tyese-aisite' ),
            'tyese_event_list'         => __( 'Tyese Event List', 'tyese-aisite' ),
            'tyese_contact_section'    => __( 'Tyese Contact Section', 'tyese-aisite' ),
            'tyese_newsletter_signup'  => __( 'Tyese Newsletter Signup', 'tyese-aisite' ),
            'tyese_announcement_bar'   => __( 'Tyese Announcement Bar', 'tyese-aisite' ),
        );
    }
}
