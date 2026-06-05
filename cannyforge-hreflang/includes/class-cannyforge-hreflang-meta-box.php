<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CannyForge_Hreflang_Meta_Box {
    private $repository;

    public function __construct( CannyForge_Hreflang_Repository $repository ) {
        $this->repository = $repository;
    }

    public function register() {
        foreach ( $this->repository->get_enabled_post_types() as $post_type ) {
            add_meta_box(
                'cannyforge-hreflang-meta-box',
                __( 'CannyForge Hreflang', 'cannyforge-hreflang' ),
                array( $this, 'render' ),
                $post_type,
                'side',
                'default'
            );
        }
    }

    public function render( $post ) {
        $data          = $this->repository->hydrate_post( $post );
        $languages     = CannyForge_Hreflang_Helpers::get_languages();
        $regions       = CannyForge_Hreflang_Helpers::get_regions();
        $groups        = $this->repository->get_all_groups();
        $health_issues = $this->repository->get_post_health_issues( $post->ID );

        wp_nonce_field( 'cannyforge_hreflang_save_meta', 'cannyforge_hreflang_nonce' );
        ?>
        <p>
            <label for="cannyforge_hreflang_group"><strong><?php esc_html_e( 'Translation Group', 'cannyforge-hreflang' ); ?></strong></label><br />
            <select class="widefat cannyforge-hreflang-group-select" id="cannyforge_hreflang_group" name="cannyforge_hreflang_group">
                <option value=""><?php esc_html_e( 'Select group or create new', 'cannyforge-hreflang' ); ?></option>
                <?php if ( ! empty( $groups ) ) : ?>
                    <optgroup label="<?php esc_html_e( 'Existing Groups', 'cannyforge-hreflang' ); ?>">
                        <?php foreach ( $groups as $group ) : ?>
                            <option value="<?php echo esc_attr( $group ); ?>" <?php selected( $data['group'], $group ); ?>>
                                <?php echo esc_html( $group ); ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endif; ?>
            </select>
            <p class="description cannyforge-hreflang-field-note"><?php esc_html_e( 'Or type a new group name below to create one:', 'cannyforge-hreflang' ); ?></p>
            <input type="text" class="widefat cannyforge-hreflang-group-new-input cannyforge-hreflang-gap-top" id="cannyforge_hreflang_group_new" name="cannyforge_hreflang_group_new" placeholder="pricing-pages" />
            <p class="description"><?php esc_html_e( 'Use lowercase with hyphens. Leave empty to use selection above.', 'cannyforge-hreflang' ); ?></p>
        </p>

        <p>
            <label for="cannyforge_hreflang_lang"><strong><?php esc_html_e( 'Language', 'cannyforge-hreflang' ); ?></strong></label><br />
            <select class="widefat" id="cannyforge_hreflang_lang" name="cannyforge_hreflang_lang">
                <option value=""><?php esc_html_e( 'Select language', 'cannyforge-hreflang' ); ?></option>
                <?php foreach ( $languages as $code => $label ) : ?>
                    <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $data['language'], $code ); ?>>
                        <?php echo esc_html( sprintf( '%s (%s)', $label, $code ) ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="cannyforge_hreflang_region"><strong><?php esc_html_e( 'Region', 'cannyforge-hreflang' ); ?></strong></label><br />
            <select class="widefat" id="cannyforge_hreflang_region" name="cannyforge_hreflang_region">
                <?php foreach ( $regions as $code => $label ) : ?>
                    <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $data['region'], $code ); ?>>
                        <?php echo esc_html( '' === $code ? $label : sprintf( '%s (%s)', $label, $code ) ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label>
                <input type="checkbox" name="cannyforge_hreflang_is_default" value="1" <?php checked( $data['is_default'] ); ?> />
                <?php esc_html_e( 'Use this page as x-default', 'cannyforge-hreflang' ); ?>
            </label>
        </p>

        <p class="cannyforge-hreflang-preview-panel">
            <strong><?php esc_html_e( 'Preview', 'cannyforge-hreflang' ); ?>:</strong><br />
            <span id="cannyforge_hreflang_preview_value">
                <?php if ( ! empty( $data['hreflang'] ) ) : ?>
                    <code><?php echo esc_html( $data['hreflang'] ); ?></code><br />
                <?php else : ?>
                    <em><?php esc_html_e( 'Select a language to generate a hreflang value.', 'cannyforge-hreflang' ); ?></em><br />
                <?php endif; ?>
            </span>
            <span id="cannyforge_hreflang_preview_x_default">
                <?php if ( $data['is_default'] ) : ?>
                    <code>x-default</code>
                <?php endif; ?>
            </span>
        </p>

        <p class="cannyforge-hreflang-preview-panel">
            <strong><?php esc_html_e( 'Hreflang health', 'cannyforge-hreflang' ); ?>:</strong><br />
            <?php if ( empty( $data['group'] ) ) : ?>
                <em><?php esc_html_e( 'Assign this page to a translation group to run cluster checks.', 'cannyforge-hreflang' ); ?></em>
            <?php elseif ( empty( $health_issues ) ) : ?>
                <span class="cannyforge-hreflang-status-good"><?php esc_html_e( 'No noindex/canonical conflicts detected.', 'cannyforge-hreflang' ); ?></span>
            <?php else : ?>
                <ul class="cannyforge-hreflang-issues-list cannyforge-hreflang-issues-list-spaced">
                    <?php foreach ( $health_issues as $issue ) : ?>
                        <li class="cannyforge-hreflang-status-bad"><?php echo esc_html( $issue ); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </p>
        <?php
    }

    public function save( $post_id ) {
        if ( ! isset( $_POST['cannyforge_hreflang_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cannyforge_hreflang_nonce'] ) ), 'cannyforge_hreflang_save_meta' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $group = isset( $_POST['cannyforge_hreflang_group'] ) ? CannyForge_Hreflang_Helpers::sanitize_group( sanitize_text_field( wp_unslash( $_POST['cannyforge_hreflang_group'] ) ) ) : '';

        // Allow creating a new group from the text input
        if ( empty( $group ) && isset( $_POST['cannyforge_hreflang_group_new'] ) ) {
            $group = CannyForge_Hreflang_Helpers::sanitize_group( sanitize_text_field( wp_unslash( $_POST['cannyforge_hreflang_group_new'] ) ) );
        }

        $language   = isset( $_POST['cannyforge_hreflang_lang'] ) ? CannyForge_Hreflang_Helpers::sanitize_language( sanitize_text_field( wp_unslash( $_POST['cannyforge_hreflang_lang'] ) ) ) : '';
        $region     = isset( $_POST['cannyforge_hreflang_region'] ) ? CannyForge_Hreflang_Helpers::sanitize_region( sanitize_text_field( wp_unslash( $_POST['cannyforge_hreflang_region'] ) ) ) : '';
        $is_default = isset( $_POST['cannyforge_hreflang_is_default'] ) ? 1 : 0;

        $post = get_post( $post_id );
        if ( $post && 'publish' !== $post->post_status && ( ! empty( $group ) || ! empty( $language ) || ! empty( $region ) || $is_default ) ) {
            return;
        }

        $this->repository->update_post_meta( $post_id, $group, $language, $region, $is_default );
    }
}
