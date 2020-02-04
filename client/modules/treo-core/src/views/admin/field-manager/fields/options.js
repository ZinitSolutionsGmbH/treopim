/*
 * This file is part of EspoCRM and/or TreoCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * TreoCore is EspoCRM-based Open Source application.
 * Copyright (C) 2017-2019 TreoLabs GmbH
 * Website: https://treolabs.com
 *
 * TreoCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * TreoCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
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
 * and "TreoCore" word.
 */

Espo.define('treo-core:views/admin/field-manager/fields/options', ['class-replace!treo-core:views/admin/field-manager/fields/options', 'views/fields/array'],
    (Dep, Arr) => Dep.extend({

        getItemHtml(value) {
            let valueSanitized = this.getHelper().stripTags(value);
            let translatedValue = this.translatedOptions[value] || valueSanitized;

            translatedValue = translatedValue.replace(/"/g, '&quot;').replace(/\\/g, '&bsol;');

            let valueInternal = valueSanitized.replace(/"/g, '-quote-').replace(/\\/g, '-backslash-');

            return `
                <div class="list-group-item link-with-role form-inline" data-value="${valueInternal}">
                    ${this.getTranslationContainer(value, valueInternal, translatedValue, valueSanitized)}
                    <div style="width: 8%; display: inline-block;">
                        <a href="javascript:" class="pull-right" data-value="${valueInternal}" data-action="removeValue"><span class="fas fa-times"></a>
                    </div>
                    <br style="clear: both;" />
                </div>`;
        },

        getTranslationContainer(value, valueInternal, translatedValue, valueSanitized) {
            return `
                <div class="pull-left" style="width: 92%; display: inline-block;" data-name="${this.name}">
                    <input name="translatedValue" data-value="${valueInternal}" class="role form-control input-sm pull-right" value="${translatedValue}">
                    <div class="main-option">${valueSanitized}</div>
                </div>`;
        },

        fetch() {
            let data = Arr.prototype.fetch.call(this);

            data.translatedOptions = {};

            if (!data[this.name].length) {
                data[this.name] = false;
                return data;
            }

            (data[this.name] || []).forEach(value => {
                data.translatedOptions[value] = this.getTranslatedOption(value);
            });

            return data;
        },

        getTranslatedOption(value, pathName) {
            pathName = pathName && typeof pathName === 'string' ? pathName : this.name;

            let valueSanitized = this.getHelper().stripTags(value);
            let valueInternal = valueSanitized.replace(/"/g, '-quote-').replace(/\\/g, '-backslash-');
            let translatedValue = this.$el.find(`[data-name="${pathName}"] input[name="translatedValue"][data-value="${valueInternal}"]`).val() || value;

            return translatedValue.toString();
        },

        fetchFromDom() {
            var selected = [];
            this.$el.find('.list-group .list-group-item').each((i, el) => {
                var value = $(el).data('value').toString();
                value = value.replace(/-quote-/g, '"').replace(/-backslash-/g, '\\');
                selected.push(value);
            });
            this.selected = selected;
        },

    })
);
