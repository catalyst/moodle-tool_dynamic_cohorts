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
     * A list of supported custom profile fields.
     */
    protected const SUPPORTED_CUSTOM_FIELDS = ['text', 'menu'];

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
     * Returns a list of all fields with extra data (shortname, name, datatype, param1 and type).
     *
     * @return \stdClass[]
     */
    protected function get_fields_info(): array {
        global $CFG;

        require_once($CFG->dirroot.'/user/profile/lib.php');

        $fields = [];

        foreach (profile_get_user_fields_with_data(0) as $customfield) {
            if (!in_array($customfield->field->datatype, self::SUPPORTED_CUSTOM_FIELDS)) {
                continue;
            }

            $field = (object)array_intersect_key((array)$customfield->field,
                ['shortname' => 1, 'name' => 1, 'datatype' => 1, 'param1' => 1]);

            if ($field->datatype == self::FIELD_DATA_TYPE_MENU) {
                $options = explode("\n", $field->param1);
                $field->param1 = array_combine($options, $options);
            } else if ($field->datatype == 'text') {
                $field->paramtype = PARAM_TEXT;
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
        parent::config_form_add($mform);

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
        if (in_array($this->get_operator_value(), $this->missing_data_operators())) {
            return !empty($this->get_config_data()[self::INCLUDE_MISSING_DATA_FIELD_NAME]);
        }

        return false;
    }

    /**
     * A list of operators that getting missing data does make sense for.
     *
     * @return array
     */
    protected function missing_data_operators(): array {
        return [
            self::TEXT_DOES_NOT_CONTAIN,
            self::TEXT_IS_NOT_EQUAL_TO,
            self::TEXT_IS_EMPTY,
        ];
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
                $result = $this->get_text_sql_data($ud, 'data');
                break;
            case self::FIELD_DATA_TYPE_MENU:
                $result = $this->get_menu_sql_data($ud, 'data');
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
