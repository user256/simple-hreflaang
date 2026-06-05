<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CannyForge_Hreflang_Plugin {
    private $repository;
    private $meta_box;
    private $settings;
    private $sitemap;

    public function __construct() {
        $this->repository = new CannyForge_Hreflang_Repository();
        $this->meta_box   = new CannyForge_Hreflang_Meta_Box( $this->repository );
        $this->settings   = new CannyForge_Hreflang_Settings( $this->repository );
        $this->sitemap    = new CannyForge_Hreflang_Sitemap_Provider( $this->repository );
    }

    public function boot() {
        add_action( 'add_meta_boxes', array( $this->meta_box, 'register' ) );
        add_action( 'save_post', array( $this->meta_box, 'save' ) );
        add_action( 'admin_menu', array( $this->settings, 'register_menu' ) );
        add_action( 'admin_init', array( $this->settings, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this->settings, 'enqueue_assets' ) );
        add_action( 'init', array( $this->sitemap, 'register_rewrite' ) );
        add_action( 'template_redirect', array( $this->sitemap, 'maybe_serve_xsl' ), 0 );
        add_action( 'template_redirect', array( $this->sitemap, 'maybe_render' ), 1 );
        add_action( 'wp_ajax_cannyforge_hreflang_add_to_group', array( $this, 'ajax_add_to_group' ) );
        add_action( 'wp_ajax_cannyforge_hreflang_delete_all_groups', array( $this, 'ajax_delete_all_groups' ) );
        add_action( 'wp_ajax_cannyforge_hreflang_set_x_default', array( $this, 'ajax_set_x_default' ) );

        register_activation_hook( CANNYFORGE_HREFLANG_FILE, array( 'CannyForge_Hreflang_Sitemap_Provider', 'activate' ) );
        register_deactivation_hook( CANNYFORGE_HREFLANG_FILE, array( 'CannyForge_Hreflang_Sitemap_Provider', 'deactivate' ) );
    }

    public function ajax_add_to_group() {
        if ( ! isset( $_POST['cannyforge_hreflang_nonce_add'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cannyforge_hreflang_nonce_add'] ) ), 'cannyforge_hreflang_add_to_group' ) ) {
            wp_send_json_error( __( 'Security check failed', 'cannyforge-hreflang' ), 403 );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'cannyforge-hreflang' ), 403 );
        }

        $post_id  = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
        $group    = isset( $_POST['group'] ) ? sanitize_text_field( wp_unslash( $_POST['group'] ) ) : '';
        $language = isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : '';
        $region   = isset( $_POST['region'] ) ? sanitize_text_field( wp_unslash( $_POST['region'] ) ) : '';
        $is_default = isset( $_POST['is_default'] ) ? 1 : 0;

        if ( ! $post_id ) {
            wp_send_json_error( __( 'Invalid post ID', 'cannyforge-hreflang' ) );
        }

        $post = get_post( $post_id );
        if ( ! $post || ! in_array( $post->post_type, $this->repository->get_enabled_post_types(), true ) ) {
            wp_send_json_error( __( 'Invalid post', 'cannyforge-hreflang' ) );
        }

        if ( 'publish' !== $post->post_status ) {
            wp_send_json_error( __( 'Only published pages can be added to hreflang groups.', 'cannyforge-hreflang' ) );
        }

        // Allow new group creation from text input
        if ( empty( $group ) && isset( $_POST['group_new'] ) ) {
            $group = sanitize_text_field( wp_unslash( $_POST['group_new'] ) );
        }

        $group    = CannyForge_Hreflang_Helpers::sanitize_group( $group );
        $language = CannyForge_Hreflang_Helpers::sanitize_language( $language );
        $region   = CannyForge_Hreflang_Helpers::sanitize_region( $region );

        if ( empty( $group ) ) {
            wp_send_json_error( __( 'Group name is required', 'cannyforge-hreflang' ) );
        }

        if ( empty( $language ) ) {
            wp_send_json_error( __( 'Language is required', 'cannyforge-hreflang' ) );
        }

        $this->repository->update_post_meta( $post_id, $group, $language, $region, $is_default );

        wp_send_json_success(
            array(
                'message' => __( 'Page added to group successfully', 'cannyforge-hreflang' ),
                'group'   => $group,
            )
        );
    }

    public function ajax_delete_all_groups() {
        if ( ! isset( $_POST['cannyforge_hreflang_nonce_delete'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cannyforge_hreflang_nonce_delete'] ) ), 'cannyforge_hreflang_delete_all_groups' ) ) {
            wp_send_json_error( __( 'Security check failed', 'cannyforge-hreflang' ), 403 );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'cannyforge-hreflang' ), 403 );
        }

        $posts = get_posts(
            array(
                'post_type'      => $this->repository->get_enabled_post_types(),
                'post_status'    => array( 'publish', 'private', 'draft', 'pending' ),
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_key'       => CannyForge_Hreflang_Repository::META_GROUP,
            )
        );

        $count = 0;
        foreach ( $posts as $post_id ) {
            delete_post_meta( $post_id, CannyForge_Hreflang_Repository::META_GROUP );
            delete_post_meta( $post_id, CannyForge_Hreflang_Repository::META_LANGUAGE );
            delete_post_meta( $post_id, CannyForge_Hreflang_Repository::META_REGION );
            delete_post_meta( $post_id, CannyForge_Hreflang_Repository::META_X_DEFAULT );
            ++$count;
        }

        $message = sprintf(
            /* translators: %d: Number of posts whose hreflang data was deleted. */
            __( 'Deleted hreflang data from %d posts', 'cannyforge-hreflang' ),
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
        if ( ! isset( $_POST['cannyforge_hreflang_nonce_set_default'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cannyforge_hreflang_nonce_set_default'] ) ), 'cannyforge_hreflang_set_x_default' ) ) {
            wp_send_json_error( __( 'Security check failed', 'cannyforge-hreflang' ), 403 );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'cannyforge-hreflang' ), 403 );
        }

        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
        if ( ! $post_id ) {
            wp_send_json_error( __( 'Invalid post ID', 'cannyforge-hreflang' ) );
        }

        $post = get_post( $post_id );
        if ( ! $post || ! in_array( $post->post_type, $this->repository->get_enabled_post_types(), true ) ) {
            wp_send_json_error( __( 'Invalid post', 'cannyforge-hreflang' ) );
        }

        if ( 'publish' !== $post->post_status ) {
            wp_send_json_error( __( 'Only published pages can be used as x-default.', 'cannyforge-hreflang' ) );
        }

        $group = get_post_meta( $post_id, CannyForge_Hreflang_Repository::META_GROUP, true );
        $language = get_post_meta( $post_id, CannyForge_Hreflang_Repository::META_LANGUAGE, true );
        $region = get_post_meta( $post_id, CannyForge_Hreflang_Repository::META_REGION, true );

        $group    = CannyForge_Hreflang_Helpers::sanitize_group( $group );
        $language = CannyForge_Hreflang_Helpers::sanitize_language( $language );
        $region   = CannyForge_Hreflang_Helpers::sanitize_region( $region );

        if ( empty( $group ) || empty( $language ) ) {
            wp_send_json_error( __( 'Group and language are required to set x-default', 'cannyforge-hreflang' ) );
        }

        $this->repository->update_post_meta( $post_id, $group, $language, $region, 1 );

        wp_send_json_success(
            array(
                'message' => __( 'x-default set successfully', 'cannyforge-hreflang' ),
            )
        );
    }
}
