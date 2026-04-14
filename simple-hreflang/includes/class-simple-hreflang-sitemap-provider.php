<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Simple_Hreflang_Sitemap_Provider {
    private $repository;

    public function __construct( Simple_Hreflang_Repository $repository ) {
        $this->repository = $repository;
    }

    public function register_rewrite() {
        add_rewrite_rule( '^hreflang-sitemap\.xml$', 'index.php?simple_hreflang_sitemap=1', 'top' );
        add_filter(
            'query_vars',
            static function ( $vars ) {
                $vars[] = 'simple_hreflang_sitemap';
                return $vars;
            }
        );
    }

    public function maybe_render() {
        if ( '1' !== get_query_var( 'simple_hreflang_sitemap' ) ) {
            return;
        }

        $groups = $this->repository->get_valid_groups_for_sitemap();

        status_header( 200 );
        header( 'Content-Type: application/xml; charset=UTF-8' );

        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">';

        foreach ( $groups as $entries ) {
            $x_default_url = '';
            foreach ( $entries as $entry ) {
                if ( $entry['is_default'] ) {
                    $x_default_url = $entry['permalink'];
                    break;
                }
            }

            foreach ( $entries as $entry ) {
                echo "\n  <url>\n";
                echo '    <loc>' . esc_url( $entry['permalink'] ) . "</loc>\n";

                foreach ( $entries as $alternate ) {
                    echo '    <xhtml:link rel="alternate" hreflang="' . esc_attr( $alternate['hreflang'] ) . '" href="' . esc_url( $alternate['permalink'] ) . '" />' . "\n";
                }

                if ( ! empty( $x_default_url ) ) {
                    echo '    <xhtml:link rel="alternate" hreflang="x-default" href="' . esc_url( $x_default_url ) . '" />' . "\n";
                }

                echo "  </url>\n";
            }
        }

        echo "</urlset>";
        exit;
    }

    public static function activate() {
        add_rewrite_rule( '^hreflang-sitemap\.xml$', 'index.php?simple_hreflang_sitemap=1', 'top' );
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }
}
