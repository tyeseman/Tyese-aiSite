<?php
/**
 * Universal page and section template rules for Tyese aiSite.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Tyese_AiSite_Template_Registry {
    public function required_menu_items() {
        return array(
            array( 'label' => __( 'Home', 'tyese-aisite' ), 'anchor' => 'top' ),
            array( 'label' => __( 'About', 'tyese-aisite' ), 'anchor' => 'about' ),
            array( 'label' => __( 'Services', 'tyese-aisite' ), 'anchor' => 'services' ),
            array( 'label' => __( 'News', 'tyese-aisite' ), 'anchor' => 'news' ),
            array( 'label' => __( 'Events', 'tyese-aisite' ), 'anchor' => 'events' ),
            array( 'label' => __( 'Contact', 'tyese-aisite' ), 'anchor' => 'contact' ),
        );
    }

    public function page_types() {
        return array(
            'home',
            'about',
            'services',
            'team',
            'platform',
            'product',
            'pricing',
            'portfolio',
            'news',
            'events',
            'contact',
            'landing',
            'generic',
        );
    }

    public function design_system() {
        return array(
            'content_width' => 1180,
            'section_padding_desktop' => array( 72, 42, 72, 42 ),
            'section_padding_tablet'  => array( 54, 28, 54, 28 ),
            'section_padding_mobile'  => array( 42, 18, 42, 18 ),
            'card_gap' => 24,
            'hero_image_ratio' => '4 / 3',
            'card_image_ratio' => '3 / 2',
            'portrait_image_ratio' => '4 / 5',
            'hero_min_height' => 520,
            'image_max_height' => 460,
        );
    }

    public function section_anchor( $type, $headline = '' ) {
        $type = sanitize_key( $type ?: 'section' );
        $map = array(
            'hero'         => 'top',
            'about'        => 'about',
            'content'      => 'about',
            'services'     => 'services',
            'features'     => 'services',
            'platform'     => 'services',
            'pillars'      => 'services',
            'team'         => 'team',
            'staff'        => 'team',
            'candidate'    => 'team',
            'testimonials' => 'testimonials',
            'pricing'      => 'pricing',
            'portfolio'    => 'work',
            'gallery'      => 'gallery',
            'news'         => 'news',
            'blog'         => 'news',
            'events'       => 'events',
            'contact'      => 'contact',
            'cta'          => 'contact',
        );

        return $map[ $type ] ?? sanitize_title( $headline ?: $type );
    }

    public function default_sections_for_page_type( $page_type ) {
        switch ( sanitize_key( $page_type ) ) {
            case 'contact':
                return array( 'hero', 'contact', 'cta' );
            case 'news':
                return array( 'hero', 'news', 'cta' );
            case 'events':
                return array( 'hero', 'events', 'cta' );
            case 'services':
            case 'platform':
                return array( 'hero', 'services', 'features', 'cta' );
            case 'team':
            case 'about':
                return array( 'hero', 'about', 'team', 'cta' );
            case 'product':
            case 'landing':
                return array( 'hero', 'features', 'pricing', 'testimonials', 'cta' );
            default:
                return array( 'hero', 'about', 'services', 'team', 'news', 'events', 'cta' );
        }
    }
}
