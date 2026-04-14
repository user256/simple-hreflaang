<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Simple_Hreflang_Plugin {
    private $repository;
    private $meta_box;
    private $settings;
    private $sitemap;

    public function __construct() {
        $this->repository = new Simple_Hreflang_Repository();
        $this->meta_box   = new Simple_Hreflang_Meta_Box( $this->repository );
        $this->settings   = new Simple_Hreflang_Settings( $this->repository );
        $this->sitemap    = new Simple_Hreflang_Sitemap_Provider( $this->repository );
    }

    public function boot() {
        add_action( 'add_meta_boxes', array( $this->meta_box, 'register' ) );
        add_action( 'save_post', array( $this->meta_box, 'save' ) );
        add_action( 'admin_menu', array( $this->settings, 'register_menu' ) );
        add_action( 'admin_init', array( $this->settings, 'register_settings' ) );
        add_action( 'init', array( $this->sitemap, 'register_rewrite' ) );
        add_action( 'template_redirect', array( $this->sitemap, 'maybe_render' ) );
        add_action( 'wp_ajax_simple_hreflang_add_to_group', array( $this, 'ajax_add_to_group' ) );
        add_action( 'wp_ajax_simple_hreflang_delete_all_groups', array( $this, 'ajax_delete_all_groups' ) );
        add_action( 'wp_ajax_simple_hreflang_set_x_default', array( $this, 'ajax_set_x_default' ) );

        register_activation_hook( SIMPLE_HREFLANG_FILE, array( 'Simple_Hreflang_Sitemap_Provider', 'activate' ) );
        register_deactivation_hook( SIMPLE_HREFLANG_FILE, array( 'Simple_Hreflang_Sitemap_Provider', 'deactivate' ) );
    }

    public function ajax_add_to_group() {
        if ( ! isset( $_POST['simple_hreflang_nonce_add'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['simple_hreflang_nonce_add'] ) ), 'simple_hreflang_add_to_group' ) ) {
            wp_send_json_error( __( 'Security check failed', 'simple-hreflang' ), 403 );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'simple-hreflang' ), 403 );
        }

        $post_id  = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
        $group    = isset( $_POST['group'] ) ? sanitize_text_field( wp_unslash( $_POST['group'] ) ) : '';
        $language = isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : '';
        $region   = isset( $_POST['region'] ) ? sanitize_text_field( wp_unslash( $_POST['region'] ) ) : '';
        $is_default = isset( $_POST['is_default'] ) ? 1 : 0;

        if ( ! $post_id ) {
            wp_send_json_error( __( 'Invalid post ID', 'simple-hreflang' ) );
        }

        $post = get_post( $post_id );
        if ( ! $post || ! in_array( $post->post_type, $this->repository->get_enabled_post_types(), true ) ) {
            wp_send_json_error( __( 'Invalid post', 'simple-hreflang' ) );
        }

        // Allow new group creation from text input
        if ( empty( $group ) && isset( $_POST['group_new'] ) ) {
            $group = sanitize_text_field( wp_unslash( $_POST['group_new'] ) );
        }

        $group    = Simple_Hreflang_Helpers::sanitize_group( $group );
        $language = Simple_Hreflang_Helpers::sanitize_language( $language );
        $region   = Simple_Hreflang_Helpers::sanitize_region( $region );

        if ( empty( $group ) ) {
            wp_send_json_error( __( 'Group name is required', 'simple-hreflang' ) );
        }

        if ( empty( $language ) ) {
            wp_send_json_error( __( 'Language is required', 'simple-hreflang' ) );
        }

        $this->repository->update_post_meta( $post_id, $group, $language, $region, $is_default );

        wp_send_json_success(
            array(
                'message' => __( 'Page added to group successfully', 'simple-hreflang' ),
                'group'   => $group,
            )
        );
    }

    public function ajax_delete_all_groups() {
        if ( ! isset( $_POST['simple_hreflang_nonce_delete'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['simple_hreflang_nonce_delete'] ) ), 'simple_hreflang_delete_all_groups' ) ) {
            wp_send_json_error( __( 'Security check failed', 'simple-hreflang' ), 403 );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'simple-hreflang' ), 403 );
        }

        $posts = get_posts(
            array(
                'post_type'      => $this->repository->get_enabled_post_types(),
                'post_status'    => array( 'publish', 'private', 'draft', 'pending' ),
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'     => Simple_Hreflang_Repository::META_GROUP,
                        'compare' => 'EXISTS',
                    ),
                ),
            )
        );

        $count = 0;
        foreach ( $posts as $post_id ) {
            delete_post_meta( $post_id, Simple_Hreflang_Repository::META_GROUP );
            delete_post_meta( $post_id, Simple_Hreflang_Repository::META_LANGUAGE );
            delete_post_meta( $post_id, Simple_Hreflang_Repository::META_REGION );
            delete_post_meta( $post_id, Simple_Hreflang_Repository::META_X_DEFAULT );
            ++$count;
        }

        $message = sprintf(
            /* translators: %d: Number of posts whose hreflang data was deleted. */
            __( 'Deleted hreflang data from %d posts', 'simple-hreflang' ),
            $count
        );

        wp_send_json_success(
            array(
                'message' => $message,
                'count'   => $count,
            )
        );
    }

    public function ajax_set_x_default() {
        if ( ! isset( $_POST['simple_hreflang_nonce_set_default'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['simple_hreflang_nonce_set_default'] ) ), 'simple_hreflang_set_x_default' ) ) {
            wp_send_json_error( __( 'Security check failed', 'simple-hreflang' ), 403 );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'simple-hreflang' ), 403 );
        }

        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
        if ( ! $post_id ) {
            wp_send_json_error( __( 'Invalid post ID', 'simple-hreflang' ) );
        }

        $post = get_post( $post_id );
        if ( ! $post || ! in_array( $post->post_type, $this->repository->get_enabled_post_types(), true ) ) {
            wp_send_json_error( __( 'Invalid post', 'simple-hreflang' ) );
        }

        $group = get_post_meta( $post_id, Simple_Hreflang_Repository::META_GROUP, true );
        $language = get_post_meta( $post_id, Simple_Hreflang_Repository::META_LANGUAGE, true );
        $region = get_post_meta( $post_id, Simple_Hreflang_Repository::META_REGION, true );

        $group    = Simple_Hreflang_Helpers::sanitize_group( $group );
        $language = Simple_Hreflang_Helpers::sanitize_language( $language );
        $region   = Simple_Hreflang_Helpers::sanitize_region( $region );

        if ( empty( $group ) || empty( $language ) ) {
            wp_send_json_error( __( 'Group and language are required to set x-default', 'simple-hreflang' ) );
        }

        $this->repository->update_post_meta( $post_id, $group, $language, $region, 1 );

        wp_send_json_success(
            array(
                'message' => __( 'x-default set successfully', 'simple-hreflang' ),
            )
        );
    }
}
