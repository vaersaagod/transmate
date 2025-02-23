/** global: Craft */
/** global: Garnish */
/**
 * TranslateEntry js,
 * Based on Entry Mover (https://github.com/craftcms/cms/blob/80436653f2fad7a8b95b3b6e01d6ddec1ee2ffdf/src/web/assets/cp/src/js/EntryMover.js)
 */
Craft.TranslateElementsTo = Garnish.Base.extend({
    modal: null,
    cancelToken: null,

    elementIds: null,
    currentSiteId: null,

    sitesListContainer: null,
    sitesList: null,
    $cancelBtn: null,
    $selectBtn: null,
    sitesSelect: null,
    saveModeSwitch: null,

    init(elementIds, siteId) {
        this.elementIds = elementIds;
        this.currentSiteId = siteId

        this.createModal();
    },

    createModal() {
        const $container = $('<div class="modal transmate-modal fitted"/>');
        const $header = $('<div class="header"/>').appendTo($container);
        const headingId =
            'transmateModalHeading-' + Math.floor(Math.random() * 1000000);
        $('<h1/>', {
            text: Craft.t('transmate', 'Translate to'),
            id: headingId,
        }).appendTo($header);
        const $body = $('<div class="body"/>').appendTo($container);
        const $footer = $('<div/>', {
            class: 'footer',
        }).appendTo($container);

        this.$sitesListContainer = $(
            '<div class="transmate-modal--list"/>'
        ).appendTo($body);

        this.$sitesList = $('<fieldset/>', {
            class: 'chips',
            'aria-labelledby': headingId,
        }).appendTo(this.$sitesListContainer);

        $('<div class="buttons left secondary-buttons"/>').appendTo($footer);
        const $primaryButtons = $('<div class="buttons right"/>').appendTo($footer);
        this.$cancelBtn = $('<button/>', {
            type: 'button',
            class: 'btn',
            text: Craft.t('app', 'Cancel'),
        }).appendTo($primaryButtons);

        this.$selectBtn = Craft.ui
            .createSubmitButton({
                class: 'disabled',
                label: Craft.t('transmate', 'Translate'),
                spinner: true,
            })
            .attr('aria-disabled', 'true')
            .appendTo($primaryButtons);

        this.addListener(this.$cancelBtn, 'activate', 'cancel');
        this.addListener(this.$selectBtn, 'activate', 'selectSites');

        this.modal = new Garnish.Modal($container);
        this.getCompatibleSections();
    },

    getCompatibleSections() {
        if (this.cancelToken) {
            this.cancelToken.cancel();
        }

        this.$selectBtn.addClass('loading');
        this.cancelToken = axios.CancelToken.source();

        Craft.sendActionRequest('POST', 'transmate/default/translate-to-site-modal-data', {
                data: {
                    elementIds: this.elementIds,
                    siteId: this.currentSiteId,
                },
                cancelToken: this.cancelToken.token,
            })
            .then(({ data }) => {
                const listHtml = data?.listHtml;
                if (listHtml) {
                    this.$sitesList.html(listHtml);

                    this.sitesSelect = new Garnish.Select(
                        this.$sitesList,
                        this.$sitesList.find('.chip'),
                        {
                            vertical: true,
                            multi: true,
                            filter: (target) => {
                                return !$(target).closest('a[href],.toggle,.btn,[role=button]')
                                    .length;
                            },
                            checkboxMode: true,
                            onSelectionChange: () => {
                                if (this.sitesSelect.$selectedItems.length) {
                                    this.$selectBtn.removeClass('disabled');
                                } else {
                                    this.$selectBtn.toggleClass('disabled');
                                }
                            },
                        }
                    );
                    
                    /*
                    this.saveModeSwitch = new Garnish.Select(
                        this.$sitesList,
                        this.$sitesList.find('.lightswitch'),
                        {
                            vertical: true,
                            multi: true,
                            filter: (target) => {
                                return !$(target).closest('a[href],.toggle,.btn,[role=button]')
                                    .length;
                            },
                            checkboxMode: true,
                            onSelectionChange: () => {
                                if (this.sitesSelect.$selectedItems.length) {
                                    this.$selectBtn.removeClass('disabled');
                                } else {
                                    this.$selectBtn.toggleClass('disabled');
                                }
                            },
                        }
                    );*/
                }
            })
            .catch(({ response }) => {
                Craft.cp.displayError(response?.data?.message);
                this.modal.hide();
            })
            .finally(() => {
                this.$selectBtn.removeClass('loading');
                this.cancelToken = null;
            });
    },

    selectSites() {
        if (this.$selectBtn.hasClass('loading')) {
            return;
        }

        this.$selectBtn.addClass('loading');
        Craft.cp.announce(Craft.t('app', 'Loading'));
        
        $siteIds = [];
        
        this.sitesSelect.$selectedItems.each(function (i, item) {
            $siteIds.push(item.dataset.id);
        });

        let data = {
            siteId: this.currentSiteId,
            siteIds: $siteIds,
            elementIds: this.elementIds,
            saveAsDraft: this.$sitesList.find('.checkbox[name="transmateSaveAsDraft"]')[0].checked ? 'yes' : 'no'
        };
        
        Craft.sendActionRequest('POST', 'transmate/default/translate-elements-to-sites', {
                data: data,
            })
            .then((response) => {
                Craft.cp.displaySuccess(response.data.message);
                Craft.cp.announce(response.data.message);

                this.elementIndex.updateElements();
                this.elementIndex.$elements.attr('tabindex', '-1').focus();
                this.modal.hide();
            })
            .catch((e) => {
                if (e.response !== undefined && e.response.data !== undefined) {
                    Craft.cp.displayError(e.response.data.message);
                    Craft.cp.announce(e.response.data.message);
                }
            })
            .finally(() => {
                this.$selectBtn.removeClass('loading');
                this.modal.hide();
            });
    },

    cancel: function() {
        this.modal.hide();
    },
});
