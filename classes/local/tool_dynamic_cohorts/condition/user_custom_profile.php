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

use coding_exception;
use tool_dynamic_cohorts\condition_sql;

/**
 * Condition using user custom profile data.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_custom_profile extends user_profile {

    /**
     * Field name to store include missing data option,
     */
    public const INCLUDE_MISSING_DATA_FIELD_NAME = 'include_missing_data';

    /**
     * Custom field prefix.
     */
    protected const FIELD_PREFIX = 'profile_field_';

    /**
     * Condition name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('condition:user_custom_profile', 'tool_dynamic_cohorts');
    }

    /**
     * Get a list of supported custom fields.
     *
     * @return array
     */
    protected function get_supported_custom_fields(): array {
        return [self::FIELD_DATA_TYPE_TEXT, self::FIELD_DATA_TYPE_MENU, self::FIELD_DATA_TYPE_CHECKBOX];
    }

    /**
     * Returns a list of all fields with extra data (shortname, name, datatype and param1).
     *
     * @return \stdClass[]
     */
    protected function get_fields_info(): array {
        global $CFG;

        require_once($CFG->dirroot.'/user/profile/lib.php');

        $fields = [];

        foreach (profile_get_user_fields_with_data(0) as $customfield) {
            if (!in_array($customfield->field->datatype, $this->get_supported_custom_fields())) {
                continue;
            }

            $field = (object)array_intersect_key((array)$customfield->field,
                ['shortname' => 1, 'name' => 1, 'datatype' => 1, 'param1' => 1]);

            switch ($field->datatype) {
                case self::FIELD_DATA_TYPE_MENU:
                    $options = explode("\n", $field->param1);
                    $field->param1 = array_combine($options, $options);
                    break;
                case self::FIELD_DATA_TYPE_TEXT:
                    $field->paramtype = PARAM_TEXT;
                    break;
                case self::FIELD_DATA_TYPE_CHECKBOX:
                    $field->param1 = array_combine([0, 1], [get_string('no'), get_string('yes')]);
                    break;
                default:
                    throw new coding_exception('Invalid field type ' . $field->datatype);
            }

            $shortname = self::FIELD_PREFIX . $field->shortname;
            $fields[$shortname] = $field;
        }

        return $fields;
    }

    /**
     * Add config form elements.
     *
     * @param \MoodleQuickForm $mform
     */
    public function config_form_add(\MoodleQuickForm $mform): void {
        $options = [0 => get_string('select')];

        $fields = $this->get_fields_info();
        foreach ($fields as $shortname => $field) {
            $options[$shortname] = $field->name;
        }

        $group = [];
        $group[] = $mform->createElement('select', static::get_form_field(), '', $options);

        foreach ($fields as $shortname => $field) {
            switch ($field->datatype) {
                case self::FIELD_DATA_TYPE_TEXT:
                    $this->add_text_field($mform, $group, $field, $shortname);
                    break;
                case  self::FIELD_DATA_TYPE_MENU:
                    $this->add_menu_field($mform, $group, $field, $shortname);
                    break;
                case  self::FIELD_DATA_TYPE_CHECKBOX:
                    $this->add_checkbox_field($mform, $group, $field, $shortname);
                    break;
            }
        }

        $mform->addGroup($group, 'profilefieldgroup', get_string('profilefield', 'tool_dynamic_cohorts'), '', false);

        $mform->addElement(
            'checkbox',
            self::INCLUDE_MISSING_DATA_FIELD_NAME,
            '',
            get_string('includeusersmissingdata', 'tool_dynamic_cohorts')
        );

        $mform->addHelpButton(self::INCLUDE_MISSING_DATA_FIELD_NAME, self::INCLUDE_MISSING_DATA_FIELD_NAME, 'tool_dynamic_cohorts');
    }

    /**
     * Gets required config data from submitted condition form data.
     *
     * @param \stdClass $formdata
     * @return array
     */
    public static function retrieve_config_data(\stdClass $formdata): array {
        $data = parent::retrieve_config_data($formdata);
        $data[self::INCLUDE_MISSING_DATA_FIELD_NAME] = $formdata->{self::INCLUDE_MISSING_DATA_FIELD_NAME} ?? 0;

        return $data;
    }

    /**
     * Check if we should include missing data from user_info_data table.
     *
     * @return bool
     */
    protected function should_include_missing_data(): bool {
        return !empty($this->get_config_data()[self::INCLUDE_MISSING_DATA_FIELD_NAME]);
    }

    /**
     * Gets SQL data for building SQL.
     *
     * @return condition_sql
     */
    public function get_sql(): condition_sql {
        $result = new condition_sql('', '1=0', []);

        $configuredfield = $this->get_field_name();
        $datatype = $this->get_fields_info()[$configuredfield]->datatype;
        $ud = condition_sql::generate_table_alias();

        switch ($datatype) {
            case self::FIELD_DATA_TYPE_TEXT:
                $result = $this->get_text_sql($ud, 'data');
                break;
            case self::FIELD_DATA_TYPE_CHECKBOX:
            case self::FIELD_DATA_TYPE_MENU:
                $result = $this->get_menu_sql($ud, 'data');
                break;
        }

        if (!empty($result->get_params())) {
            $userinfofield = condition_sql::generate_table_alias();
            $userinfodata = condition_sql::generate_table_alias();

            $shortnameparam = condition_sql::generate_param_alias();
            $extrafields = "{$userinfodata}.data, {$userinfodata}.userid";

            $join = "LEFT JOIN (SELECT $extrafields
                                 FROM {user_info_data} $userinfodata
                                 JOIN {user_info_field} $userinfofield
                                   ON ({$userinfofield}.id = {$userinfodata}.fieldid
                                      AND {$userinfofield}.shortname = :{$shortnameparam})) $ud
                           ON ({$ud}.userid = u.id)";

            $params = $result->get_params();
            $params[$shortnameparam] = str_replace(self::FIELD_PREFIX, '', $configuredfield);

            $where = $result->get_where();

            if ($this->should_include_missing_data()) {
                $where .= " OR $ud.data IS NULL";
            }

            $result = new condition_sql($join, $where, $params);
        }

        return $result;
    }

    /**
     * Human-readable description of the configured condition.
     *
     * @return string
     */
    public function get_config_description(): string {
        $description = parent::get_config_description();

        if ($this->should_include_missing_data()) {
            $description .= ' ' . get_string('includingmissingdatadesc', 'tool_dynamic_cohorts');
        }

        return $description;
    }
}
