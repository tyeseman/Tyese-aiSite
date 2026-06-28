<?php
/**
 * Scans the current WordPress site before AI builds pages.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Tyese_AiSite_Site_Scanner {
    public function scan() {
        $theme = wp_get_theme();

        return array(
            'theme'    => array(
                'name'      => $theme->get( 'Name' ),
                'version'   => $theme->get( 'Version' ),
                'template'  => get_template(),
                'stylesheet'=> get_stylesheet(),
            ),
            'homepage' => array(
                'show_on_front' => get_option( 'show_on_front' ),
                'page_on_front' => absint( get_option( 'page_on_front' ) ),
            ),
            'menus'    => $this->menus(),
            'pages'    => $this->pages(),
        );
    }

    public function pages() {
        $posts = get_posts(
            array(
                'post_type'      => 'page',
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => 100,
                'orderby'        => 'menu_order title',
                'order'          => 'ASC',
            )
        );

        $pages = array();
        foreach ( $posts as $post ) {
            $pages[] = array(
                'id'           => $post->ID,
                'title'        => get_the_title( $post ),
                'slug'         => $post->post_name,
                'status'       => $post->post_status,
                'is_elementor' => 'builder' === get_post_meta( $post->ID, '_elementor_edit_mode', true ),
                'template'     => get_post_meta( $post->ID, '_wp_page_template', true ) ?: 'default',
                'edit'         => get_edit_post_link( $post->ID, '' ),
                'view'         => get_permalink( $post->ID ),
            );
        }

        return $pages;
    }

    public function prompt_context() {
        $scan  = $this->scan();
        $lines = array();
        $lines[] = 'Active theme: ' . $scan['theme']['name'] . ' ' . $scan['theme']['version'];
        $lines[] = 'Homepage mode: ' . $scan['homepage']['show_on_front'];
        $lines[] = 'Existing pages:';

        foreach ( $scan['pages'] as $page ) {
            $lines[] = sprintf(
                '- %s (ID %d, slug %s, status %s, Elementor %s, template %s)',
                $page['title'],
                $page['id'],
                $page['slug'],
                $page['status'],
                $page['is_elementor'] ? 'yes' : 'no',
                $page['template']
            );
        }

        return implode( "\n", $lines );
    }

    private function menus() {
        $menus = array();
        foreach ( wp_get_nav_menus() as $menu ) {
            $menus[] = array(
                'id'    => $menu->term_id,
                'name'  => $menu->name,
                'count' => $menu->count,
            );
        }

        return $menus;
    }
}
