<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Simple_Hreflang_Settings {
    private $repository;

    public function __construct( Simple_Hreflang_Repository $repository ) {
        $this->repository = $repository;
    }

    public function register_menu() {
        add_options_page(
            __( 'Simple Hreflang', 'simple-hreflang' ),
            __( 'Simple Hreflang', 'simple-hreflang' ),
            'manage_options',
            'simple-hreflang',
            array( $this, 'render_page' )
        );
    }

    public function register_settings() {
        // Handle flush rewrite rules
        if ( isset( $_POST['simple_hreflang_flush_rules'] ) ) {
            if ( ! isset( $_POST['simple_hreflang_flush_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['simple_hreflang_flush_nonce'] ) ), 'simple_hreflang_flush_rules' ) ) {
                wp_die( 'Security check failed' );
            }
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( 'Unauthorized' );
            }
            flush_rewrite_rules();
            add_action( 'admin_notices', array( $this, 'render_flush_notice' ) );
        }

        register_setting(
            'simple_hreflang_settings_group',
            Simple_Hreflang_Repository::OPTION_SETTINGS,
            array( $this, 'sanitize_settings' )
        );

        add_settings_section(
            'simple_hreflang_main',
            __( 'General settings', 'simple-hreflang' ),
            '__return_false',
            'simple-hreflang'
        );

        add_settings_field(
            'post_types',
            __( 'Enabled post types', 'simple-hreflang' ),
            array( $this, 'render_post_types_field' ),
            'simple-hreflang',
            'simple_hreflang_main'
        );

        add_settings_field(
            'min_group_size',
            __( 'Minimum group size for sitemap', 'simple-hreflang' ),
            array( $this, 'render_min_group_size_field' ),
            'simple-hreflang',
            'simple_hreflang_main'
        );
    }

    public function render_flush_notice() {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Rewrite rules flushed successfully!', 'simple-hreflang' ); ?></p>
        </div>
        <?php
    }

    public function sanitize_settings( $input ) {
        $input = is_array( $input ) ? $input : array();

        $post_types = isset( $input['post_types'] ) && is_array( $input['post_types'] ) ? array_map( 'sanitize_key', $input['post_types'] ) : array( 'page' );
        $post_types = array_values( array_filter( $post_types, 'post_type_exists' ) );

        return array(
            'post_types'      => empty( $post_types ) ? array( 'page' ) : $post_types,
            'min_group_size'  => max( 1, (int) ( $input['min_group_size'] ?? 2 ) ),
            'include_private' => 0,
        );
    }

    public function render_post_types_field() {
        $settings   = $this->repository->get_settings();
        $selected   = isset( $settings['post_types'] ) ? (array) $settings['post_types'] : array( 'page' );
        $post_types = get_post_types( array( 'public' => true ), 'objects' );

        foreach ( $post_types as $post_type ) {
            echo '<label style="display:block;margin-bottom:4px;">';
            echo '<input type="checkbox" name="' . esc_attr( Simple_Hreflang_Repository::OPTION_SETTINGS ) . '[post_types][]" value="' . esc_attr( $post_type->name ) . '" ' . checked( in_array( $post_type->name, $selected, true ), true, false ) . ' /> ';
            echo esc_html( $post_type->labels->singular_name );
            echo '</label>';
        }
    }

    public function render_min_group_size_field() {
        $settings = $this->repository->get_settings();
        ?>
        <input type="number" min="1" step="1" name="<?php echo esc_attr( Simple_Hreflang_Repository::OPTION_SETTINGS ); ?>[min_group_size]" value="<?php echo esc_attr( (int) $settings['min_group_size'] ); ?>" />
        <p class="description"><?php esc_html_e( 'Groups smaller than this will be skipped from the sitemap.', 'simple-hreflang' ); ?></p>
        <?php
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $rows = $this->repository->get_grouped_posts();
        usort(
            $rows,
            static function ( $a, $b ) {
                return strcmp( $a['group'] . $a['post_title'], $b['group'] . $b['post_title'] );
            }
        );

        $group_hreflang_map = array();
        $groups_with_default = array();
        foreach ( $rows as $row ) {
            if ( $row['is_default'] ) {
                $groups_with_default[ $row['group'] ] = true;
            }

            if ( empty( $row['group'] ) || empty( $row['language'] ) ) {
                continue;
            }

            $group_hreflang_map[ $row['group'] ][ $row['language'] ][] = $row['region'];
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Simple Hreflang', 'simple-hreflang' ); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'simple_hreflang_settings_group' );
                do_settings_sections( 'simple-hreflang' );
                submit_button();
                ?>
            </form>

            <hr />

            <h2><?php esc_html_e( 'Sitemap URL', 'simple-hreflang' ); ?></h2>
            <p>
                <code><?php echo esc_html( home_url( '/hreflang-sitemap.xml' ) ); ?></code>
            </p>
            <p class="description">
                <?php esc_html_e( 'If the sitemap is not accessible, click the button below to flush rewrite rules.', 'simple-hreflang' ); ?>
            </p>
            <form method="post" style="display:inline;">
                <?php wp_nonce_field( 'simple_hreflang_flush_rules', 'simple_hreflang_flush_nonce' ); ?>
                <button type="submit" name="simple_hreflang_flush_rules" class="button">
                    <?php esc_html_e( 'Flush Rewrite Rules', 'simple-hreflang' ); ?>
                </button>
            </form>

            <h2><?php esc_html_e( 'Grouped pages', 'simple-hreflang' ); ?></h2>
            <p><?php esc_html_e( 'Add, create and edit groups here, or in the page builder directly', 'simple-hreflang' ); ?></p>

            <div style="margin-bottom:12px;">
                <button type="button" class="button button-primary" id="simple_hreflang_add_to_group_btn">
                    <?php esc_html_e( '+ Add to Group', 'simple-hreflang' ); ?>
                </button>
                <button type="button" class="button button-secondary" id="simple_hreflang_delete_all_btn" style="margin-left:10px;">
                    <?php esc_html_e( 'Delete All Groups', 'simple-hreflang' ); ?>
                </button>
                <?php wp_nonce_field( 'simple_hreflang_delete_all_groups', 'simple_hreflang_nonce_delete', false ); ?>
            </div>
            <div id="simple_hreflang_status" style="margin-bottom:16px;display:none;padding:10px;border-radius:4px;"></div>
            <input type="hidden" id="simple_hreflang_set_default_nonce" value="<?php echo esc_attr( wp_create_nonce( 'simple_hreflang_set_x_default' ) ); ?>" />

            <style>
                .group-separator td {
                    background: #f9f9f9;
                    font-weight: 600;
                    padding: 10px 8px;
                    border-top: 2px solid #ccc;
                }

                .group-start { border-top: 3px solid #ddd !important; }
            </style>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Group', 'simple-hreflang' ); ?></th>
                        <th><?php esc_html_e( 'Page', 'simple-hreflang' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'simple-hreflang' ); ?></th>
                        <th><?php esc_html_e( 'Language', 'simple-hreflang' ); ?></th>
                        <th><?php esc_html_e( 'Region', 'simple-hreflang' ); ?></th>
                        <th><?php esc_html_e( 'Hreflang', 'simple-hreflang' ); ?></th>
                        <th><?php esc_html_e( 'x-default', 'simple-hreflang' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'simple-hreflang' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $rows ) ) : ?>
                        <tr>
                            <td colspan="8"><?php esc_html_e( 'No grouped pages found yet.', 'simple-hreflang' ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php $prev_group = ''; ?>
                        <?php foreach ( $rows as $row ) : ?>
                            <?php if ( $prev_group !== $row['group'] ) : ?>
                                <tr class="group-separator">
                                    <td colspan="8">
                                        <?php echo esc_html__( 'Group:', 'simple-hreflang' ); ?> <strong><?php echo esc_html( $row['group'] ); ?></strong>
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php $classes = ( $prev_group !== $row['group'] ) ? 'group-start' : ''; ?>
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
                                <td><?php echo $row['is_default'] ? '&#10003;' : '—'; ?></td>
                                <td>
                                    <button type="button" class="button button-secondary simple_hreflang_edit_hreflang" data-post-id="<?php echo esc_attr( $row['post_id'] ); ?>" data-group="<?php echo esc_attr( $row['group'] ); ?>" data-language="<?php echo esc_attr( $row['language'] ); ?>" data-region="<?php echo esc_attr( $row['region'] ); ?>" data-x-default="<?php echo $row['is_default'] ? '1' : '0'; ?>" data-title="<?php echo esc_attr( $row['post_title'] ); ?>">
                                        <?php esc_html_e( 'Edit hreflang', 'simple-hreflang' ); ?>
                                    </button>
                                    <?php if ( empty( $groups_with_default[ $row['group'] ] ) && ! $row['is_default'] ) : ?>
                                        <button type="button" class="button button-primary simple_hreflang_set_default" data-post-id="<?php echo esc_attr( $row['post_id'] ); ?>">
                                            <?php esc_html_e( 'Set as x-default', 'simple-hreflang' ); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php $prev_group = $row['group']; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Add to Group Modal -->
        <div id="simple_hreflang_modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;justify-content:center;align-items:center;">
            <div style="background:white;padding:20px;border-radius:5px;box-shadow:0 3px 10px rgba(0,0,0,0.3);max-width:400px;width:90%;">
                <h3 id="sh_modal_heading" style="margin-top:0;"><?php esc_html_e( 'Add Page to Group', 'simple-hreflang' ); ?></h3>
                
                <form id="simple_hreflang_add_form">
                    <?php wp_nonce_field( 'simple_hreflang_add_to_group', 'simple_hreflang_nonce_add' ); ?>

                    <p>
                        <label for="sh_modal_group"><strong><?php esc_html_e( 'Group Name', 'simple-hreflang' ); ?></strong></label><br />
                        <select id="sh_modal_group" name="group" class="widefat">
                            <option value=""><?php esc_html_e( 'Create new group...', 'simple-hreflang' ); ?></option>
                            <?php
                            $groups = $this->repository->get_all_groups();
                            foreach ( $groups as $group ) {
                                echo '<option value="' . esc_attr( $group ) . '">' . esc_html( $group ) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description" style="margin-top:6px;"><?php esc_html_e( 'Or type a new group name below:', 'simple-hreflang' ); ?></p>
                        <input type="text" id="sh_modal_group_new" class="widefat" placeholder="pricing-pages" style="margin-top:6px;" />
                        <p class="description"><?php esc_html_e( 'Use lowercase with hyphens. Leave empty to use selection above.', 'simple-hreflang' ); ?></p>
                    </p>

                    <p id="sh_modal_post_select_section">
                        <label for="sh_modal_post_select"><strong><?php esc_html_e( 'Select Page', 'simple-hreflang' ); ?></strong></label><br />
                        <select id="sh_modal_post_select" name="" class="widefat">
                            <option value=""><?php esc_html_e( 'Choose a page...', 'simple-hreflang' ); ?></option>
                            <?php
                            $post_types = $this->repository->get_enabled_post_types();
                            $posts      = get_posts(
                                array(
                                    'post_type'      => $post_types,
                                    'post_status'    => array( 'publish', 'draft', 'pending' ),
                                    'posts_per_page' => 200,
                                    'orderby'        => 'title',
                                    'order'          => 'ASC',
                                    'meta_query'     => array(
                                        array(
                                            'key'     => Simple_Hreflang_Repository::META_GROUP,
                                            'compare' => 'NOT EXISTS',
                                        ),
                                    ),
                                )
                            );
                            foreach ( $posts as $post ) {
                                echo '<option value="' . esc_attr( $post->ID ) . '">' . esc_html( get_the_title( $post ) ) . ' (' . esc_html( $post->post_status ) . ')</option>';
                            }
                            ?>
                        </select>
                    </p>
                    <p id="sh_modal_current_page_section" style="display:none;">
                        <strong><?php esc_html_e( 'Editing page', 'simple-hreflang' ); ?>:</strong>
                        <span id="sh_modal_current_page_title"></span>
                    </p>
                    <input type="hidden" id="sh_modal_post_id" name="post_id" value="" />

                    <p>
                        <label for="sh_modal_language"><strong><?php esc_html_e( 'Language', 'simple-hreflang' ); ?></strong></label><br />
                        <select id="sh_modal_language" name="language" class="widefat" required>
                            <option value=""><?php esc_html_e( 'Select language', 'simple-hreflang' ); ?></option>
                            <?php
                            $languages = Simple_Hreflang_Helpers::get_languages();
                            foreach ( $languages as $code => $label ) {
                                echo '<option value="' . esc_attr( $code ) . '">' . esc_html( sprintf( '%s (%s)', $label, $code ) ) . '</option>';
                            }
                            ?>
                        </select>
                    </p>

                    <p>
                        <label for="sh_modal_region"><strong><?php esc_html_e( 'Region', 'simple-hreflang' ); ?></strong></label><br />
                        <select id="sh_modal_region" name="region" class="widefat">
                            <?php
                            $regions = Simple_Hreflang_Helpers::get_regions();
                            foreach ( $regions as $code => $label ) {
                                echo '<option value="' . esc_attr( $code ) . '" data-label="' . esc_attr( $label ) . '">' . esc_html( '' === $code ? $label : sprintf( '%s (%s)', $label, $code ) ) . '</option>';
                            }
                            ?>
                        </select>
                    </p>

                    <p>
                        <label>
                            <input type="checkbox" id="sh_modal_x_default" name="is_default" value="1" />
                            <?php esc_html_e( 'Use as x-default', 'simple-hreflang' ); ?>
                        </label>
                    </p>

                    <div style="margin-top:20px;display:flex;gap:10px;">
                        <input type="submit" class="button button-primary" id="sh_modal_submit" style="min-width:120px;" value="Save" />
                        <button type="button" class="button" id="sh_modal_cancel"><?php esc_html_e( 'Cancel', 'simple-hreflang' ); ?></button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        ( function() {
            const modal       = document.getElementById( 'simple_hreflang_modal' );
            const openBtn     = document.getElementById( 'simple_hreflang_add_to_group_btn' );
            const closeBtn    = document.getElementById( 'sh_modal_cancel' );
            const form        = document.getElementById( 'simple_hreflang_add_form' );
            const submitBtn   = document.getElementById( 'sh_modal_submit' );
            const modalTitle  = document.getElementById( 'sh_modal_heading' );
            const groupSelect = document.getElementById( 'sh_modal_group' );
            const groupNewInput = document.getElementById( 'sh_modal_group_new' );
            const languageSelect = document.getElementById( 'sh_modal_language' );
            const regionSelect = document.getElementById( 'sh_modal_region' );
            const postSelectSection = document.getElementById( 'sh_modal_post_select_section' );
            const postSelect = document.getElementById( 'sh_modal_post_select' );
            const currentPageSection = document.getElementById( 'sh_modal_current_page_section' );
            const currentPageTitle = document.getElementById( 'sh_modal_current_page_title' );
            const hiddenPostId = document.getElementById( 'sh_modal_post_id' );
            const deleteBtn   = document.getElementById( 'simple_hreflang_delete_all_btn' );
            const groupHreflangMap = <?php echo wp_json_encode( $group_hreflang_map ); ?>;
            let modalMode = 'add';

            function openModal( mode, data ) {
                modalMode = mode;
                modal.style.display = 'flex';
                form.reset();
                hiddenPostId.value = '';
                currentPageTitle.textContent = '';

                if ( mode === 'edit' ) {
                    modalTitle.textContent = '<?php esc_js( __( 'Edit hreflang', 'simple-hreflang' ) ); ?>';
                    postSelectSection.style.display = 'none';
                    currentPageSection.style.display = 'block';
                    hiddenPostId.value = data.postId || '';
                    currentPageTitle.textContent = data.title || '';
                } else {
                    modalTitle.textContent = '<?php esc_js( __( 'Add Page to Group', 'simple-hreflang' ) ); ?>';
                    postSelectSection.style.display = 'block';
                    currentPageSection.style.display = 'none';
                }

                groupSelect.value = data.group || '';
                groupNewInput.value = '';
                languageSelect.value = data.language || 'en';
                regionSelect.value = data.region || '';
                document.getElementById( 'sh_modal_x_default' ).checked = !! data.isDefault;
            }

            openBtn.addEventListener( 'click', function() {
                openModal( 'add', {
                    group: '',
                    language: 'en',
                    region: '',
                    isDefault: false,
                } );
            } );

            closeBtn.addEventListener( 'click', function() {
                modal.style.display = 'none';
                form.reset();
            } );

            document.querySelectorAll( '.simple_hreflang_edit_hreflang' ).forEach( function( button ) {
                button.addEventListener( 'click', function() {
                    openModal( 'edit', {
                        postId: button.getAttribute( 'data-post-id' ),
                        group: button.getAttribute( 'data-group' ),
                        language: button.getAttribute( 'data-language' ),
                        region: button.getAttribute( 'data-region' ),
                        isDefault: button.getAttribute( 'data-x-default' ) === '1',
                        title: button.getAttribute( 'data-title' ),
                    } );
                } );
            } );


            closeBtn.addEventListener( 'click', function() {
                modal.style.display = 'none';
                form.reset();
            } );

            modal.addEventListener( 'click', function( e ) {
                if ( e.target === modal ) {
                    modal.style.display = 'none';
                    form.reset();
                }
            } );

            // Clear dropdown when typing a new group name
            groupNewInput.addEventListener( 'input', function() {
                if ( groupNewInput.value.trim() ) {
                    groupSelect.value = '';
                }
            } );

            function showStatus( message, isError ) {
                const status = document.getElementById( 'simple_hreflang_status' );
                status.style.display = 'block';
                status.textContent = message;
                status.style.backgroundColor = isError ? '#fdd' : '#dff0d8';
                status.style.color = isError ? '#900' : '#263238';
                status.style.border = isError ? '1px solid #f5c6cb' : '1px solid #c3e6cb';
            }

            form.addEventListener( 'submit', function( e ) {
                e.preventDefault();

                if ( postSelectSection.style.display !== 'none' ) {
                    hiddenPostId.value = postSelect.value;
                }

                const formData = new FormData( form );

                if ( groupNewInput.value.trim() ) {
                    formData.set( 'group_new', groupNewInput.value.trim() );
                }

                formData.append( 'action', 'simple_hreflang_add_to_group' );

                submitBtn.disabled = true;
                submitBtn.value = 'Saving...';

                fetch( ajaxurl, {
                    method: 'POST',
                    body: formData
                } )
                .then( response => response.json().catch( () => null ) )
                .then( data => {
                    if ( data && data.success ) {
                        showStatus( modalMode === 'edit' ? '<?php esc_js( __( 'Hreflang updated successfully!', 'simple-hreflang' ) ); ?>' : '<?php esc_js( __( 'Page added to group successfully!', 'simple-hreflang' ) ); ?>', false );
                        modal.style.display = 'none';
                        form.reset();
                        location.reload();
                    } else {
                        const message = data && data.data ? data.data : modalMode === 'edit' ? '<?php esc_js( __( 'Error saving hreflang.', 'simple-hreflang' ) ); ?>' : '<?php esc_js( __( 'Error adding page to group.', 'simple-hreflang' ) ); ?>';
                        showStatus( message, true );
                    }
                } )
                .catch( err => {
                    showStatus( modalMode === 'edit' ? '<?php esc_js( __( 'Error: Failed to save hreflang.', 'simple-hreflang' ) ); ?>' : '<?php esc_js( __( 'Error: Failed to add page to group.', 'simple-hreflang' ) ); ?>', true );
                    console.error( err );
                } )
                .finally( () => {
                    submitBtn.disabled = false;
                    submitBtn.value = 'Save';
                } );
            } );

            const setDefaultButtons = document.querySelectorAll( '.simple_hreflang_set_default' );
            setDefaultButtons.forEach( function( button ) {
                button.addEventListener( 'click', function() {
                    const postId = button.getAttribute( 'data-post-id' );
                    if ( ! postId ) {
                        showStatus( '<?php esc_js( __( 'Invalid post selected.', 'simple-hreflang' ) ); ?>', true );
                        return;
                    }

                    button.disabled = true;
                    button.textContent = '<?php esc_js( __( 'Setting...', 'simple-hreflang' ) ); ?>';

                    const formData = new FormData();
                    formData.append( 'action', 'simple_hreflang_set_x_default' );
                    formData.append( 'post_id', postId );
                    formData.append( 'simple_hreflang_nonce_set_default', document.getElementById( 'simple_hreflang_set_default_nonce' ).value );

                    fetch( ajaxurl, {
                        method: 'POST',
                        body: formData
                    } )
                    .then( response => response.json().catch( () => null ) )
                    .then( data => {
                        if ( data && data.success ) {
                            showStatus( data.data.message || '<?php esc_js( __( 'x-default set successfully.', 'simple-hreflang' ) ); ?>', false );
                            location.reload();
                        } else {
                            const message = data && data.data ? data.data : '<?php esc_js( __( 'Error setting x-default.', 'simple-hreflang' ) ); ?>';
                            showStatus( message, true );
                        }
                    } )
                    .catch( err => {
                        showStatus( '<?php esc_js( __( 'Error: Failed to set x-default.', 'simple-hreflang' ) ); ?>', true );
                        console.error( err );
                    } )
                    .finally( () => {
                        button.disabled = false;
                        button.textContent = '<?php esc_js( __( 'Set as x-default', 'simple-hreflang' ) ); ?>';
                    } );
                } );
            } );

            let deleteConfirm = false;
            deleteBtn.addEventListener( 'click', function() {
                if ( ! deleteConfirm ) {
                    deleteConfirm = true;
                    deleteBtn.textContent = '<?php esc_js( __( 'Click again to confirm delete', 'simple-hreflang' ) ); ?>';
                    showStatus( '<?php esc_js( __( 'Click delete again to confirm removing all groups.', 'simple-hreflang' ) ); ?>', true );
                    setTimeout( function() {
                        deleteConfirm = false;
                        deleteBtn.textContent = '<?php esc_js( __( 'Delete All Groups', 'simple-hreflang' ) ); ?>';
                        showStatus( '', false );
                        document.getElementById( 'simple_hreflang_status' ).style.display = 'none';
                    }, 5000 );
                    return;
                }

                deleteConfirm = false;
                deleteBtn.disabled = true;
                deleteBtn.textContent = '<?php esc_js( __( 'Deleting...', 'simple-hreflang' ) ); ?>';

                const formData = new FormData();
                formData.append( 'action', 'simple_hreflang_delete_all_groups' );
                formData.append( 'simple_hreflang_nonce_delete', document.querySelector( 'input[name="simple_hreflang_nonce_delete"]' ).value );

                fetch( ajaxurl, {
                    method: 'POST',
                    body: formData
                } )
                .then( response => response.json().catch( () => null ) )
                .then( data => {
                    if ( data && data.success ) {
                        showStatus( data.data.message || '<?php esc_js( __( 'All groups deleted!', 'simple-hreflang' ) ); ?>', false );
                        location.reload();
                    } else {
                        const message = data && data.data ? data.data : '<?php esc_js( __( 'Error deleting groups.', 'simple-hreflang' ) ); ?>';
                        showStatus( message, true );
                    }
                } )
                .catch( err => {
                    showStatus( '<?php esc_js( __( 'Error: Failed to delete groups.', 'simple-hreflang' ) ); ?>', true );
                    console.error( err );
                } )
                .finally( () => {
                    deleteBtn.disabled = false;
                    deleteBtn.textContent = '<?php esc_js( __( 'Delete All Groups', 'simple-hreflang' ) ); ?>';
                } );
            } );
        } )();
        </script>
        <?php
    }
}
