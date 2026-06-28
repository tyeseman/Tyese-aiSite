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
                        'required'             => array( 'title', 'slug', 'sections' ),
                        'properties'           => array(
                            'title'    => array( 'type' => 'string' ),
                            'slug'     => array( 'type' => 'string' ),
                            'sections' => array(
                                'type'  => 'array',
                                'items' => array(
                                    'type'                 => 'object',
                                    'additionalProperties' => false,
                                    'required'             => array( 'type', 'headline', 'body', 'widgets' ),
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
                                                    'settings' => array( 'type' => 'object' ),
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
                    'sections' => array(
                        array(
                            'type'       => 'hero',
                            'headline'   => 'Build a Better Website With Tyese aiSite',
                            'body'       => 'A polished Elementor-ready homepage draft generated from your instructions.',
                            'cta_text'   => 'Get Started',
                            'cta_url'    => '#contact',
                            'image_hint' => 'professional website strategy session',
                            'widgets'    => array(
                                array(
                                    'widget'   => 'tyese_hero_block',
                                    'settings' => array(),
                                ),
                            ),
                        ),
                    ),
                ),
            ),
        );
    }
}
