$(document).ready(
    function() {
        if ($('[data-transmate-action]').length > 0) {

            function translateFrom(elementId, elementSiteId, fromSiteId) {
                const data = {
                    elementId,
                    elementSiteId,
                    fromSiteId
                };

                const $button = $('[data-transmate-disclosure-button]');
                $button.addClass('loading');

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
                        $button.removeClass('loading');
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

        // Initialise field action translate menus
        Garnish.$bod.on('click', '[data-transmate-field-translate]', (ev) => {
            const $target = $(ev.currentTarget);
            const $field = $target
                .closest('.menu')
                .data('disclosureMenu')
                ?.$trigger.closest('.field');
            if ($target && $field) {
                new Craft.TranslateFieldModal($target, $field);
            }
        });

    }
);
