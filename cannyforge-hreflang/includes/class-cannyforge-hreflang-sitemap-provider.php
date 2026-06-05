<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CannyForge_Hreflang_Sitemap_Provider {
    const XSL_CSS_PLACEHOLDER = '__CANNYFORGE_HREFLANG_SITEMAP_CSS__';

    private $repository;

    public function __construct( CannyForge_Hreflang_Repository $repository ) {
        $this->repository = $repository;
    }

    public function register_rewrite() {
        add_rewrite_rule( '^hreflang-sitemap\.xml$', 'index.php?cannyforge_hreflang_sitemap=1', 'top' );
        add_filter(
            'query_vars',
            static function ( $vars ) {
                $vars[] = 'cannyforge_hreflang_sitemap';
                $vars[] = 'cannyforge_hreflang_sitemap_xsl';
                return $vars;
            }
        );
    }

    /**
     * Serve the sitemap XSL with an absolute stylesheet URL.
     *
     * Browsers resolve relative URLs in transformed HTML against the sitemap document URL,
     * so a relative path in the static .xsl file never loads the plugin CSS.
     */
    public function maybe_serve_xsl() {
        if ( '1' !== get_query_var( 'cannyforge_hreflang_sitemap_xsl' ) ) {
            return;
        }

        $xsl_path = CANNYFORGE_HREFLANG_PATH . 'assets/xml/cannyforge-hreflang-sitemap.xsl';
        if ( ! is_readable( $xsl_path ) ) {
            status_header( 404 );
            exit;
        }

        $css_path = CANNYFORGE_HREFLANG_PATH . 'assets/css/cannyforge-hreflang-sitemap.css';
        $css_ver  = is_readable( $css_path ) ? (string) filemtime( $css_path ) : CANNYFORGE_HREFLANG_VERSION;
        $css_url  = add_query_arg( 'ver', $css_ver, CANNYFORGE_HREFLANG_URL . 'assets/css/cannyforge-hreflang-sitemap.css' );

        $body = (string) file_get_contents( $xsl_path );
        $body = str_replace( self::XSL_CSS_PLACEHOLDER, esc_url( $css_url ), $body );

        status_header( 200 );
        nocache_headers();
        header( 'Content-Type: application/xml; charset=UTF-8' );

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static XSL from plugin filesystem; escaping would break XML. Placeholder replaced with esc_url() above.
        echo $body;
        exit;
    }

    public function maybe_render() {
        if ( '1' !== get_query_var( 'cannyforge_hreflang_sitemap' ) ) {
            return;
        }

        $groups = $this->repository->get_valid_groups_for_sitemap();

        if ( empty( $groups ) ) {
            status_header( 404 );
            echo '<?xml version="1.0" encoding="UTF-8"?><error>Not Found</error>';
            exit;
        }

        status_header( 200 );
        header( 'Content-Type: application/xml; charset=UTF-8' );

        $xsl_url = add_query_arg( 'cannyforge_hreflang_sitemap_xsl', '1', home_url( '/' ) );

        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        echo "<?xml-stylesheet type=\"text/xsl\" href=\"" . esc_url( $xsl_url ) . "\"?>\n";
        echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\" xmlns:xhtml=\"http://www.w3.org/1999/xhtml\">\n";

        foreach ( $groups as $group_key => $entries ) {
            $x_default_url = '';
            foreach ( $entries as $entry ) {
                if ( $entry['is_default'] ) {
                    $x_default_url = $entry['permalink'];
                    break;
                }
            }

            $group_token = self::sitemap_group_comment_token( (string) $group_key );

            foreach ( $entries as $entry ) {
                echo '<!--cannyforge-hreflang-group:' . esc_html( $group_token ) . "-->\n";
                echo "  <url>\n";
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

        echo "</urlset>\n";
        exit;
    }

    public static function activate() {
        add_rewrite_rule( '^hreflang-sitemap\.xml$', 'index.php?cannyforge_hreflang_sitemap=1', 'top' );
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Safe token for XML comment + XSL grouping (no --, slug-safe).
     */
    private static function sitemap_group_comment_token( $group_key ) {
        $token = sanitize_title( $group_key );

        if ( '' === $token ) {
            return 'group';
        }

        return str_replace( '--', '-', $token );
    }
}
