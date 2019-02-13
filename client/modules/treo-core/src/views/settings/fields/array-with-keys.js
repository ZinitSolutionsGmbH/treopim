/*
 * This file is part of EspoCRM and/or TreoPIM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * TreoPIM is EspoCRM-based Open Source Product Information Management application.
 * Copyright (C) 2017-2019 TreoLabs GmbH
 * Website: http://www.treopim.com
 *
 * TreoPIM as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * TreoPIM as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "TreoPIM" word.
 */

Espo.define('treo-core:views/settings/fields/array-with-keys', 'views/fields/array',
    Dep => Dep.extend({

        _timeouts: {},

        editTemplate: 'treo-core:settings/fields/array-with-keys/edit',

        maxKeyLength: 6,

        unitSymbols: {},

        events: _.extend({
            'change .unit-symbol': function (e) {
                let currentTarget = $(e.currentTarget);
                let value = currentTarget.parents('.list-group-item').data('value');
                let currentUnitSymbol = currentTarget.val();
                this.changeUnitSymbol(value, currentUnitSymbol);
            }
        }, Dep.prototype.events),

        setup() {
            Dep.prototype.setup.call(this);

            if (this.options.editableKey) {
                if (!this.validations.includes('key')) {
                    this.validations.push('key');
                }
                this.setupUnitSymbols();
                this.on('customInvalid', value => {
                    let listItem = this.$el.find(`.list-group-item[data-value="${value}"]`);
                    listItem.addClass('has-error');
                    this.$el.one('click', () => {
                        listItem.removeClass('has-error');
                    });
                    this.once('render', () => {
                        listItem.removeClass('has-error');
                    });
                });
            }
        },

        validate() {
            for (let i in this.validations) {
                let method = 'validate' + Espo.Utils.upperCaseFirst(this.validations[i]);
                if (this[method].call(this)) {
                    if (!this.options.editableKey) {
                        this.trigger('invalid');
                    }
                    return true;
                }
            }
            return false;
        },

        setupUnitSymbols() {
            let unitsOfMeasure = this.getConfig().get('unitsOfMeasure') || {};
            let measure = this.options.parentModel.get('measure');
            let measureConfig = unitsOfMeasure[measure] || {};
            this.unitSymbols = measureConfig.unitList || {};
        },

        changeUnitSymbol(value, currentUnitSymbol) {
            this.unitSymbols[value] = currentUnitSymbol;
        },

        addValue(value) {
            let clearedValue = this.clearValue(value);
            this.translatedOptions[clearedValue] = value;
            if (this.options.editableKey) {
                this.unitSymbols[clearedValue] = clearedValue.slice(0, 6);
            }

            Dep.prototype.addValue.call(this, clearedValue);
        },

        removeValue(value) {
            this.$list.children(`[data-value="${value}"]`).remove();
            let index = this.selected.indexOf(value);
            this.selected.splice(index, 1);
            delete this.translatedOptions[value];
            if (this.options.editableKey) {
                delete this.unitSymbols[value];
            }
            this.trigger('change');
        },

        clearValue(value) {
            let cleared = value;
            cleared = cleared.toLowerCase().replace(/-/g, '').replace(/_/g, '').replace(/[^\w\s]/gi, '').replace(/ (.)/g, (match, g) => g.toUpperCase()).trim();
            return cleared;
        },

        getItemHtml(value) {
            let label = value;
            if (this.translatedOptions) {
                label = ((value in this.translatedOptions) ? this.translatedOptions[value] : label);
                label = this.getHelper().stripTags(label).replace(/"/g, '&quot;');
            }

            let html = `
                    <div class="list-group-item" data-value="${value}" style="cursor: default;">
                        ${label}&nbsp;
                        <a href="javascript:" class="pull-right" data-value="${value}" data-action="removeValue"><span class="fas fa-times"></a>
                    </div>`;

            if (this.options.editableKey) {
                html = `
                    <div class="list-group-item" data-value="${value}" style="cursor: default;">
                        <a href="javascript:" class="pull-right" data-value="${value}" data-action="removeValue"><span class="fas fa-times"></span></a>
                        <span>${label}&nbsp;</span>
                        <div class="key-array-unit-symbol">
                            <label class="control-label">${this.translate('UnitSymbol', 'labels', 'Global')}:</label><input class="form-control unit-symbol" value="${this.unitSymbols[value]}" maxlength="${this.maxKeyLength}" type="text" autocomplete="off">
                        </div>
                    </div>`;
            }

            return html;
        },

        fetchFromDom() {
            let selected = [];
            this.$el.find('.list-group .list-group-item').each((i, el) => {
                let value = $(el).data('value').toString();
                selected.push(value);
            });
            this.selected = selected;
        },

        validateKey: function () {
            let validate = false;
            let counts = {};
            Object.keys(this.unitSymbols).forEach(value => {
                let unitSymbol = this.unitSymbols[value].trim();
                counts[value] = (counts[value] || 0) + 1;
                if (!unitSymbol.length || counts[value] > 2) {
                    let msg = this.translate('isRequiredAndUnique', 'messages').replace('{field}', this.translate('UnitSymbol', 'labels', 'Global'));
                    this.showValidationMessage(msg, `.list-group-item[data-value="${value}"] .unit-symbol`);
                    this.trigger('customInvalid', value);
                    validate = true;
                }
            });
            return validate;
        },

        showValidationMessage: function (message, target) {
            let $el;

            target = target || '.main-element';

            if (typeof target === 'string' || target instanceof String) {
                $el = this.$el.find(target);
            } else {
                $el = $(target);
            }

            if (!$el.size() && this.$element) {
                $el = this.$element;
            }
            $el.popover({
                placement: 'bottom',
                container: 'body',
                content: message,
                trigger: 'manual'
            }).popover('show');

            let isDestroyed = false;

            $el.closest('.field').one('mousedown click', function () {
                if (isDestroyed) return;
                $el.popover('destroy');
                isDestroyed = true;
            });

            this.once('render remove', function () {
                if (isDestroyed) return;
                if ($el) {
                    $el.popover('destroy');
                    isDestroyed = true;
                }
            });

            if (this._timeouts[target]) {
                clearTimeout(this._timeouts[target]);
            }

            this._timeouts[target] = setTimeout(function () {
                if (isDestroyed) return;
                $el.popover('destroy');
                isDestroyed = true;
            }, 3000);
        }

    })
);
