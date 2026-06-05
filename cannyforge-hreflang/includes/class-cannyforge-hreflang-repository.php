<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CannyForge_Hreflang_Repository {
    const META_GROUP      = 'cannyforge_hreflang_group';
    const META_LANGUAGE   = 'cannyforge_hreflang_lang';
    const META_REGION     = 'cannyforge_hreflang_region';
    const META_X_DEFAULT  = 'cannyforge_hreflang_is_default';
    const OPTION_SETTINGS = 'cannyforge_hreflang_settings';

    public function get_settings() {
        $defaults = array(
            'post_types'      => array( 'page' ),
            'min_group_size'  => 2,
            'include_private' => 0,
        );

        $settings = get_option( self::OPTION_SETTINGS, array() );
        $settings = is_array( $settings ) ? $settings : array();

        return wp_parse_args( $settings, $defaults );
    }

    public function get_enabled_post_types() {
        $settings   = $this->get_settings();
        $post_types = isset( $settings['post_types'] ) && is_array( $settings['post_types'] ) ? $settings['post_types'] : array( 'page' );
        $post_types = array_filter( array_map( 'sanitize_key', $post_types ) );

        return empty( $post_types ) ? array( 'page' ) : $post_types;
    }

    public function update_post_meta( $post_id, $group, $language, $region, $is_default ) {
        if ( ! empty( $group ) ) {
            update_post_meta( $post_id, self::META_GROUP, $group );
        } else {
            delete_post_meta( $post_id, self::META_GROUP );
        }

        if ( ! empty( $language ) ) {
            update_post_meta( $post_id, self::META_LANGUAGE, $language );
        } else {
            delete_post_meta( $post_id, self::META_LANGUAGE );
        }

        if ( ! empty( $region ) ) {
            update_post_meta( $post_id, self::META_REGION, $region );
        } else {
            delete_post_meta( $post_id, self::META_REGION );
        }

        if ( $is_default ) {
            update_post_meta( $post_id, self::META_X_DEFAULT, '1' );
            $this->clear_group_x_default_on_other_posts( $post_id, $group );
        } else {
            delete_post_meta( $post_id, self::META_X_DEFAULT );
        }

    }

    public function clear_group_x_default_on_other_posts( $post_id, $group ) {
        if ( empty( $group ) ) {
            return;
        }

        $posts = get_posts(
            array(
                'post_type'      => $this->get_enabled_post_types(),
                'post_status'    => array( 'publish', 'private', 'draft', 'pending' ),
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_key'       => self::META_GROUP,
                'meta_value'     => $group,
            )
        );

        foreach ( $posts as $other_post_id ) {
            if ( (int) $post_id === (int) $other_post_id ) {
                continue;
            }

            delete_post_meta( $other_post_id, self::META_X_DEFAULT );
        }
    }

    public function get_grouped_posts() {
        $posts = get_posts(
            array(
                'post_type'      => $this->get_enabled_post_types(),
                'post_status'    => array( 'publish', 'private', 'draft', 'pending' ),
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
                'meta_key'       => self::META_GROUP,
            )
        );

        $rows = array();

        foreach ( $posts as $post ) {
            $rows[] = $this->hydrate_post( $post );
        }

        return $rows;
    }

    public function get_valid_groups_for_sitemap() {
        $settings       = $this->get_settings();
        // Hreflang alternates require at least two distinct URLs; never emit single-URL "clusters".
        $min_group_size = max( 2, (int) $settings['min_group_size'] );
        $groups         = array();

        foreach ( $this->get_grouped_posts() as $row ) {
            if ( 'publish' !== $row['post_status'] ) {
                continue;
            }

            if ( empty( $row['group'] ) || empty( $row['language'] ) || empty( $row['permalink'] ) ) {
                continue;
            }

            $groups[ $row['group'] ][] = $row;
        }

        foreach ( $groups as $group_key => $entries ) {
            if ( count( $entries ) < $min_group_size ) {
                unset( $groups[ $group_key ] );
                continue;
            }

            $unique_entries  = array();
            $x_default_count = 0;

            foreach ( $entries as $entry ) {
                if ( empty( $entry['hreflang'] ) ) {
                    unset( $groups[ $group_key ] );
                    continue 2;
                }

                if ( isset( $unique_entries[ $entry['hreflang'] ] ) ) {
                    continue;
                }

                $unique_entries[ $entry['hreflang'] ] = $entry;

                if ( $entry['is_default'] ) {
                    ++$x_default_count;
                }
            }

            if ( $x_default_count > 1 ) {
                unset( $groups[ $group_key ] );
                continue;
            }

            if ( count( $unique_entries ) < $min_group_size ) {
                unset( $groups[ $group_key ] );
                continue;
            }

            $groups[ $group_key ] = array_values( $unique_entries );
        }

        return $groups;
    }

    public function hydrate_post( $post ) {
        $post = get_post( $post );

        if ( ! $post ) {
            return array();
        }

        $group      = get_post_meta( $post->ID, self::META_GROUP, true );
        $language   = get_post_meta( $post->ID, self::META_LANGUAGE, true );
        $region     = get_post_meta( $post->ID, self::META_REGION, true );
        $is_default = '1' === (string) get_post_meta( $post->ID, self::META_X_DEFAULT, true );

        $permalink      = get_permalink( $post );
        $seo_signals    = $this->get_seo_signals( $post );
        $canonical_url  = $seo_signals['canonical_url'];
        $is_noindex     = $seo_signals['is_noindex'];

        return array(
            'post_id'     => $post->ID,
            'post_title'  => get_the_title( $post ),
            'post_status' => $post->post_status,
            'edit_link'   => get_edit_post_link( $post->ID, '' ),
            'permalink'   => $permalink,
            'group'       => CannyForge_Hreflang_Helpers::sanitize_group( $group ),
            'language'    => CannyForge_Hreflang_Helpers::sanitize_language( $language ),
            'region'      => CannyForge_Hreflang_Helpers::sanitize_region( $region ),
            'hreflang'    => CannyForge_Hreflang_Helpers::build_hreflang( $language, $region ),
            'is_default'  => $is_default,
            'canonical'   => $canonical_url,
            'is_noindex'  => $is_noindex,
        );
    }

    public function build_health_issues( array $row, array $group_rows, array $url_group_map ) {
        $issues = array();

        if ( isset( $row['post_status'] ) && 'publish' !== $row['post_status'] ) {
            $issues[] = __( 'Only published pages should be included in hreflang clusters.', 'cannyforge-hreflang' );
        }

        if ( ! empty( $row['is_noindex'] ) ) {
            $issues[] = __( 'Noindex page is included in a hreflang cluster.', 'cannyforge-hreflang' );
        }

        $canonical          = isset( $row['canonical'] ) ? (string) $row['canonical'] : '';
        $current_normalized = $this->normalize_url_for_compare( (string) ( $row['permalink'] ?? '' ) );
        $canonical_normalized = $this->normalize_url_for_compare( $canonical );

        if ( ! empty( $canonical_normalized ) && ! empty( $current_normalized ) && $canonical_normalized !== $current_normalized ) {
            if ( isset( $url_group_map[ $canonical_normalized ] ) ) {
                $canonical_group = $url_group_map[ $canonical_normalized ];
                if ( $canonical_group !== ( $row['group'] ?? '' ) ) {
                    $issues[] = __( 'Canonical points to a page outside this hreflang cluster.', 'cannyforge-hreflang' );
                } else {
                    $issues[] = __( 'Canonical points to another page in this cluster instead of itself.', 'cannyforge-hreflang' );
                }
            } else {
                $issues[] = __( 'Canonical points outside known hreflang URLs.', 'cannyforge-hreflang' );
            }
        }

        $group_canonical_map = array();
        foreach ( $group_rows as $group_row ) {
            $group_canonical = isset( $group_row['canonical'] ) ? (string) $group_row['canonical'] : '';
            $group_permalink = isset( $group_row['permalink'] ) ? (string) $group_row['permalink'] : '';
            $group_canonical_normalized = $this->normalize_url_for_compare( $group_canonical );
            $group_permalink_normalized = $this->normalize_url_for_compare( $group_permalink );

            if ( ! empty( $group_canonical_normalized ) && ! empty( $group_permalink_normalized ) && $group_canonical_normalized !== $group_permalink_normalized ) {
                $group_canonical_map[ $group_canonical_normalized ] = true;
            }
        }

        if ( count( $group_canonical_map ) > 1 ) {
            $issues[] = __( 'Canonical mismatch detected between alternates in this cluster.', 'cannyforge-hreflang' );
        }

        return array_values( array_unique( $issues ) );
    }

    public function get_post_health_issues( $post_id ) {
        $row = $this->hydrate_post( $post_id );
        if ( empty( $row ) || empty( $row['group'] ) ) {
            return array();
        }

        $rows = $this->get_grouped_posts();
        $group_rows = array();
        $url_group_map = array();

        foreach ( $rows as $entry ) {
            if ( empty( $entry['group'] ) ) {
                continue;
            }

            if ( ! empty( $entry['permalink'] ) ) {
                $url_group_map[ $this->normalize_url_for_compare( $entry['permalink'] ) ] = $entry['group'];
            }

            if ( $entry['group'] === $row['group'] ) {
                $group_rows[] = $entry;
            }
        }

        return $this->build_health_issues( $row, $group_rows, $url_group_map );
    }

    public function get_all_groups() {
        $posts = get_posts(
            array(
                'post_type'      => $this->get_enabled_post_types(),
                'post_status'    => array( 'publish', 'private', 'draft', 'pending' ),
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_key'       => self::META_GROUP,
            )
        );

        $groups = array();

        foreach ( $posts as $post_id ) {
            $group = get_post_meta( $post_id, self::META_GROUP, true );
            if ( ! empty( $group ) ) {
                $groups[ $group ] = true;
            }
        }

        return array_keys( $groups );
    }

    public function run_indexability_audit() {
        $results = array(
            'checked_count' => 0,
            'skipped_count' => 0,
            'issue_count'   => 0,
            'rows'          => array(),
        );

        foreach ( $this->get_grouped_posts() as $row ) {
            if ( empty( $row['permalink'] ) || 'publish' !== ( $row['post_status'] ?? '' ) ) {
                continue;
            }

            $validation_issues = $this->validate_url_for_indexing( (string) $row['permalink'] );
            if ( ! empty( $validation_issues['notes'] ) ) {
                ++$results['skipped_count'];
                continue;
            }

            ++$results['checked_count'];

            if ( empty( $validation_issues['issues'] ) ) {
                continue;
            }

            ++$results['issue_count'];
            $results['rows'][] = array(
                'post_id'    => (int) $row['post_id'],
                'post_title' => (string) $row['post_title'],
                'permalink'  => (string) $row['permalink'],
                'group'      => (string) $row['group'],
                'hreflang'   => (string) $row['hreflang'],
                'issues'     => array_values( array_unique( $validation_issues['issues'] ) ),
            );
        }

        return $results;
    }

    private function get_seo_signals( $post ) {
        $post    = get_post( $post );
        $post_id = $post ? (int) $post->ID : 0;

        if ( ! $post_id ) {
            return array(
                'is_noindex'   => false,
                'canonical_url' => '',
            );
        }

        $is_noindex = false;

        // Yoast.
        $yoast_noindex = get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true );
        if ( '1' === (string) $yoast_noindex ) {
            $is_noindex = true;
        }

        // Rank Math.
        $rankmath_robots = get_post_meta( $post_id, 'rank_math_robots', true );
        if ( is_array( $rankmath_robots ) && in_array( 'noindex', $rankmath_robots, true ) ) {
            $is_noindex = true;
        } elseif ( is_string( $rankmath_robots ) && false !== strpos( $rankmath_robots, 'noindex' ) ) {
            $is_noindex = true;
        }

        // AIOSEO.
        $aioseo_noindex = get_post_meta( $post_id, '_aioseo_robots_noindex', true );
        if ( '1' === (string) $aioseo_noindex || 'on' === (string) $aioseo_noindex ) {
            $is_noindex = true;
        }

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WordPress hook.
        $robots = apply_filters( 'wp_robots', array(), 'index' );
        if ( is_array( $robots ) && ! empty( $robots['noindex'] ) ) {
            $is_noindex = true;
        }

        $canonical = '';

        $yoast_canonical = get_post_meta( $post_id, '_yoast_wpseo_canonical', true );
        if ( is_string( $yoast_canonical ) && '' !== trim( $yoast_canonical ) ) {
            $canonical = trim( $yoast_canonical );
        }

        $rankmath_canonical = get_post_meta( $post_id, 'rank_math_canonical_url', true );
        if ( empty( $canonical ) && is_string( $rankmath_canonical ) && '' !== trim( $rankmath_canonical ) ) {
            $canonical = trim( $rankmath_canonical );
        }

        $aioseo_canonical = get_post_meta( $post_id, '_aioseo_canonical_url', true );
        if ( empty( $canonical ) && is_string( $aioseo_canonical ) && '' !== trim( $aioseo_canonical ) ) {
            $canonical = trim( $aioseo_canonical );
        }

        if ( empty( $canonical ) ) {
            $canonical = wp_get_canonical_url( $post_id );
        }

        $canonical = is_string( $canonical ) ? trim( $canonical ) : '';

        return array(
            'is_noindex'   => $is_noindex,
            'canonical_url' => $canonical,
        );
    }

    private function normalize_url_for_compare( $url ) {
        $url = is_string( $url ) ? trim( $url ) : '';
        if ( '' === $url ) {
            return '';
        }

        $parts = wp_parse_url( $url );
        if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
            return untrailingslashit( strtolower( $url ) );
        }

        $scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : 'https';
        $host   = strtolower( $parts['host'] );
        $path   = isset( $parts['path'] ) ? untrailingslashit( $parts['path'] ) : '';
        $query  = isset( $parts['query'] ) ? '?' . $parts['query'] : '';

        return $scheme . '://' . $host . $path . $query;
    }

    private function validate_url_for_indexing( $url ) {
        $normalized = $this->normalize_url_for_compare( $url );
        $issues     = array();
        $notes      = array();

        if ( empty( $normalized ) ) {
            return array(
                'issues' => $issues,
                'notes'  => $notes,
            );
        }

        if ( $this->is_local_audit_host( $normalized ) ) {
            $notes[] = __( 'HTTP audit skipped for local/development host.', 'cannyforge-hreflang' );

            return array(
                'issues' => $issues,
                'notes'  => $notes,
            );
        }

        if ( $this->is_url_blocked_by_robots( $normalized ) ) {
            $issues[] = __( 'URL is blocked by robots.txt.', 'cannyforge-hreflang' );
        }

        $probe = $this->probe_url_response( $normalized );
        if ( ! empty( $probe['error'] ) ) {
            $issues[] = sprintf(
                /* translators: %s: request error message */
                __( 'URL could not be validated: %s', 'cannyforge-hreflang' ),
                $probe['error']
            );
            return array(
                'issues' => $issues,
                'notes'  => $notes,
            );
        }

        $status_code = isset( $probe['status_code'] ) ? (int) $probe['status_code'] : 0;

        if ( 200 !== $status_code ) {
            $issues[] = sprintf(
                /* translators: %d: HTTP status code */
                __( 'URL is not returning HTTP 200 (received %d).', 'cannyforge-hreflang' ),
                $status_code
            );
        }

        $x_robots_tag = isset( $probe['x_robots_tag'] ) ? (string) $probe['x_robots_tag'] : '';
        if ( false !== stripos( $x_robots_tag, 'noindex' ) ) {
            $issues[] = __( 'URL response contains X-Robots-Tag noindex.', 'cannyforge-hreflang' );
        }

        return array(
            'issues' => $issues,
            'notes'  => $notes,
        );
    }

    private function probe_url_response( $url ) {
        $cache_key = 'cannyforge_hreflang_probe_' . md5( $url );
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $args = array(
            'timeout'     => 6,
            'redirection' => 0,
            'user-agent'  => 'CannyForgeHreflang/' . CANNYFORGE_HREFLANG_VERSION,
        );

        $response = wp_remote_head( $url, $args );
        if ( is_wp_error( $response ) ) {
            $response = wp_remote_get( $url, $args );
        }

        if ( is_wp_error( $response ) ) {
            $result = array(
                'error'       => $response->get_error_message(),
                'status_code' => 0,
                'x_robots_tag' => '',
            );
            set_transient( $cache_key, $result, 10 * MINUTE_IN_SECONDS );
            return $result;
        }

        $headers = wp_remote_retrieve_headers( $response );
        $result  = array(
            'error'       => '',
            'status_code' => (int) wp_remote_retrieve_response_code( $response ),
            'x_robots_tag' => is_object( $headers ) ? (string) $headers->offsetGet( 'x-robots-tag' ) : (string) ( $headers['x-robots-tag'] ?? '' ),
        );

        set_transient( $cache_key, $result, 10 * MINUTE_IN_SECONDS );
        return $result;
    }

    private function is_url_blocked_by_robots( $url ) {
        $path = wp_parse_url( $url, PHP_URL_PATH );
        if ( ! is_string( $path ) ) {
            $path = '/';
        }

        $query = wp_parse_url( $url, PHP_URL_QUERY );
        if ( is_string( $query ) && '' !== $query ) {
            $path .= '?' . $query;
        }

        $rules = $this->get_robots_rules_for_wildcard_user_agent();
        if ( empty( $rules ) ) {
            return false;
        }

        $best_match = null;
        foreach ( $rules as $rule ) {
            $pattern = isset( $rule['pattern'] ) ? (string) $rule['pattern'] : '';
            if ( '' === $pattern || ! $this->robots_pattern_matches( $path, $pattern ) ) {
                continue;
            }

            $length = strlen( $pattern );
            if ( null === $best_match || $length > $best_match['length'] || ( $length === $best_match['length'] && 'allow' === $rule['type'] ) ) {
                $best_match = array(
                    'type'   => $rule['type'],
                    'length' => $length,
                );
            }
        }

        return is_array( $best_match ) && 'disallow' === $best_match['type'];
    }

    private function robots_pattern_matches( $path, $pattern ) {
        if ( '' === $pattern ) {
            return false;
        }

        if ( false === strpos( $pattern, '*' ) && false === strpos( $pattern, '$' ) ) {
            return 0 === strpos( $path, $pattern );
        }

        $regex = preg_quote( $pattern, '#' );
        $regex = str_replace( '\*', '.*', $regex );
        if ( '\\$' === substr( $regex, -2 ) ) {
            $regex = substr( $regex, 0, -2 ) . '$';
        }

        return 1 === preg_match( '#^' . $regex . '#', $path );
    }

    private function is_local_audit_host( $url ) {
        $host = wp_parse_url( $url, PHP_URL_HOST );
        $host = is_string( $host ) ? strtolower( trim( $host ) ) : '';

        if ( '' === $host ) {
            return false;
        }

        if ( in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
            return true;
        }

        if ( preg_match( '/^127\.\d+\.\d+\.\d+$/', $host ) ) {
            return true;
        }

        foreach ( array( '.local', '.test', '.invalid', '.example' ) as $suffix ) {
            if ( str_ends_with( $host, $suffix ) ) {
                return true;
            }
        }

        return false;
    }

    private function get_robots_rules_for_wildcard_user_agent() {
        $cache_key = 'cannyforge_hreflang_robots_rules';
        $cached    = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $response = wp_remote_get(
            home_url( '/robots.txt' ),
            array(
                'timeout'    => 6,
                'user-agent' => 'CannyForgeHreflang/' . CANNYFORGE_HREFLANG_VERSION,
            )
        );

        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            set_transient( $cache_key, array(), 10 * MINUTE_IN_SECONDS );
            return array();
        }

        $body  = (string) wp_remote_retrieve_body( $response );
        $lines = preg_split( '/\r\n|\r|\n/', $body );
        if ( ! is_array( $lines ) ) {
            set_transient( $cache_key, array(), 10 * MINUTE_IN_SECONDS );
            return array();
        }

        $rules           = array();
        $active_agents   = array();
        $seen_non_agent  = false;

        foreach ( $lines as $line ) {
            $line = trim( preg_replace( '/#.*/', '', (string) $line ) );
            if ( '' === $line || false === strpos( $line, ':' ) ) {
                continue;
            }

            list( $directive, $value ) = array_map( 'trim', explode( ':', $line, 2 ) );
            $directive = strtolower( $directive );

            if ( 'user-agent' === $directive ) {
                if ( $seen_non_agent ) {
                    $active_agents  = array();
                    $seen_non_agent = false;
                }
                $active_agents[] = strtolower( $value );
                continue;
            }

            if ( 'allow' !== $directive && 'disallow' !== $directive ) {
                continue;
            }

            $seen_non_agent = true;
            if ( empty( $active_agents ) || ! in_array( '*', $active_agents, true ) ) {
                continue;
            }

            $pattern = trim( $value );
            if ( '' === $pattern && 'disallow' === $directive ) {
                continue;
            }

            $rules[] = array(
                'type'    => $directive,
                'pattern' => $pattern,
            );
        }

        set_transient( $cache_key, $rules, 10 * MINUTE_IN_SECONDS );
        return $rules;
    }

}
