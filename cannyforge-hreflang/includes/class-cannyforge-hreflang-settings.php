<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CannyForge_Hreflang_Settings {
    const MENU_SLUG          = 'cannyforge-hreflang';
    const NOTICE_FLUSHED_KEY = 'cannyforge_hreflang_flushed';
    const NOTICE_AUDITED_KEY = 'cannyforge_hreflang_indexability_audited';
    const AUDIT_TRANSIENT_KEY = 'cannyforge_hreflang_indexability_audit_results';
    const SCRIPT_HANDLE      = 'cannyforge-hreflang-admin';
    const STYLE_HANDLE       = 'cannyforge-hreflang-admin';

    private $repository;
    private $settings_page_hook = '';

    public function __construct( CannyForge_Hreflang_Repository $repository ) {
        $this->repository = $repository;
    }

    public function register_menu() {
        $this->settings_page_hook = add_options_page(
            __( 'CannyForge Hreflang', 'cannyforge-hreflang' ),
            __( 'CannyForge Hreflang', 'cannyforge-hreflang' ),
            'manage_options',
            self::MENU_SLUG,
            array( $this, 'render_page' )
        );
    }

    public function register_settings() {
        $this->handle_flush_rewrite_request();
        $this->handle_indexability_audit_request();

        register_setting(
            'cannyforge_hreflang_settings_group',
            CannyForge_Hreflang_Repository::OPTION_SETTINGS,
            array( $this, 'sanitize_settings' )
        );

        add_settings_section(
            'cannyforge_hreflang_main',
            __( 'General settings', 'cannyforge-hreflang' ),
            '__return_false',
            self::MENU_SLUG
        );

        add_settings_field(
            'post_types',
            __( 'Enabled post types', 'cannyforge-hreflang' ),
            array( $this, 'render_post_types_field' ),
            self::MENU_SLUG,
            'cannyforge_hreflang_main'
        );

        add_settings_field(
            'min_group_size',
            __( 'Minimum group size for sitemap', 'cannyforge-hreflang' ),
            array( $this, 'render_min_group_size_field' ),
            self::MENU_SLUG,
            'cannyforge_hreflang_main'
        );
    }

    public function enqueue_assets( $hook_suffix ) {
        if ( ! $this->should_enqueue_admin_script( $hook_suffix ) ) {
            return;
        }

        $script_path = CANNYFORGE_HREFLANG_PATH . 'assets/js/cannyforge-hreflang-admin.js';
        $style_path  = CANNYFORGE_HREFLANG_PATH . 'assets/css/cannyforge-hreflang-admin.css';
        $script_ver  = file_exists( $script_path ) ? (string) filemtime( $script_path ) : CANNYFORGE_HREFLANG_VERSION;
        $style_ver   = file_exists( $style_path ) ? (string) filemtime( $style_path ) : CANNYFORGE_HREFLANG_VERSION;

        wp_register_script(
            self::SCRIPT_HANDLE,
            CANNYFORGE_HREFLANG_URL . 'assets/js/cannyforge-hreflang-admin.js',
            array(),
            $script_ver,
            true
        );
        wp_enqueue_script( self::SCRIPT_HANDLE );

        if ( $this->is_settings_page_hook( $hook_suffix ) ) {
            wp_register_style(
                self::STYLE_HANDLE,
                CANNYFORGE_HREFLANG_URL . 'assets/css/cannyforge-hreflang-admin.css',
                array(),
                $style_ver
            );
            wp_enqueue_style( self::STYLE_HANDLE );

            wp_localize_script(
                self::SCRIPT_HANDLE,
                'cannyforgeHreflangAdmin',
                $this->get_settings_page_script_data()
            );
        }
    }

    public function sanitize_settings( $input ) {
        $input = is_array( $input ) ? $input : array();

        $post_types = isset( $input['post_types'] ) && is_array( $input['post_types'] ) ? array_map( 'sanitize_key', $input['post_types'] ) : array( 'page' );
        $post_types = array_values( array_filter( $post_types, 'post_type_exists' ) );

        return array(
            'post_types'      => empty( $post_types ) ? array( 'page' ) : $post_types,
            'min_group_size'  => max( 2, (int) ( $input['min_group_size'] ?? 2 ) ),
            'include_private' => 0,
        );
    }

    public function render_post_types_field() {
        $settings   = $this->repository->get_settings();
        $selected   = isset( $settings['post_types'] ) ? (array) $settings['post_types'] : array( 'page' );
        $post_types = get_post_types( array( 'public' => true ), 'objects' );

        foreach ( $post_types as $post_type ) {
            echo '<label class="cannyforge-hreflang-post-type-option">';
            echo '<input type="checkbox" name="' . esc_attr( CannyForge_Hreflang_Repository::OPTION_SETTINGS ) . '[post_types][]" value="' . esc_attr( $post_type->name ) . '" ' . checked( in_array( $post_type->name, $selected, true ), true, false ) . ' /> ';
            echo esc_html( $post_type->labels->singular_name );
            echo '</label>';
        }
    }

    public function render_min_group_size_field() {
        $settings = $this->repository->get_settings();
        ?>
        <input type="number" min="1" step="1" name="<?php echo esc_attr( CannyForge_Hreflang_Repository::OPTION_SETTINGS ); ?>[min_group_size]" value="<?php echo esc_attr( (int) $settings['min_group_size'] ); ?>" />
        <p class="description"><?php esc_html_e( 'Groups smaller than this will be skipped from the sitemap.', 'cannyforge-hreflang' ); ?></p>
        <?php
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $rows                 = $this->get_sorted_group_rows();
        $group_data           = $this->build_group_page_data( $rows );
        $groups_with_default  = $group_data['groups_with_default'];
        $rows_by_group        = $group_data['rows_by_group'];
        $url_group_map        = $group_data['url_group_map'];
        $audit_results        = get_transient( self::AUDIT_TRANSIENT_KEY );
        $show_audit_results   = $this->is_notice_flag_set( self::NOTICE_AUDITED_KEY );
        ?>
        <div class="wrap">
            <div class="cannyforge-hreflang-header">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 80" width="200" height="50">
                  <defs>
                    <linearGradient id="cfhFlame" x1="0%" y1="100%" x2="0%" y2="0%">
                      <stop offset="0%" stop-color="#E84A1C"/>
                      <stop offset="60%" stop-color="#F97316"/>
                      <stop offset="100%" stop-color="#FBBF24"/>
                    </linearGradient>
                    <linearGradient id="cfhAnvil" x1="0%" y1="0%" x2="0%" y2="100%">
                      <stop offset="0%" stop-color="#334155"/>
                      <stop offset="100%" stop-color="#0F172A"/>
                    </linearGradient>
                  </defs>
                  <rect x="8" y="42" width="46" height="16" rx="3" fill="url(#cfhAnvil)"/>
                  <path d="M12 42 L52 42 L52 32 Q52 28 48 28 L28 28 Q22 28 18 32 Z" fill="url(#cfhAnvil)"/>
                  <path d="M8 35 Q4 35 4 38 L4 42 L12 42 L12 32 Z" fill="#1E293B"/>
                  <rect x="14" y="58" width="12" height="6" rx="2" fill="#0F172A"/>
                  <rect x="36" y="58" width="12" height="6" rx="2" fill="#0F172A"/>
                  <path d="M31 10 C31 10 26 16 27 22 C25 19 23 17 24 13 C20 18 20 24 23 28 C21 27 19 25 19 22 C17 27 20 35 28 36 C35 37 39 33 39 28 C42 30 41 34 39 36 C44 33 45 26 42 22 C43 26 41 28 40 28 C42 23 40 17 37 14 C38 18 36 21 35 22 C36 17 34 12 31 10Z" fill="url(#cfhFlame)"/>
                  <circle cx="44" cy="22" r="1.5" fill="#FBBF24" opacity="0.9"/>
                  <circle cx="47" cy="18" r="1" fill="#FCD34D" opacity="0.7"/>
                  <circle cx="15" cy="20" r="1.5" fill="#FCA5A5" opacity="0.8"/>
                  <text x="68" y="50" font-family="'Segoe UI', Arial, sans-serif" font-size="30" font-weight="800" letter-spacing="-0.5" fill="#0F172A">Canny</text>
                  <text x="156" y="50" font-family="'Segoe UI', Arial, sans-serif" font-size="30" font-weight="800" letter-spacing="-0.5" fill="#E84A1C">Forge</text>
                  <text x="68" y="63" font-family="'Segoe UI', Arial, sans-serif" font-size="9" font-weight="400" letter-spacing="2" fill="#64748B">WORDPRESS PLUGINS</text>
                </svg>
                <div class="cannyforge-hreflang-header-content">
                    <h1><?php esc_html_e( 'Hreflang', 'cannyforge-hreflang' ); ?></h1>
                    <p><?php esc_html_e( 'Multilingual page relationships for SEO optimization', 'cannyforge-hreflang' ); ?></p>
                </div>
            </div>

            <?php if ( $this->is_notice_flag_set( self::NOTICE_FLUSHED_KEY ) ) : ?>
                <div class="notice notice-success inline">
                    <p><?php esc_html_e( 'Rewrite rules flushed successfully!', 'cannyforge-hreflang' ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( $show_audit_results && is_array( $audit_results ) ) : ?>
                <div class="notice notice-success inline">
                    <p>
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: 1: checked URL count, 2: skipped URL count, 3: issue row count */
                                __( 'Indexability audit completed. Checked %1$d published URLs, skipped %2$d local/development URLs, and found issues on %3$d rows.', 'cannyforge-hreflang' ),
                                (int) $audit_results['checked_count'],
                                (int) ( $audit_results['skipped_count'] ?? 0 ),
                                (int) $audit_results['issue_count']
                            )
                        );
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'cannyforge_hreflang_settings_group' );
                do_settings_sections( self::MENU_SLUG );
                submit_button();
                ?>
            </form>

            <hr />

            <h2><?php esc_html_e( 'Sitemap URL', 'cannyforge-hreflang' ); ?></h2>
            <p>
                <code><?php echo esc_html( home_url( '/hreflang-sitemap.xml' ) ); ?></code>
            </p>
            <p class="description">
                <?php esc_html_e( 'If the sitemap is not accessible, click the button below to flush rewrite rules.', 'cannyforge-hreflang' ); ?>
            </p>
            <form method="post" action="<?php echo esc_url( $this->get_settings_page_url() ); ?>" class="cannyforge-hreflang-inline-form">
                <?php wp_nonce_field( 'cannyforge_hreflang_flush_rules', 'cannyforge_hreflang_flush_nonce' ); ?>
                <button type="submit" name="cannyforge_hreflang_flush_rules" class="button">
                    <?php esc_html_e( 'Flush Rewrite Rules', 'cannyforge-hreflang' ); ?>
                </button>
            </form>

            <h2><?php esc_html_e( 'Grouped pages', 'cannyforge-hreflang' ); ?></h2>
            <p><?php esc_html_e( 'Add, create and edit groups here, or in the page builder directly', 'cannyforge-hreflang' ); ?></p>

            <div class="cannyforge-hreflang-toolbar">
                <button type="button" class="button button-primary" id="cannyforge_hreflang_add_to_group_btn">
                    <?php esc_html_e( '+ Add to Group', 'cannyforge-hreflang' ); ?>
                </button>
                <button type="button" class="button button-secondary cannyforge-hreflang-toolbar-button" id="cannyforge_hreflang_delete_all_btn">
                    <?php esc_html_e( 'Delete All Groups', 'cannyforge-hreflang' ); ?>
                </button>
                <?php wp_nonce_field( 'cannyforge_hreflang_delete_all_groups', 'cannyforge_hreflang_nonce_delete', false ); ?>
            </div>
            <div id="cannyforge_hreflang_status" class="cannyforge-hreflang-status" hidden></div>
            <input type="hidden" id="cannyforge_hreflang_set_default_nonce" value="<?php echo esc_attr( wp_create_nonce( 'cannyforge_hreflang_set_x_default' ) ); ?>" />

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Group', 'cannyforge-hreflang' ); ?></th>
                        <th><?php esc_html_e( 'Page', 'cannyforge-hreflang' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'cannyforge-hreflang' ); ?></th>
                        <th><?php esc_html_e( 'Language', 'cannyforge-hreflang' ); ?></th>
                        <th><?php esc_html_e( 'Region', 'cannyforge-hreflang' ); ?></th>
                        <th><?php esc_html_e( 'Hreflang', 'cannyforge-hreflang' ); ?></th>
                        <th><?php esc_html_e( 'SEO health', 'cannyforge-hreflang' ); ?></th>
                        <th><?php esc_html_e( 'x-default', 'cannyforge-hreflang' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'cannyforge-hreflang' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $rows ) ) : ?>
                        <tr>
                            <td colspan="9"><?php esc_html_e( 'No grouped pages found yet.', 'cannyforge-hreflang' ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php $prev_group = ''; ?>
                        <?php foreach ( $rows as $row ) : ?>
                            <?php
                            $group_rows    = isset( $rows_by_group[ $row['group'] ] ) ? $rows_by_group[ $row['group'] ] : array();
                            $health_issues = $this->repository->build_health_issues( $row, $group_rows, $url_group_map );
                            ?>
                            <?php if ( $prev_group !== $row['group'] ) : ?>
                                <tr class="cannyforge-hreflang-group-separator">
                                    <td colspan="9">
                                        <span class="cannyforge-hreflang-group-separator-inner">
                                            <?php echo esc_html__( 'Group:', 'cannyforge-hreflang' ); ?>
                                            <strong><?php echo esc_html( $row['group'] ); ?></strong>
                                            <button
                                                type="button"
                                                class="button button-small cannyforge-hreflang-group-add-btn"
                                                data-group="<?php echo esc_attr( $row['group'] ); ?>"
                                                title="<?php echo esc_attr__( 'Add a page to this group', 'cannyforge-hreflang' ); ?>"
                                                aria-label="<?php echo esc_attr__( 'Add a page to this group', 'cannyforge-hreflang' ); ?>"
                                            >+</button>
                                        </span>
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php $classes = ( $prev_group !== $row['group'] ) ? 'cannyforge-hreflang-group-start' : ''; ?>
                            <tr class="<?php echo esc_attr( $classes ); ?>">
                                <td><?php echo $prev_group !== $row['group'] ? '<code>' . esc_html( $row['group'] ) . '</code>' : ''; ?></td>
                                <td>
                                    <a href="<?php echo esc_url( $row['edit_link'] ); ?>"><?php echo esc_html( $row['post_title'] ); ?></a><br />
                                    <small><a href="<?php echo esc_url( $row['permalink'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $row['permalink'] ); ?></a></small>
                                </td>
                                <td><?php echo esc_html( $row['post_status'] ); ?></td>
                                <td><?php echo esc_html( $row['language'] ?: '—' ); ?></td>
                                <td><?php echo esc_html( $row['region'] ?: '—' ); ?></td>
                                <td><?php echo esc_html( $row['hreflang'] ?: '—' ); ?></td>
                                <td>
                                    <?php if ( empty( $health_issues ) ) : ?>
                                        <span class="cannyforge-hreflang-status-good"><?php esc_html_e( 'Healthy', 'cannyforge-hreflang' ); ?></span>
                                    <?php else : ?>
                                        <ul class="cannyforge-hreflang-issues-list">
                                            <?php foreach ( $health_issues as $issue ) : ?>
                                                <li class="cannyforge-hreflang-status-bad"><?php echo esc_html( $issue ); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $row['is_default'] ? '&#10003;' : '—'; ?></td>
                                <td>
                                    <button
                                        type="button"
                                        class="button button-secondary cannyforge_hreflang_edit_hreflang"
                                        data-post-id="<?php echo esc_attr( $row['post_id'] ); ?>"
                                        data-group="<?php echo esc_attr( $row['group'] ); ?>"
                                        data-language="<?php echo esc_attr( $row['language'] ); ?>"
                                        data-region="<?php echo esc_attr( $row['region'] ); ?>"
                                        data-x-default="<?php echo $row['is_default'] ? '1' : '0'; ?>"
                                        data-title="<?php echo esc_attr( $row['post_title'] ); ?>"
                                    >
                                        <?php esc_html_e( 'Edit hreflang', 'cannyforge-hreflang' ); ?>
                                    </button>
                                    <?php if ( empty( $groups_with_default[ $row['group'] ] ) && ! $row['is_default'] ) : ?>
                                        <button type="button" class="button button-primary cannyforge_hreflang_set_default" data-post-id="<?php echo esc_attr( $row['post_id'] ); ?>">
                                            <?php esc_html_e( 'Set as x-default', 'cannyforge-hreflang' ); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php $prev_group = $row['group']; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <details class="cannyforge-hreflang-audit-panel" <?php echo $show_audit_results ? 'open' : ''; ?>>
                <summary><?php esc_html_e( 'Optional HTTP indexability audit', 'cannyforge-hreflang' ); ?></summary>
                <p class="description">
                    <?php esc_html_e( 'This runs live HTTP requests against published grouped URLs and is intended for manual auditing only. It is not used by the default hreflang health checks.', 'cannyforge-hreflang' ); ?>
                </p>
                <form method="post" action="<?php echo esc_url( $this->get_settings_page_url() ); ?>" class="cannyforge-hreflang-inline-form">
                    <?php wp_nonce_field( 'cannyforge_hreflang_run_indexability_audit', 'cannyforge_hreflang_audit_nonce' ); ?>
                    <button type="submit" name="cannyforge_hreflang_run_indexability_audit" class="button button-secondary">
                        <?php esc_html_e( 'Run indexability audit', 'cannyforge-hreflang' ); ?>
                    </button>
                </form>

                <?php if ( $show_audit_results && is_array( $audit_results ) ) : ?>
                    <?php if ( empty( $audit_results['rows'] ) ) : ?>
                        <p class="cannyforge-hreflang-audit-ok"><?php esc_html_e( 'No HTTP indexability issues were found in the published grouped URLs that were checked.', 'cannyforge-hreflang' ); ?></p>
                    <?php else : ?>
                        <ul class="cannyforge-hreflang-audit-results">
                            <?php foreach ( $audit_results['rows'] as $audit_row ) : ?>
                                <li>
                                    <strong><?php echo esc_html( $audit_row['post_title'] ); ?></strong>
                                    <code><?php echo esc_html( $audit_row['hreflang'] ); ?></code>
                                    <a href="<?php echo esc_url( $audit_row['permalink'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $audit_row['permalink'] ); ?></a>
                                    <ul class="cannyforge-hreflang-issues-list">
                                        <?php foreach ( $audit_row['issues'] as $issue ) : ?>
                                            <li class="cannyforge-hreflang-status-bad"><?php echo esc_html( $issue ); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php endif; ?>
            </details>
        </div>

        <div id="cannyforge_hreflang_modal" class="cannyforge-hreflang-modal" hidden>
            <div class="cannyforge-hreflang-modal__dialog">
                <h3 id="cannyforge_hreflang_modal_heading" class="cannyforge-hreflang-modal__heading"><?php esc_html_e( 'Add Page to Group', 'cannyforge-hreflang' ); ?></h3>

                <form id="cannyforge_hreflang_add_form">
                    <?php wp_nonce_field( 'cannyforge_hreflang_add_to_group', 'cannyforge_hreflang_nonce_add' ); ?>

                    <p>
                        <label for="cannyforge_hreflang_modal_group"><strong><?php esc_html_e( 'Group Name', 'cannyforge-hreflang' ); ?></strong></label><br />
                        <select id="cannyforge_hreflang_modal_group" name="group" class="widefat">
                            <option value=""><?php esc_html_e( 'Create new group...', 'cannyforge-hreflang' ); ?></option>
                            <?php foreach ( $this->repository->get_all_groups() as $group ) : ?>
                                <option value="<?php echo esc_attr( $group ); ?>"><?php echo esc_html( $group ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <p class="description cannyforge-hreflang-field-note"><?php esc_html_e( 'Or type a new group name below:', 'cannyforge-hreflang' ); ?></p>
                    <input type="text" id="cannyforge_hreflang_modal_group_new" class="widefat cannyforge-hreflang-gap-top" placeholder="pricing-pages" />
                    <p class="description"><?php esc_html_e( 'Use lowercase with hyphens. Leave empty to use selection above.', 'cannyforge-hreflang' ); ?></p>

                    <p id="cannyforge_hreflang_modal_post_select_section">
                        <label for="cannyforge_hreflang_modal_post_select"><strong><?php esc_html_e( 'Select Page', 'cannyforge-hreflang' ); ?></strong></label><br />
                        <select id="cannyforge_hreflang_modal_post_select" class="widefat">
                            <option value=""><?php esc_html_e( 'Choose a page...', 'cannyforge-hreflang' ); ?></option>
                            <?php foreach ( $this->get_available_posts_for_modal() as $post ) : ?>
                                <option value="<?php echo esc_attr( $post->ID ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?> (<?php echo esc_html( $post->post_status ); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <p id="cannyforge_hreflang_modal_current_page_section" hidden>
                        <strong><?php esc_html_e( 'Editing page', 'cannyforge-hreflang' ); ?>:</strong>
                        <span id="cannyforge_hreflang_modal_current_page_title"></span>
                    </p>
                    <input type="hidden" id="cannyforge_hreflang_modal_post_id" name="post_id" value="" />

                    <p>
                        <label for="cannyforge_hreflang_modal_language"><strong><?php esc_html_e( 'Language', 'cannyforge-hreflang' ); ?></strong></label><br />
                        <select id="cannyforge_hreflang_modal_language" name="language" class="widefat" required>
                            <option value=""><?php esc_html_e( 'Select language', 'cannyforge-hreflang' ); ?></option>
                            <?php foreach ( CannyForge_Hreflang_Helpers::get_languages() as $code => $label ) : ?>
                                <option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( sprintf( '%s (%s)', $label, $code ) ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>

                    <p>
                        <label for="cannyforge_hreflang_modal_region"><strong><?php esc_html_e( 'Region', 'cannyforge-hreflang' ); ?></strong></label><br />
                        <select id="cannyforge_hreflang_modal_region" name="region" class="widefat">
                            <?php foreach ( CannyForge_Hreflang_Helpers::get_regions() as $code => $label ) : ?>
                                <option value="<?php echo esc_attr( $code ); ?>" data-label="<?php echo esc_attr( $label ); ?>"><?php echo esc_html( '' === $code ? $label : sprintf( '%s (%s)', $label, $code ) ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>

                    <p>
                        <label>
                            <input type="checkbox" id="cannyforge_hreflang_modal_x_default" name="is_default" value="1" />
                            <?php esc_html_e( 'Use as x-default', 'cannyforge-hreflang' ); ?>
                        </label>
                    </p>

                    <div class="cannyforge-hreflang-modal__actions">
                        <input type="submit" class="button button-primary cannyforge-hreflang-submit" id="cannyforge_hreflang_modal_submit" value="<?php echo esc_attr__( 'Save', 'cannyforge-hreflang' ); ?>" />
                        <button type="button" class="button" id="cannyforge_hreflang_modal_cancel"><?php esc_html_e( 'Cancel', 'cannyforge-hreflang' ); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    private function handle_flush_rewrite_request() {
        if ( ! $this->is_settings_page_request() || ! isset( $_POST['cannyforge_hreflang_flush_rules'] ) ) {
            return;
        }

        check_admin_referer( 'cannyforge_hreflang_flush_rules', 'cannyforge_hreflang_flush_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized.', 'cannyforge-hreflang' ) );
        }

        flush_rewrite_rules();

        wp_safe_redirect(
            add_query_arg(
                self::NOTICE_FLUSHED_KEY,
                '1',
                $this->get_settings_page_url()
            )
        );
        exit;
    }

    private function handle_indexability_audit_request() {
        if ( ! $this->is_settings_page_request() || ! isset( $_POST['cannyforge_hreflang_run_indexability_audit'] ) ) {
            return;
        }

        check_admin_referer( 'cannyforge_hreflang_run_indexability_audit', 'cannyforge_hreflang_audit_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized.', 'cannyforge-hreflang' ) );
        }

        $results = $this->repository->run_indexability_audit();
        set_transient( self::AUDIT_TRANSIENT_KEY, $results, 30 * MINUTE_IN_SECONDS );

        wp_safe_redirect(
            add_query_arg(
                self::NOTICE_AUDITED_KEY,
                '1',
                $this->get_settings_page_url()
            )
        );
        exit;
    }

    private function should_enqueue_admin_script( $hook_suffix ) {
        if ( $this->is_settings_page_hook( $hook_suffix ) ) {
            return true;
        }

        $screen = get_current_screen();

        if ( ! $screen || ! in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
            return false;
        }

        return in_array( $screen->post_type, $this->repository->get_enabled_post_types(), true );
    }

    private function is_settings_page_hook( $hook_suffix ) {
        return ! empty( $this->settings_page_hook ) && $hook_suffix === $this->settings_page_hook;
    }

    private function is_settings_page_request() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page routing check for current admin screen.
        return isset( $_GET['page'] ) && self::MENU_SLUG === sanitize_key( wp_unslash( $_GET['page'] ) );
    }

    private function get_settings_page_url() {
        return admin_url( 'options-general.php?page=' . self::MENU_SLUG );
    }

    private function get_sorted_group_rows() {
        $rows = $this->repository->get_grouped_posts();

        usort(
            $rows,
            static function ( $a, $b ) {
                return strcmp( $a['group'] . $a['post_title'], $b['group'] . $b['post_title'] );
            }
        );

        return $rows;
    }

    private function build_group_page_data( array $rows ) {
        $group_hreflang_map  = array();
        $groups_with_default = array();
        $rows_by_group       = array();
        $url_group_map       = array();

        foreach ( $rows as $row ) {
            if ( $row['is_default'] ) {
                $groups_with_default[ $row['group'] ] = true;
            }

            if ( empty( $row['group'] ) || empty( $row['language'] ) ) {
                continue;
            }

            $group_hreflang_map[ $row['group'] ][ $row['language'] ][] = array(
                'postId' => (int) $row['post_id'],
                'region' => (string) $row['region'],
            );
            $rows_by_group[ $row['group'] ][] = $row;

            if ( ! empty( $row['permalink'] ) ) {
                $url_group_map[ untrailingslashit( strtolower( $row['permalink'] ) ) ] = $row['group'];
            }
        }

        return array(
            'group_hreflang_map'  => $group_hreflang_map,
            'groups_with_default' => $groups_with_default,
            'rows_by_group'       => $rows_by_group,
            'url_group_map'       => $url_group_map,
        );
    }

    private function get_settings_page_script_data() {
        $rows       = $this->get_sorted_group_rows();
        $group_data = $this->build_group_page_data( $rows );

        return array(
            'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
            'groupHreflangMap' => $group_data['group_hreflang_map'],
            'strings'          => array(
                'addTitle'             => __( 'Add Page to Group', 'cannyforge-hreflang' ),
                'editTitle'            => __( 'Edit hreflang', 'cannyforge-hreflang' ),
                'save'                 => __( 'Save', 'cannyforge-hreflang' ),
                'saving'               => __( 'Saving...', 'cannyforge-hreflang' ),
                'setDefault'           => __( 'Set as x-default', 'cannyforge-hreflang' ),
                'settingDefault'       => __( 'Setting...', 'cannyforge-hreflang' ),
                'deleteAll'            => __( 'Delete All Groups', 'cannyforge-hreflang' ),
                'deleting'             => __( 'Deleting...', 'cannyforge-hreflang' ),
                'confirmDelete'        => __( 'Click again to confirm delete', 'cannyforge-hreflang' ),
                'confirmDeleteHelp'    => __( 'Click delete again to confirm removing all groups.', 'cannyforge-hreflang' ),
                'invalidPost'          => __( 'Invalid post selected.', 'cannyforge-hreflang' ),
                'addedSuccess'         => __( 'Page added to group successfully!', 'cannyforge-hreflang' ),
                'updatedSuccess'       => __( 'Hreflang updated successfully!', 'cannyforge-hreflang' ),
                'addError'             => __( 'Error adding page to group.', 'cannyforge-hreflang' ),
                'updateError'          => __( 'Error saving hreflang.', 'cannyforge-hreflang' ),
                'addNetworkError'      => __( 'Error: Failed to add page to group.', 'cannyforge-hreflang' ),
                'updateNetworkError'   => __( 'Error: Failed to save hreflang.', 'cannyforge-hreflang' ),
                'defaultSuccess'       => __( 'x-default set successfully.', 'cannyforge-hreflang' ),
                'defaultError'         => __( 'Error setting x-default.', 'cannyforge-hreflang' ),
                'defaultNetworkError'  => __( 'Error: Failed to set x-default.', 'cannyforge-hreflang' ),
                'deleteSuccess'        => __( 'All groups deleted!', 'cannyforge-hreflang' ),
                'deleteError'          => __( 'Error deleting groups.', 'cannyforge-hreflang' ),
                'deleteNetworkError'   => __( 'Error: Failed to delete groups.', 'cannyforge-hreflang' ),
            ),
        );
    }

    private function get_available_posts_for_modal() {
        $posts = get_posts(
            array(
                'post_type'      => $this->repository->get_enabled_post_types(),
                'post_status'    => array( 'publish' ),
                'posts_per_page' => 200,
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );

        return array_values(
            array_filter(
                $posts,
                static function ( $post ) {
                    return '' === (string) get_post_meta( $post->ID, CannyForge_Hreflang_Repository::META_GROUP, true );
                }
            )
        );
    }

    private function is_notice_flag_set( $key ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice flag after safe redirect.
        return isset( $_GET[ $key ] ) && '1' === sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
    }
}
