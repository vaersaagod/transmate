/** global: Craft */
/** global: Garnish */
/** global: $ */
/** global: jQuery */

Craft.TranslateFieldModal = Garnish.Base.extend({
    editor: null,
    fromSites: null,

    init($btn, $field) {
        this.editor = $field.closest('[data-element-editor]').data('elementEditor');

        const headingId =
            'translate-field-heading-' + Math.floor(Math.random() * 1000000);

        const $hudContent = $('<div/>', {
            class: 'modal fitted translate-field-modal',
            'aria-labelledby': headingId
        });
        const $body = $('<div/>', {
            class: 'body'
        }).appendTo($hudContent);

        $body.append(
            `<div class="header"><h1 id="${headingId}" class="h2">${Craft.t(
                'transmate',
                'Translate “{name}” value',
                {
                    name: $btn.data('label')
                }
            )}</h1></div>`
        );

        const $form = Craft.createForm().appendTo($body);
        $form.append(Craft.getCsrfInput());

        const $fields = $('<div/>', {
            class: 'flex flex-end flex-nowrap'
        }).appendTo($form);

        const $siteSelectField = Craft.ui
            .createSelectField({
                label: Craft.t('transmate', 'Translate from'),
                class: ['fullwidth'],
                options: $btn.data('sites').map((s) => ({
                    label: s.name,
                    value: s.id
                }))
            })
            .addClass('flex-grow')
            .appendTo($fields);

        Craft.ui
            .createSubmitButton({
                label: Craft.t('transmate', 'Translate'),
                spinner: true
            })
            .appendTo($fields);

        const modal = new Garnish.Modal($hudContent);

        const $siteSelect = $siteSelectField.find('select').focus();

        this.addListener($form, 'submit', async (ev) => {
            ev.preventDefault();
            const $submitBtn = $form.find('[type=submit]');
            $submitBtn.addClass('loading');
            Craft.cp.announce(Craft.t('app', 'Loading'));

            if (this.editor) {
                await this.editor.checkForm();
                this.editor.savingDraft = true;
            }

            let elementId;
            let siteId;
            if (this.editor) {
                elementId = this.editor.getDraftElementId($btn.data('element-id'));
                siteId = this.editor.settings.siteId;
            } else {
                elementId = $btn.data('element-id');
                siteId = $btn.data('site-id');
            }

            try {
                const response = await Craft.sendActionRequest(
                    'POST',
                    'transmate/default/translate-field-from-site',
                    {
                        data: {
                            elementId,
                            siteId,
                            fromSiteId: parseInt($siteSelect.val()),
                            layoutElementUid: $btn.data('layout-element'),
                            layoutElementLabel: $btn.data('label'),
                            namespace: $btn.data('namespace')
                        }
                    }
                );

                const {
                    fieldHtml,
                    headHtml,
                    bodyHtml,
                    message
                } = response.data;

                if (this.editor) {
                    this.editor._handleSaveDraftResponse(response);
                }

                const $newField = $(fieldHtml);
                $field.replaceWith($newField);
                // Execute the response JS first so any Selectize inputs, etc.,
                // get instantiated before field toggles
                await Craft.appendHeadHtml(headHtml);
                await Craft.appendBodyHtml(bodyHtml);
                Craft.initUiElements($newField);

                $newField.find('input:visible,textarea:visible').first().focus();

                Craft.cp.displaySuccess(message);
            } catch (error) {
                Craft.cp.displayError(error.response.data.message || null);
            } finally {
                $submitBtn.removeClass('loading');
                Craft.cp.announce(Craft.t('app', 'Loading complete'));
                modal.hide();

                if (this.editor) {
                    this.editor.savingDraft = false;
                }
            }
        });
    }
});
