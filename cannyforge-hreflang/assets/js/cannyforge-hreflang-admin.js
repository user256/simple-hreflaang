( function() {
    function clearGroupSelectOnInput( groupInput ) {
        const container = groupInput.closest( 'form, .postbox, .inside, .cannyforge-hreflang-modal__dialog' );
        if ( ! container ) {
            return;
        }

        const groupSelect = container.querySelector( '.cannyforge-hreflang-group-select, #cannyforge_hreflang_modal_group' );
        if ( groupSelect && groupInput.value.trim() ) {
            groupSelect.value = '';
        }
    }

    function bindGroupInputSync( root ) {
        root.querySelectorAll( '.cannyforge-hreflang-group-new-input, #cannyforge_hreflang_modal_group_new' ).forEach( function( input ) {
            [ 'input', 'change', 'keyup' ].forEach( function( eventName ) {
                input.addEventListener( eventName, function() {
                    clearGroupSelectOnInput( input );
                } );
            } );
        } );
    }

    function initMetaboxEnhancements() {
        bindGroupInputSync( document );

        const languageSelect = document.getElementById( 'cannyforge_hreflang_lang' );
        const regionSelect = document.getElementById( 'cannyforge_hreflang_region' );
        const xDefaultCheckbox = document.querySelector( 'input[name="cannyforge_hreflang_is_default"]' );
        const previewValue = document.getElementById( 'cannyforge_hreflang_preview_value' );
        const previewXDefault = document.getElementById( 'cannyforge_hreflang_preview_x_default' );

        if ( ! languageSelect || ! regionSelect || ! previewValue || ! previewXDefault ) {
            return;
        }

        function renderPreview() {
            const language = String( languageSelect.value || '' ).trim().toLowerCase();
            const region = String( regionSelect.value || '' ).trim().toUpperCase();
            let hreflang = '';

            if ( language ) {
                hreflang = region ? language + '-' + region : language;
            }

            if ( hreflang ) {
                previewValue.innerHTML = '<code>' + hreflang + '</code><br>';
            } else {
                previewValue.innerHTML = '<em>Select a language to generate a hreflang value.</em><br>';
            }

            previewXDefault.innerHTML = xDefaultCheckbox && xDefaultCheckbox.checked ? '<code>x-default</code>' : '';
        }

        languageSelect.addEventListener( 'change', renderPreview );
        regionSelect.addEventListener( 'change', renderPreview );

        if ( xDefaultCheckbox ) {
            xDefaultCheckbox.addEventListener( 'change', renderPreview );
        }

        renderPreview();
    }

    function initSettingsPage() {
        const modal = document.getElementById( 'cannyforge_hreflang_modal' );

        if ( ! modal ) {
            return;
        }

        const config = window.cannyforgeHreflangAdmin || {};

        bindGroupInputSync( document );

        const openButton = document.getElementById( 'cannyforge_hreflang_add_to_group_btn' );
        const closeButton = document.getElementById( 'cannyforge_hreflang_modal_cancel' );
        const form = document.getElementById( 'cannyforge_hreflang_add_form' );
        const submitButton = document.getElementById( 'cannyforge_hreflang_modal_submit' );
        const modalTitle = document.getElementById( 'cannyforge_hreflang_modal_heading' );
        const groupSelect = document.getElementById( 'cannyforge_hreflang_modal_group' );
        const groupNewInput = document.getElementById( 'cannyforge_hreflang_modal_group_new' );
        const languageSelect = document.getElementById( 'cannyforge_hreflang_modal_language' );
        const regionSelect = document.getElementById( 'cannyforge_hreflang_modal_region' );
        const postSelectSection = document.getElementById( 'cannyforge_hreflang_modal_post_select_section' );
        const postSelect = document.getElementById( 'cannyforge_hreflang_modal_post_select' );
        const currentPageSection = document.getElementById( 'cannyforge_hreflang_modal_current_page_section' );
        const currentPageTitle = document.getElementById( 'cannyforge_hreflang_modal_current_page_title' );
        const hiddenPostId = document.getElementById( 'cannyforge_hreflang_modal_post_id' );
        const deleteButton = document.getElementById( 'cannyforge_hreflang_delete_all_btn' );
        const setDefaultNonce = document.getElementById( 'cannyforge_hreflang_set_default_nonce' );
        const ajaxUrl = config.ajaxUrl || window.ajaxurl || '';
        const groupHreflangMap = config.groupHreflangMap || {};
        const strings = config.strings || {};
        let deleteConfirmPending = false;
        let modalMode = 'add';

        function setSectionHidden( element, hidden ) {
            if ( element ) {
                element.hidden = hidden;
            }
        }

        function showStatus( message, isError ) {
            const status = document.getElementById( 'cannyforge_hreflang_status' );
            if ( ! status ) {
                return;
            }

            status.textContent = message || '';
            status.hidden = ! message;
            status.classList.toggle( 'is-error', !! isError );
            status.classList.toggle( 'is-success', ! isError );
        }

        function updateRegionOptions() {
            const selectedGroup = groupNewInput.value.trim() ? '' : groupSelect.value;
            const selectedLanguage = languageSelect.value;
            const currentPostId = hiddenPostId.value ? String( hiddenPostId.value ) : '';
            const usedEntries = selectedGroup && selectedLanguage && groupHreflangMap[ selectedGroup ] && groupHreflangMap[ selectedGroup ][ selectedLanguage ]
                ? groupHreflangMap[ selectedGroup ][ selectedLanguage ]
                : [];
            const usedRegions = usedEntries
                .filter( function( entry ) {
                    if ( ! entry || 'object' !== typeof entry ) {
                        return true;
                    }

                    return String( entry.postId || '' ) !== currentPostId;
                } )
                .map( function( entry ) {
                    if ( entry && 'object' === typeof entry ) {
                        return String( entry.region || '' );
                    }

                    return String( entry || '' );
                } );

            Array.from( regionSelect.options ).forEach( function( option ) {
                const shouldDisable = usedRegions.includes( String( option.value || '' ) );
                option.hidden = shouldDisable;
                option.disabled = shouldDisable;

                if ( shouldDisable && option.selected ) {
                    regionSelect.value = '';
                }
            } );
        }

        function openModal( mode, data ) {
            modalMode = mode;
            modal.hidden = false;
            modal.style.display = 'flex';
            form.reset();
            hiddenPostId.value = '';
            currentPageTitle.textContent = '';

            if ( mode === 'edit' ) {
                modalTitle.textContent = strings.editTitle || 'Edit hreflang';
                setSectionHidden( postSelectSection, true );
                setSectionHidden( currentPageSection, false );
                hiddenPostId.value = data.postId || '';
                currentPageTitle.textContent = data.title || '';
            } else {
                modalTitle.textContent = strings.addTitle || 'Add Page to Group';
                setSectionHidden( postSelectSection, false );
                setSectionHidden( currentPageSection, true );
            }

            groupSelect.value = data.group || '';
            groupNewInput.value = '';
            languageSelect.value = data.language || 'en';
            regionSelect.value = data.region || '';
            document.getElementById( 'cannyforge_hreflang_modal_x_default' ).checked = !! data.isDefault;
            updateRegionOptions();
        }

        function closeModal() {
            modal.hidden = true;
            modal.style.display = 'none';
        }

        function request( action, formData ) {
            if ( ! ajaxUrl ) {
                return Promise.reject( new Error( 'Missing ajax URL' ) );
            }

            formData.append( 'action', action );

            return fetch( ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
            } ).then( function( response ) {
                return response.json().then( function( payload ) {
                    return { ok: response.ok, payload: payload };
                } );
            } );
        }

        openButton.addEventListener( 'click', function() {
            openModal( 'add', {
                group: '',
                language: 'en',
                region: '',
                isDefault: false,
            } );
        } );

        document.querySelectorAll( '.cannyforge-hreflang-group-add-btn' ).forEach( function( btn ) {
            btn.addEventListener( 'click', function() {
                openModal( 'add', {
                    group: btn.getAttribute( 'data-group' ) || '',
                    language: 'en',
                    region: '',
                    isDefault: false,
                } );
            } );
        } );

        closeButton.addEventListener( 'click', closeModal );

        modal.addEventListener( 'click', function( event ) {
            if ( event.target === modal ) {
                closeModal();
            }
        } );

        document.addEventListener( 'keydown', function( event ) {
            if ( 'Escape' === event.key && ! modal.hidden ) {
                closeModal();
            }
        } );

        groupSelect.addEventListener( 'change', updateRegionOptions );
        groupNewInput.addEventListener( 'input', updateRegionOptions );
        languageSelect.addEventListener( 'change', updateRegionOptions );

        document.querySelectorAll( '.cannyforge_hreflang_edit_hreflang' ).forEach( function( button ) {
            button.addEventListener( 'click', function() {
                openModal( 'edit', {
                    postId: button.dataset.postId || '',
                    group: button.dataset.group || '',
                    language: button.dataset.language || 'en',
                    region: button.dataset.region || '',
                    isDefault: button.dataset.xDefault === '1',
                    title: button.dataset.title || '',
                } );
            } );
        } );

        form.addEventListener( 'submit', function( event ) {
            event.preventDefault();
            submitButton.disabled = true;
            submitButton.value = strings.saving || 'Saving...';

            const formData = new FormData( form );

            if ( postSelectSection && ! postSelectSection.hidden ) {
                if ( ! postSelect.value ) {
                    showStatus( strings.invalidPost || 'Invalid post selected.', true );
                    submitButton.disabled = false;
                    submitButton.value = strings.save || 'Save';
                    return;
                }

                formData.set( 'post_id', postSelect.value );
            }

            formData.append( 'group_new', groupNewInput.value.trim() );

            request( 'cannyforge_hreflang_add_to_group', formData )
                .then( function( result ) {
                    if ( result.ok && result.payload && result.payload.success ) {
                        showStatus(
                            modalMode === 'edit'
                                ? ( strings.updatedSuccess || 'Hreflang updated successfully!' )
                                : ( strings.addedSuccess || 'Page added to group successfully!' ),
                            false
                        );
                        closeModal();
                        window.location.reload();
                        return;
                    }

                    const fallbackMessage = modalMode === 'edit'
                        ? ( strings.updateError || 'Error saving hreflang.' )
                        : ( strings.addError || 'Error adding page to group.' );
                    const payloadMessage = result.payload && result.payload.data && result.payload.data.message
                        ? result.payload.data.message
                        : result.payload && typeof result.payload.data === 'string'
                            ? result.payload.data
                            : fallbackMessage;

                    showStatus( payloadMessage, true );
                } )
                .catch( function() {
                    showStatus(
                        modalMode === 'edit'
                            ? ( strings.updateNetworkError || 'Error: Failed to save hreflang.' )
                            : ( strings.addNetworkError || 'Error: Failed to add page to group.' ),
                        true
                    );
                } )
                .finally( function() {
                    submitButton.disabled = false;
                    submitButton.value = strings.save || 'Save';
                } );
        } );

        document.querySelectorAll( '.cannyforge_hreflang_set_default' ).forEach( function( button ) {
            button.addEventListener( 'click', function() {
                if ( ! button.dataset.postId ) {
                    showStatus( strings.invalidPost || 'Invalid post selected.', true );
                    return;
                }

                const originalText = button.textContent;
                button.disabled = true;
                button.textContent = strings.settingDefault || 'Setting...';

                const formData = new FormData();
                formData.append( 'post_id', button.dataset.postId );
                formData.append( 'cannyforge_hreflang_nonce_set_default', setDefaultNonce.value );

                request( 'cannyforge_hreflang_set_x_default', formData )
                    .then( function( result ) {
                        if ( result.ok && result.payload && result.payload.success ) {
                            const successMessage = result.payload.data && result.payload.data.message
                                ? result.payload.data.message
                                : ( strings.defaultSuccess || 'x-default set successfully.' );
                            showStatus( successMessage, false );
                            window.location.reload();
                            return;
                        }

                        const payloadMessage = result.payload && result.payload.data && result.payload.data.message
                            ? result.payload.data.message
                            : result.payload && typeof result.payload.data === 'string'
                                ? result.payload.data
                                : ( strings.defaultError || 'Error setting x-default.' );
                        showStatus( payloadMessage, true );
                    } )
                    .catch( function() {
                        showStatus( strings.defaultNetworkError || 'Error: Failed to set x-default.', true );
                    } )
                    .finally( function() {
                        button.disabled = false;
                        button.textContent = originalText || strings.setDefault || 'Set as x-default';
                    } );
            } );
        } );

        deleteButton.addEventListener( 'click', function() {
            if ( ! deleteConfirmPending ) {
                deleteConfirmPending = true;
                deleteButton.textContent = strings.confirmDelete || 'Click again to confirm delete';
                showStatus( strings.confirmDeleteHelp || 'Click delete again to confirm removing all groups.', true );

                window.setTimeout( function() {
                    deleteConfirmPending = false;
                    deleteButton.textContent = strings.deleteAll || 'Delete All Groups';
                    showStatus( '', false );
                }, 4000 );
                return;
            }

            deleteConfirmPending = false;
            deleteButton.disabled = true;
            deleteButton.textContent = strings.deleting || 'Deleting...';

            const formData = new FormData();
            formData.append( 'cannyforge_hreflang_nonce_delete', document.querySelector( 'input[name="cannyforge_hreflang_nonce_delete"]' ).value );

            request( 'cannyforge_hreflang_delete_all_groups', formData )
                .then( function( result ) {
                    if ( result.ok && result.payload && result.payload.success ) {
                        const successMessage = result.payload.data && result.payload.data.message
                            ? result.payload.data.message
                            : ( strings.deleteSuccess || 'All groups deleted!' );
                        showStatus( successMessage, false );
                        window.location.reload();
                        return;
                    }

                    const payloadMessage = result.payload && result.payload.data && result.payload.data.message
                        ? result.payload.data.message
                        : result.payload && typeof result.payload.data === 'string'
                            ? result.payload.data
                            : ( strings.deleteError || 'Error deleting groups.' );
                    showStatus( payloadMessage, true );
                } )
                .catch( function() {
                    showStatus( strings.deleteNetworkError || 'Error: Failed to delete groups.', true );
                } )
                .finally( function() {
                    deleteButton.disabled = false;
                    deleteButton.textContent = strings.deleteAll || 'Delete All Groups';
                } );
        } );
    }

    document.addEventListener( 'DOMContentLoaded', function() {
        initMetaboxEnhancements();
        initSettingsPage();
    } );
} )();
