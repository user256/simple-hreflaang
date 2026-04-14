<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Simple_Hreflang_Meta_Box {
    private $repository;

    public function __construct( Simple_Hreflang_Repository $repository ) {
        $this->repository = $repository;
    }

    public function register() {
        foreach ( $this->repository->get_enabled_post_types() as $post_type ) {
            add_meta_box(
                'simple-hreflang-meta-box',
                __( 'Simple Hreflang', 'simple-hreflang' ),
                array( $this, 'render' ),
                $post_type,
                'side',
                'default'
            );
        }
    }

    public function render( $post ) {
        $data      = $this->repository->hydrate_post( $post );
        $languages = Simple_Hreflang_Helpers::get_languages();
        $regions   = Simple_Hreflang_Helpers::get_regions();
        $groups    = $this->repository->get_all_groups();

        wp_nonce_field( 'simple_hreflang_save_meta', 'simple_hreflang_nonce' );
        ?>
        <p>
            <label for="simple_hreflang_group"><strong><?php esc_html_e( 'Translation Group', 'simple-hreflang' ); ?></strong></label><br />
            <select class="widefat" id="simple_hreflang_group" name="simple_hreflang_group">
                <option value=""><?php esc_html_e( 'Select group or create new', 'simple-hreflang' ); ?></option>
                <?php if ( ! empty( $groups ) ) : ?>
                    <optgroup label="<?php esc_html_e( 'Existing Groups', 'simple-hreflang' ); ?>">
                        <?php foreach ( $groups as $group ) : ?>
                            <option value="<?php echo esc_attr( $group ); ?>" <?php selected( $data['group'], $group ); ?>>
                                <?php echo esc_html( $group ); ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endif; ?>
            </select>
            <p class="description" style="margin-top:6px;"><?php esc_html_e( 'Or type a new group name below to create one:', 'simple-hreflang' ); ?></p>
            <input type="text" class="widefat" id="simple_hreflang_group_new" placeholder="pricing-pages" style="margin-top:6px;" />
            <p class="description"><?php esc_html_e( 'Use lowercase with hyphens. Leave empty to use selection above.', 'simple-hreflang' ); ?></p>
        </p>

        <p>
            <label for="simple_hreflang_lang"><strong><?php esc_html_e( 'Language', 'simple-hreflang' ); ?></strong></label><br />
            <select class="widefat" id="simple_hreflang_lang" name="simple_hreflang_lang">
                <option value=""><?php esc_html_e( 'Select language', 'simple-hreflang' ); ?></option>
                <?php foreach ( $languages as $code => $label ) : ?>
                    <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $data['language'], $code ); ?>>
                        <?php echo esc_html( sprintf( '%s (%s)', $label, $code ) ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="simple_hreflang_region"><strong><?php esc_html_e( 'Region', 'simple-hreflang' ); ?></strong></label><br />
            <select class="widefat" id="simple_hreflang_region" name="simple_hreflang_region">
                <?php foreach ( $regions as $code => $label ) : ?>
                    <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $data['region'], $code ); ?>>
                        <?php echo esc_html( '' === $code ? $label : sprintf( '%s (%s)', $label, $code ) ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label>
                <input type="checkbox" name="simple_hreflang_is_default" value="1" <?php checked( $data['is_default'] ); ?> />
                <?php esc_html_e( 'Use this page as x-default', 'simple-hreflang' ); ?>
            </label>
        </p>

        <p style="margin-top:12px;padding:10px;background:#f6f7f7;border:1px solid #dcdcde;">
            <strong><?php esc_html_e( 'Preview', 'simple-hreflang' ); ?>:</strong><br />
            <?php if ( ! empty( $data['hreflang'] ) ) : ?>
                <code><?php echo esc_html( $data['hreflang'] ); ?></code><br />
            <?php else : ?>
                <em><?php esc_html_e( 'Select a language to generate a hreflang value.', 'simple-hreflang' ); ?></em><br />
            <?php endif; ?>
            <?php if ( $data['is_default'] ) : ?>
                <code>x-default</code>
            <?php endif; ?>
        </p>

        <script>
        ( function() {
            const selectEl = document.getElementById( 'simple_hreflang_group' );
            const newInputEl = document.getElementById( 'simple_hreflang_group_new' );

            newInputEl.addEventListener( 'change', function() {
                if ( newInputEl.value.trim() ) {
                    selectEl.value = '';
                }
            } );

            newInputEl.addEventListener( 'keyup', function() {
                if ( newInputEl.value.trim() ) {
                    selectEl.value = '';
                }
            } );
        } )();
        </script>
        <?php
    }

    public function save( $post_id ) {
        if ( ! isset( $_POST['simple_hreflang_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['simple_hreflang_nonce'] ) ), 'simple_hreflang_save_meta' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $group = isset( $_POST['simple_hreflang_group'] ) ? Simple_Hreflang_Helpers::sanitize_group( sanitize_text_field( wp_unslash( $_POST['simple_hreflang_group'] ) ) ) : '';
        
        // Allow creating a new group from the text input
        if ( empty( $group ) && isset( $_POST['simple_hreflang_group_new'] ) ) {
            $group = Simple_Hreflang_Helpers::sanitize_group( sanitize_text_field( wp_unslash( $_POST['simple_hreflang_group_new'] ) ) );
        }

        $language   = isset( $_POST['simple_hreflang_lang'] ) ? Simple_Hreflang_Helpers::sanitize_language( sanitize_text_field( wp_unslash( $_POST['simple_hreflang_lang'] ) ) ) : '';
        $region     = isset( $_POST['simple_hreflang_region'] ) ? Simple_Hreflang_Helpers::sanitize_region( sanitize_text_field( wp_unslash( $_POST['simple_hreflang_region'] ) ) ) : '';
        $is_default = isset( $_POST['simple_hreflang_is_default'] ) ? 1 : 0;

        $this->repository->update_post_meta( $post_id, $group, $language, $region, $is_default );
    }
}
