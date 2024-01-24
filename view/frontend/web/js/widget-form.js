/**
 * Copyright © Alekseon sp. z o.o.
 * http://www.alekseon.com/
 */
define([
    'jquery',
    'Magento_Ui/js/modal/alert'
], function ($, alert) {
    'use strict';

    $.widget('mage.alekseonWidgetForm', {

        options: {
            storageCookiePrefix : 'storage_',
            storageExpires : 1,
            currentTab: 1,
            tabs: [],
            form: null,
            formSubmitUrl: 'formSubmitUrl',
            formId: ''
        },

        cookieStorage : {},

        _create: function () {
            this.options.form = document.getElementById(this.options.formId);

            let formCookieData = this.getFormCookieData(this.options.formId);
            if (formCookieData) {
                this.cookieStorage = formCookieData;
            }

            if (Object.keys(this.cookieStorage).length) {
                this.loadFormStorage();
            }
            this.addStorageListeners();


            $(this.options.form).on('form-action', (ev) => {
                ev.stopPropagation();

                switch (ev.detail.action_type) {
                    case 'next' :
                        if ($(this.options.form).validation && !$(this.options.form).validation('isValid')) {
                            break;
                        }
                        this.openTab(this.options.form, parseInt(ev.detail.tab_id, 10) + 1);
                        break;
                    case 'previous' :
                        this.openTab(this.options.form, parseInt(ev.detail.tab_id, 10) - 1);
                        break;
                    case 'submit' :
                        this.submitFormAction();
                        break;
                }
            });

        },

        loadFormStorage : function () {
            window.addEventListener('load', (ev) => {
                window.setTimeout(() => {
                    let selectors = Object.keys(this.cookieStorage).map((key) => {
                        return '#' + key;
                    });

                    selectors = selectors.join(',');
                    let fields = this.options.form.querySelectorAll(selectors);
                    let changeEvent = new CustomEvent('change');
                    fields.forEach((control) => {
                        let node = control.nodeName.toLowerCase();
                        let type = control.getAttribute('type');

                        if ('input' === node || 'textarea' === node) {
                            if ('checkbox' === type || 'radio' === type) {
                                control.checked = this.cookieStorage[control.id];
                            } else {
                                control.value = this.cookieStorage[control.id];
                            }
                        } else if ('select' === node) {
                            let options = control.options;
                            let selected = this.cookieStorage[control.id].split(',');

                            for (let i = 0; i < options.length; i++) {
                                options[i].selected = -1 !== selected.indexOf(options[i].value);
                            }
                        }
                        control.dispatchEvent(changeEvent);
                    });
                });
                });

        },

        addStorageListeners : function () {
            let debounceSaveTextInput = this.debounce(this.saveTextInput, 500);

            let controls = this.options.form.querySelectorAll('input, select, textarea');
            controls.forEach((control) => {
                let tag = control.tagName.toLowerCase();
                let type = control.getAttribute('type');

                if (('input' === tag && 'text' === type) || 'textarea' === tag) {
                    control.addEventListener('input', (ev) => {
                        debounceSaveTextInput(ev);
                    });
                } else if ('input' === tag && 'hidden' === type) {
                    let excluded = ['form_key'];
                    control.addEventListener('change', (ev) => {
                        if (-1 === excluded.indexOf(control.id)) {
                            this.cookieStorage[control.id] = control.value;
                            this.setFormCookie(this.options.formId);
                        }
                    });
                } else if ('input' === tag && 'file' !== type) {
                    control.addEventListener('change', (ev) => {
                        if ('checkbox' === type || 'radio' === type) {
                            this.cookieStorage[control.id] = control.checked;
                        } else {
                            this.cookieStorage[control.id] = control.value;
                        }
                        this.setFormCookie(this.options.formId);
                    });
                } else if ('select' === tag) {
                    control.addEventListener('change', (ev) => {
                        this.saveSelectInput(ev);
                    });
                }
            });
        },

        getFormCookieData : function (formId) {
            let name = encodeURIComponent(this.options.storageCookiePrefix + formId);
            let cookie = $.cookie(name);

            return cookie ? JSON.parse(decodeURIComponent(cookie)) : null;
        },

        setFormCookie : function (formId, data) {
            let name = encodeURIComponent(this.options.storageCookiePrefix + formId);
            data = data || this.cookieStorage;

            data = encodeURIComponent(JSON.stringify(data));

            $.cookie(name, data,
                {
                    domain : '',
                    expires : this.options.storageExpires,
                    path : '/',
                    secure : true,
                    samesite : 'strict'
                }
            );
            return this;
        },

        clearFormCookie : function (formId) {
            let name = encodeURIComponent(this.options.storageCookiePrefix + formId);
            $.cookie(name, null, {domain : '', path: '/' });
            return this;
        },

        debounce : function (fn, timeout) {
            let t;
            return function () {
                let a = arguments;
                window.clearTimeout(t);
                t = window.setTimeout(() => fn.apply(this, a), timeout);
            }.bind(this);
        },

        saveTextInput : function (ev) {
            if (ev.target.value.trim()) {
                this.cookieStorage[ev.target.id] = ev.target.value;
                this.setFormCookie(this.options.formId);
            }
            return this;
        },

        saveSelectInput : function (ev) {
            let result = [];
            let selected = ev.target.selectedOptions;
            for (let i = 0; i < selected.length; i++) {
                result.push(selected[i].value);
            }

            this.cookieStorage[ev.target.id] = result.join(',');
            this.setFormCookie(this.options.formId);
            return this;
        },

        openTab: function (form, tabIndex) {
            $(this.options.form).find('#form-tab-fieldset-' + this.options.currentTab).slideUp();
            $(this.options.form).find('#form-tab-actions-' + this.options.currentTab).hide();

            setTimeout(() => {
                this.options.currentTab = tabIndex;

                $(this.options.form).find('#form-tab-fieldset-' + this.options.currentTab).slideDown();
                $(this.options.form).find('#form-tab-actions-' + this.options.currentTab).show();
            }, "100");
        },

        submitFormAction: function () {
            if ($(this.options.form).validation && !$(this.options.form).validation('isValid')) {
                return false;
            }

            if (this.options.tabs[this.options.currentTab + 1] !== undefined) {
                this.openTab(this.options, this.options.currentTab + 1);
                return;
            }

            let self = this;
            const formData = new FormData(this.options.form);

            $.ajax({
                url: this.options.formSubmitUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                showLoader: true
            }).done(function (response) {
                if (response.errors) {
                    self.onError(response);
                } else {
                    self.onSuccess(response);
                }
            }).fail(function (error) {
                self.onError(error.responseJSON);
            }).complete(function() {
                self.onComplete();
            });
        },

        onComplete: function() {
        },

        onError: function(response) {
            alert({
                title: $.mage.__('Error'),
                content: response.message
            });
        },

        onSuccess: function(response) {
            alert({
                title: response.title,
                content: response.message
            });
            this.cookieStorage = {};
            this.clearFormCookie(this.options.formId);

            this.options.form.reset();
            if (this.options.currentTab !== 1) {
                this.openTab(this.options.form, 1);
            }
        }
    });

    return $.mage.alekseonWidgetForm;
});
