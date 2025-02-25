$(document).ready(
    function() {
        if ($('[data-transmate-action]').length > 0) {
            
            function translateFrom(elementId, elementSiteId, fromSiteId) {
                const data = {
                    elementId,
                    elementSiteId,
                    fromSiteId
                };
                
                //$button.addClass('loading');

                Craft.sendActionRequest(
                        'POST',
                        'transmate/default/translate-from-site',
                        {
                            data
                        }
                    )
                    .then(() => {
                        window.location.reload();
                    })
                    .catch(({ response }) => {
                        Craft.cp.displayError(response.message || response.data.message);
                    })
                    .catch(error => {
                        console.error(error);
                    })
                    .then(() => {
                        //$button.removeClass('loading');
                    });
            }

            function translateTo(elementId, elementSiteId) {
                new Craft.TranslateElementsTo([elementId], elementSiteId);
            }
            
            $('[data-transmate-action]').on('click', function(e) {
                const $target = $(e.currentTarget);
                const action = $target.data('transmate-action');
                
                if (action === 'translateFrom') {
                    const elementId = $('#main-form').data('elementEditor').settings.elementId;
                    const siteId = $target.data('current-site-id');
                    const fromSiteId = $target.data('from-site-id');
                    
                    translateFrom(elementId, siteId, fromSiteId);
                }
                
                if (action === 'translateTo') {
                    const elementId = $('#main-form').data('elementEditor').settings.elementId;
                    const siteId = $target.data('current-site-id');
                    
                    translateTo(elementId, siteId);
                }
                
                
            });
        }
        
        /*
        if ($('[data-transmate-sidebar]').length > 0) {
            const $sidebar = $('[data-transmate-sidebar]');
            const $button = $sidebar.find('[data-transmate-sidebar-submit]');

            function isValid() {
                const type = $('select[name="translateType"]').val();

                if (type === 'translateFrom') {
                    const fromValue = $('select[name="translateFromSite"]').val();
                    return fromValue !== 'none';
                }

                if (type === 'translateTo') {
                    const toValueCount = $('input[name="translateToSites[]"]:checked').length;
                    return toValueCount > 0;
                }

                return false
            }

            function checkSubmit() {
                let isEnabled = isValid();

                if (isEnabled) {
                    $button.removeClass('disabled').attr('disabled', null);
                } else {
                    $button.addClass('disabled').attr('disabled', 'disabled');
                }
            }

            function updateFields() {
                const editor = $sidebar.closest('[data-element-editor]').data('elementEditor');
                editor.formObserver.pause();
                const type = $sidebar.find('select[name="translateType"]').val();

                $sidebar.find('[data-translate-options]').css({ display: 'none' });
                $sidebar.find('[data-translate-options="' + type + '"]').css({ display: '' });

                checkSubmit();
                editor.formObserver.resume();
            }

            function submitSidebarPanel() {
                const type = $('select[name="translateType"]').val();
                const entryId = $button.data('entry-id');
                const entrySiteId = $button.data('entry-site-id');

                const data = {
                    type,
                    entryId,
                    entrySiteId
                };

                if (type === 'translateFrom') {
                    const fromValue = $('select[name="translateFromSite"]').val();
                    data.fromSiteHandle = fromValue;
                }

                if (type === 'translateTo') {
                    let toValues = new Array();
                    $.each($('input[name="translateToSites[]"]:checked'), function() {
                        toValues.push($(this).val());
                    });
                    data.toSiteHandles = toValues;
                    
                    const saveAsDraft = $('select[name="saveAsDraft"]').val();
                    data.saveAsDraft = saveAsDraft;
                }
                
                $button.addClass('loading');

                Craft.sendActionRequest(
                        'POST',
                        'transmate/default/sidebar-translate',
                        {
                            data
                        }
                    )
                    .then(() => {
                        window.location.reload();
                    })
                    .catch(({ response }) => {
                        Craft.cp.displayError(response.message || response.data.message);
                    })
                    .catch(error => {
                        console.error(error);
                    })
                    .then(() => {
                        $button.removeClass('loading');
                    });

            }

            $sidebar.find('select[name="translateType"]').on('change', function() {
                updateFields();
            })

            $sidebar.find('select[name="translateFromSite"]').on('change', function() {
                checkSubmit();
            })

            $sidebar.find('input[name="translateToSites[]"]').on('change', function() {
                checkSubmit();
            })

            $button.on('click', function(e) {
                e.preventDefault();

                if (isValid()) {
                    submitSidebarPanel();
                }
            });

        }
         */
    }
);
