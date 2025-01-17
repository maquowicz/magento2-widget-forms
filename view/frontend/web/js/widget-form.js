/**
 * Copyright © Alekseon sp. z o.o.
 * http://www.alekseon.com/
 */
define([
    'jquery',
    'Magento_Customer/js/customer-data',
    'Magento_Ui/js/modal/alert'
], function ($, customerData, alert) {
    'use strict';

    $.widget('mage.alekseonWidgetForm', {

        options: {
            storageCookiePrefix : 'storage_',
            storageExpires : 1,
            currentTab: 1,
            tabs: [],
            form: null,
            formSubmitUrl: 'formSubmitUrl',
            formId: '',
            widgetConfig : {},
        },

        cookieStorage : {},
        messages : [],

        _create: function () {
            this.options.form = document.getElementById(this.options.formId);

            if (!this.options.widgetConfig.form_mode) {
                this.options.widgetConfig.form_mode = 'new';
            }
            this.loadForm().then((data) => {
                if ('new' === this.options.widgetConfig.form_mode) {
                    let formCookieData = this.getFormCookieData(this.options.formId);
                    if (formCookieData) {
                        this.cookieStorage = formCookieData;
                    }

                    if (Object.keys(this.cookieStorage).length) {
                        this.loadFormStorage();
                    }
                    this.addStorageListeners();
                }
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

                this.hideOverlay();
            }).catch((reason) => {
                this.disableFormControls();
            }).finally(() => {
                this.printMessages();
            });
        },

        loadForm : function () {
            const wconf = this.options.widgetConfig;

            let url = wconf.load_form_data_url;
            const params = this.getCurrentUrlParams();
            let json = JSON.stringify(params);

            let formData = new FormData();
            formData.append('form_mode', wconf.form_mode);
            formData.append('form_id', wconf.backend_form_id);
            formData.append('form_key', $.cookie('form_key'));
            formData.append('form_params', json);

            let result = fetch (url, {
                method: 'POST',
                body : formData
            }).then((response) => {
                if (response.ok) {
                    return response.json();
                }
                this.disableFormControls();
                this.addMessage('error', wconf.message_templates['general_error']);

                return Promise.reject();

            }).then ((data) => {
                if (data.debug) {
                    console.log(data);
                }
                if (!data.error) {
                    if ('edit' === wconf.form_mode) {
                        this.fillFromData(data.data);
                    }
                    return data;
                } else {
                    if (data.require_login || data.missing_association || data.already_filled) {
                        if (data.require_login) {
                            this.addMessage('warning', wconf.message_templates['expects_login']);
                        }
                        if (data.missing_association) {
                            this.addMessage('warning', wconf.message_templates['expects_order']);
                        }
                        if (data.already_filled) {
                            this.addMessage('warning', wconf.message_templates['already_filled']);
                        }
                    } else {
                        this.addMessage('error', wconf.message_templates['general_error']);
                    }
                    return Promise.reject();
                }

            });
            return result;
        },

        disableFormControls : function () {
            this.options.form.querySelectorAll('input, textarea, select, button').forEach((elem) => {
                elem.disabled = true;
            });
            return this;
        },

        showOverlay : function () {
            let overlay = this.options.form.parentElement.querySelector('[data-role="form-overlay"]');
            overlay.style.display = 'block';
        },

        hideOverlay : function () {
            let overlay = this.options.form.parentElement.querySelector('[data-role="form-overlay"]');
            overlay.style.display = 'none';
        },

        fillFromData : function (data) {
            let fields = data || {};
            Object.keys(fields).forEach((key) => {
                let name = key;
                if (Array.isArray(fields[key])) {
                    name = name + '[]';
                }
                let field = document.querySelector('[name="' + name + '"]');
                let event = new CustomEvent('change');
                if (field) {
                    let tag = field.tagName.toLowerCase();
                    if ('textarea' === tag) {
                        field.value = fields[key];
                        field.dispatchEvent(event);
                    }
                    if ('input' === tag) {
                        let type = field.getAttribute('type');
                        if ('file' === type) {
                            if (fields[key]) {
                                let w = document.createElement('div');
                                let a = document.createElement('a');
                                a.setAttribute('href', fields[key]);
                                a.innerText = 'link';
                                w.appendChild(a);
                                field.parentElement.appendChild(w);
                            }

                        }
                        if ('text' === type) {
                            field.value = fields[key];
                            field.dispatchEvent(event);
                        }
                        if ('date' === type) {
                            field.value = fields[key].split(' ')[0];
                            field.dispatchEvent(event);
                        }
                        if ('radio' === type) {
                            let radios = document.querySelectorAll('[name="' + key + '"]');
                            Array.from(radios).every((radio) => {
                                if (radio.value === fields[key]) {
                                    radio.checked = true;
                                    radio.dispatchEvent(event);
                                    return false;
                                }
                                return true;
                            });
                        }
                    }
                    if ('select' === tag) {
                        Array.from(field.options).forEach((item) => {
                            if (Array.isArray(fields[key])) {
                                fields[key].forEach((value) => {
                                    if (item.value === value) {
                                        item.selected = true;
                                    }
                                });
                            } else {
                                field.value = fields[key];
                            }
                            field.dispatchEvent(event);
                        });
                    }
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

        getCurrentUrlParams : function (ignore) {
            let o = window.location.origin, h = window.location.href;
            let params = {};

            if (h.length > o.length) {
                let i = 0;
                while (o.charAt(i) === h.charAt(i)) i++;
                let result = h.substring(i);

                if ('/' === result.charAt(0)) {
                    result = result.substring(1);
                }
                let split = result.split('#')[0];
                split = split.split('?');

                if (split[0].length) {
                    if ('/' === split[0].charAt(split[0].length - 1)) {
                        split[0] = split[0].substring(0, split[0].length - 1);
                    }

                    let m2 = split[0].split('/');
                    if (m2.length > 3) {
                        for (let i = 3; i < m2.length; i = i+2) {
                            if (m2[0].length) {
                                if (m2.length >= (i+2)) {
                                    if (m2[1].length) {
                                        params[decodeURIComponent(m2[i])] = decodeURIComponent(m2[i+1]);
                                    }
                                } else {
                                    params[decodeURIComponent(m2[i])] = null;
                                    break;
                                }
                            }
                        }
                    }
                }
                if (split.length > 1) {
                    let q = split[1];
                    let args = q.split('&');
                    for (let i = 0; i < args.length; i++) {
                        if (args[i].length) {
                            let pair = args[i].split('=');
                            if (pair[0].length) {
                                if (2 === pair.length && pair[1].length) {
                                    params[decodeURIComponent(pair[0])] = decodeURIComponent(pair[1]);
                                } else {
                                    params[decodeURIComponent(pair[0])] = null;
                                }
                            }
                        }

                    }
                }
            }
            if (ignore && Array.isArray(ignore)) {
                Object.keys(params).forEach((key) => {
                    if (-1 !== ignore.indexOf(key)) {
                        delete params[key];
                    }
                });
            }
            return params;
        },

        addMessage : function (type, text) {
            this.messages.push({type : type, text : text});
            return this;
        },

        printMessages : function (clear) {
            let m = customerData.get('messages')() || {};
            let a = m.messages || [];

            this.messages.forEach((item) => {
               a.push(item);
            });
            m.messages = a;
            customerData.set('messages', m);

            if (false !== clear) {
                this.messages = [];
            }
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

        closeTabs : function (form) {
            $(this.options.form).find('[data-role="form-tab"]').each((index) => {
                $(this).slideUp();
            });
            $(this.options.form).find('[data-role="form-tab-actions"]').each((index) => {
                $(this).hide();
            });
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
            formData.append('form_mode', this.options.widgetConfig.form_mode);

            let additional = this.getCurrentUrlParams(['referrer']);
            if (Object.keys(additional).length) {
                formData.append('additional_params', JSON.stringify(additional));
            }

            let params = this.getCurrentUrlParams();
            if (params && params.referrer) {
                formData.append('referrer', params.referrer);
            }

            this.showOverlay();

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
            }).always(function() {
                self.onComplete();
            });
        },

        onComplete: function() {
            this.closeTabs(this.options.form);
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

            if (response.redirect_url) {
                window.location.href = response.redirect_url;
            }
        }
    });

    return $.mage.alekseonWidgetForm;
});
