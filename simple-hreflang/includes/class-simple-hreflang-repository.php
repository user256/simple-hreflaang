<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Simple_Hreflang_Repository {
    const META_GROUP      = '_simple_hreflang_group';
    const META_LANGUAGE   = '_simple_hreflang_lang';
    const META_REGION     = '_simple_hreflang_region';
    const META_X_DEFAULT  = '_simple_hreflang_is_default';
    const OPTION_SETTINGS = 'simple_hreflang_settings';

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

        $grouped_posts = $this->get_grouped_posts();
        if ( count( $grouped_posts ) < 100 && function_exists( 'flush_rewrite_rules' ) ) {
            flush_rewrite_rules();
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
                'post__not_in'   => array( (int) $post_id ),
                'meta_query'     => array(
                    array(
                        'key'   => self::META_GROUP,
                        'value' => $group,
                    ),
                ),
            )
        );

        foreach ( $posts as $other_post_id ) {
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
                'meta_query'     => array(
                    array(
                        'key'     => self::META_GROUP,
                        'compare' => 'EXISTS',
                    ),
                ),
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
        $min_group_size = max( 1, (int) $settings['min_group_size'] );
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

        $group     = get_post_meta( $post->ID, self::META_GROUP, true );
        $language  = get_post_meta( $post->ID, self::META_LANGUAGE, true );
        $region    = get_post_meta( $post->ID, self::META_REGION, true );
        $is_default = '1' === (string) get_post_meta( $post->ID, self::META_X_DEFAULT, true );

        return array(
            'post_id'     => $post->ID,
            'post_title'  => get_the_title( $post ),
            'post_status' => $post->post_status,
            'edit_link'   => get_edit_post_link( $post->ID, '' ),
            'permalink'   => get_permalink( $post ),
            'group'       => Simple_Hreflang_Helpers::sanitize_group( $group ),
            'language'    => Simple_Hreflang_Helpers::sanitize_language( $language ),
            'region'      => Simple_Hreflang_Helpers::sanitize_region( $region ),
            'hreflang'    => Simple_Hreflang_Helpers::build_hreflang( $language, $region ),
            'is_default'  => $is_default,
        );
    }

    public function get_all_groups() {
        $posts = get_posts(
            array(
                'post_type'      => $this->get_enabled_post_types(),
                'post_status'    => array( 'publish', 'private', 'draft', 'pending' ),
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'     => self::META_GROUP,
                        'compare' => 'EXISTS',
                    ),
                ),
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
}
