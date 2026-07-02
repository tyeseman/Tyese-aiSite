<?php
/**
 * Validates and normalizes AI site blueprints.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Tyese_AiSite_Blueprint {
    public function schema() {
        return array(
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => array( 'site_name', 'brand', 'pages' ),
            'properties'           => array(
                'site_name' => array( 'type' => 'string' ),
                'brand'     => array(
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'required'             => array( 'colors', 'fonts', 'tone' ),
                    'properties'           => array(
                        'colors' => array(
                            'type'  => 'array',
                            'items' => array( 'type' => 'string' ),
                        ),
                        'fonts'  => array(
                            'type'  => 'array',
                            'items' => array( 'type' => 'string' ),
                        ),
                        'tone'   => array( 'type' => 'string' ),
                    ),
                ),
                'pages'     => array(
                    'type'  => 'array',
                    'items' => array(
                        'type'                 => 'object',
                        'additionalProperties' => false,
                        'required'             => array( 'title', 'slug', 'page_type', 'sections' ),
                        'properties'           => array(
                            'title'    => array( 'type' => 'string' ),
                            'slug'     => array( 'type' => 'string' ),
                            'page_type' => array( 'type' => 'string' ),
                            'sections' => array(
                                'type'  => 'array',
                                'items' => array(
                                    'type'                 => 'object',
                                    'additionalProperties' => false,
                                    'required'             => array( 'type', 'headline', 'body', 'cta_text', 'cta_url', 'image_hint', 'widgets' ),
                                    'properties'           => array(
                                        'type'       => array( 'type' => 'string' ),
                                        'headline'   => array( 'type' => 'string' ),
                                        'body'       => array( 'type' => 'string' ),
                                        'cta_text'   => array( 'type' => 'string' ),
                                        'cta_url'    => array( 'type' => 'string' ),
                                        'image_hint' => array( 'type' => 'string' ),
                                        'widgets'    => array(
                                            'type'  => 'array',
                                            'items' => array(
                                                'type'                 => 'object',
                                                'additionalProperties' => false,
                                                'required'             => array( 'widget', 'settings' ),
                                                'properties'           => array(
                                                    'widget'   => array( 'type' => 'string' ),
                                                    'settings' => array(
                                                        'type'                 => 'object',
                                                        'additionalProperties' => false,
                                                        'properties'           => new stdClass(),
                                                    ),
                                                ),
                                            ),
                                        ),
                                    ),
                                ),
                            ),
                        ),
                    ),
                ),
            ),
        );
    }

    public function normalize( $blueprint ) {
        if ( ! is_array( $blueprint ) ) {
            return $this->fallback_blueprint();
        }

        $pages = array();
        foreach ( $blueprint['pages'] ?? array() as $page ) {
            $sections = array();
            foreach ( $page['sections'] ?? array() as $section ) {
                $sections[] = array(
                    'type'       => sanitize_key( $section['type'] ?? 'content' ),
                    'headline'   => sanitize_text_field( $section['headline'] ?? '' ),
                    'body'       => wp_kses_post( $section['body'] ?? '' ),
                    'cta_text'   => sanitize_text_field( $section['cta_text'] ?? '' ),
                    'cta_url'    => esc_url_raw( $section['cta_url'] ?? '' ),
                    'image_hint' => sanitize_text_field( $section['image_hint'] ?? '' ),
                    'widgets'    => $this->normalize_widgets( $section['widgets'] ?? array() ),
                );
            }

            $pages[] = array(
                'title'    => sanitize_text_field( $page['title'] ?? __( 'Generated Page', 'tyese-aisite' ) ),
                'slug'     => sanitize_title( $page['slug'] ?? $page['title'] ?? 'generated-page' ),
                'page_type'=> $this->normalize_page_type( $page['page_type'] ?? $page['slug'] ?? $page['title'] ?? 'generic' ),
                'sections' => $sections,
            );
        }

        if ( empty( $pages ) ) {
            return $this->fallback_blueprint();
        }

        return array(
            'site_name' => sanitize_text_field( $blueprint['site_name'] ?? get_bloginfo( 'name' ) ),
            'brand'     => array(
                'colors' => array_map( 'sanitize_text_field', $blueprint['brand']['colors'] ?? array( '#123c7c', '#b81d24', '#172033' ) ),
                'fonts'  => array_map( 'sanitize_text_field', $blueprint['brand']['fonts'] ?? array( 'Inter', 'Georgia' ) ),
                'tone'   => sanitize_text_field( $blueprint['brand']['tone'] ?? 'clear, warm, and trustworthy' ),
            ),
            'pages'     => $pages,
        );
    }

    private function normalize_widgets( $widgets ) {
        $clean = array();
        foreach ( (array) $widgets as $widget ) {
            $clean[] = array(
                'widget'   => sanitize_key( $widget['widget'] ?? 'heading' ),
                'settings' => is_array( $widget['settings'] ?? null ) ? $widget['settings'] : array(),
            );
        }

        return $clean;
    }

    private function normalize_page_type( $page_type ) {
        $page_type = sanitize_key( $page_type );
        $registry = class_exists( 'Tyese_AiSite_Template_Registry' ) ? new Tyese_AiSite_Template_Registry() : null;
        $allowed = $registry ? $registry->page_types() : array( 'home', 'about', 'services', 'team', 'platform', 'product', 'pricing', 'portfolio', 'news', 'events', 'contact', 'landing', 'generic' );

        if ( in_array( $page_type, $allowed, true ) ) {
            return $page_type;
        }

        if ( false !== strpos( $page_type, 'service' ) ) {
            return 'services';
        }

        if ( false !== strpos( $page_type, 'blog' ) || false !== strpos( $page_type, 'article' ) ) {
            return 'news';
        }

        return 'generic';
    }

    public function fallback_blueprint() {
        return array(
            'site_name' => get_bloginfo( 'name' ) ?: 'Tyese aiSite',
            'brand'     => array(
                'colors' => array( '#123c7c', '#b81d24', '#172033' ),
                'fonts'  => array( 'Inter', 'Georgia' ),
                'tone'   => 'clear, warm, and trustworthy',
            ),
            'pages'     => array(
                array(
                    'title'    => 'Home',
                    'slug'     => 'home',
                    'page_type'=> 'home',
                    'sections' => array(
                        array(
                            'type'       => 'hero',
                            'headline'   => 'A New Website, Built With Purpose',
                            'body'       => 'A polished Elementor-ready homepage draft generated from your instructions, with a clear message, structured sections, and editable content.',
                            'cta_text'   => 'Get Started',
                            'cta_url'    => '#contact',
                            'image_hint' => 'community leadership and service',
                            'widgets'    => array(),
                        ),
                        array(
                            'type'       => 'platform',
                            'headline'   => 'Platform at a Glance',
                            'body'       => 'Tone. Unity. Service. Impact. These priorities shape the message, sections, and calls to action across the generated page.',
                            'cta_text'   => '',
                            'cta_url'    => '',
                            'image_hint' => '',
                            'widgets'    => array(),
                        ),
                        array(
                            'type'       => 'team',
                            'headline'   => 'Meet the Team',
                            'body'       => 'Introduce the people behind the work with a structured, scan-friendly team section.',
                            'cta_text'   => '',
                            'cta_url'    => '',
                            'image_hint' => 'team portraits',
                            'widgets'    => array(),
                        ),
                        array(
                            'type'       => 'content',
                            'headline'   => 'Our Commitment',
                            'body'       => 'Use this section to explain the mission, values, and practical commitments in a way visitors can quickly understand.',
                            'cta_text'   => '',
                            'cta_url'    => '',
                            'image_hint' => 'community conversation',
                            'widgets'    => array(),
                        ),
                        array(
                            'type'       => 'cta',
                            'headline'   => 'Get Involved',
                            'body'       => 'Invite visitors to volunteer, contact the team, attend an event, or take the next step.',
                            'cta_text'   => 'Volunteer Now',
                            'cta_url'    => '#contact',
                            'image_hint' => '',
                            'widgets'    => array(),
                        ),
                    ),
                ),
            ),
        );
    }
}
