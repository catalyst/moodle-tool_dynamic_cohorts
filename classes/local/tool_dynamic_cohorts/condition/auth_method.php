<?php
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

namespace tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition;

use core_plugin_manager;

/**
 * Condition using user auth method.
 *
 * This is a simplified version of user_profile that allows matching only by an auth method.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auth_method extends user_profile {

    /**
     * Condition name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('condition:auth_method', 'tool_dynamic_cohorts');
    }

    /**
     * Add config form elements.
     *
     * @param \MoodleQuickForm $mform
     */
    public function config_form_add(\MoodleQuickForm $mform): void {
        $fields = $this->get_fields_info();

        $mform->addElement('hidden', self::FIELD_NAME, 'auth');
        $mform->setType(self::FIELD_NAME, PARAM_ALPHA);

        $group = [];
        $this->add_menu_field($mform, $group, $fields['auth'], 'auth');

        $mform->addGroup($group, 'profilefieldgroup', get_string('condition:auth_method', 'tool_dynamic_cohorts'), '', false);
    }

    /**
     * Returns a list of all fields with extra data (shortname, name, datatype, param1 and type).
     *
     * @return \stdClass[]
     */
    protected function get_fields_info(): array {

        $field = 'auth';
        $options = [];
        foreach (core_plugin_manager::instance()->get_plugins_of_type('auth') as $plugin) {
            $options[$plugin->name] = $plugin->displayname;
        }
        $fields = [];
        $fields[$field] = new \stdClass();
        $fields[$field]->name = get_string('type_auth', 'plugin');
        $fields[$field]->datatype = self::FIELD_DATA_TYPE_MENU;
        $fields[$field]->param1 = $options;

        return $fields;
    }
}
