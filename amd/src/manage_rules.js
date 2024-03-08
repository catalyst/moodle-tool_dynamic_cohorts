// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Manage rules JS module.
 *
 * @module     tool_dynamic_cohorts/manage_rules
 * @copyright  2024 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import Templates from 'core/templates';
import ModalEvents from 'core/modal_events';
import ModalFactory from 'core/modal_factory';
import {get_string as getString} from 'core/str';
import * as DynamicTable from 'core_table/dynamic';

/**
 * A list of used selectors.
 */
const SELECTORS = {
    RULE_MATCHING_USERS: 'tool-dynamic-cohorts-matching-users',
    RULE_CONDITIONS: '.tool-dynamic-cohorts-condition-view',
};

/**
 * Init of the module.
 */
export const init = () => {
    loadMatchingUsers();
    initRuleConditionsModals();

    document.addEventListener(DynamicTable.Events.tableContentRefreshed, () => loadMatchingUsers());
    document.addEventListener(DynamicTable.Events.tableContentRefreshed, () => initRuleConditionsModals());
};

/**
 * Load matching users for each rule.
 */
const loadMatchingUsers = () => {
    Array.from(document.getElementsByClassName(SELECTORS.RULE_MATCHING_USERS)).forEach((collection) => {
        const ruleid = collection.dataset.ruleid;
        const loader = collection.children[0];
        const link = collection.children[1];

        Ajax.call([{
            methodname: 'tool_dynamic_cohorts_get_total_matching_users_for_rule',
            args: {ruleid: ruleid},
            done: function (number) {
                link.children[0].append(number.toLocaleString().replace(/,/g, " "));
                loader.classList.add('hidden');
                link.classList.remove('hidden');
            },
            fail: function (response) {
                Notification.exception(response);
            }
        }]);
    });
};

/**
 * Initialise displaying each rule conditions in a modal.
 */
const initRuleConditionsModals = () => {
    document.querySelectorAll(SELECTORS.RULE_CONDITIONS).forEach(link => {
        let ruleid = link.dataset.ruleid;
        link.addEventListener('click', function() {
            Ajax.call([{
                methodname: 'tool_dynamic_cohorts_get_conditions',
                args: {ruleid: ruleid},
                done: function (conditions) {
                    Templates.render(
                        'tool_dynamic_cohorts/conditions',
                        {'conditions' : conditions, 'hidecontrols': true}
                    ).then(function(html) {
                        ModalFactory.create({
                            type: ModalFactory.types.ALERT,
                            title: getString('conditionsformtitle', 'tool_dynamic_cohorts'),
                            body: html,
                            large: true,
                        }).then(function (modal) {
                            modal.getRoot().on(ModalEvents.hidden, function() {
                                modal.destroy();
                            });
                            modal.show();
                        });
                    }).fail(function(response) {
                        Notification.exception(response);
                    });
                },
                fail: function (response) {
                    Notification.exception(response);
                }
            }]);
        });
    });
};
