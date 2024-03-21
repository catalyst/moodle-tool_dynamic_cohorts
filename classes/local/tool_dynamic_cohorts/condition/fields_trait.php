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
 * Common functions for conditions filtering by DB fields.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait fields_trait {

    /**
     * Gets a list of comparison operators for text fields.
     *
     * @return array A list of operators.
     */
    protected function get_text_operators() : array {
        return [
            self::TEXT_CONTAINS => get_string('contains', 'filters'),
            self::TEXT_DOES_NOT_CONTAIN => get_string('doesnotcontain', 'filters'),
            self::TEXT_IS_EQUAL_TO => get_string('isequalto', 'filters'),
            self::TEXT_IS_NOT_EQUAL_TO => get_string('isnotequalto', 'filters'),
            self::TEXT_STARTS_WITH => get_string('startswith', 'filters'),
            self::TEXT_ENDS_WITH => get_string('endswith', 'filters'),
            self::TEXT_IS_EMPTY => get_string('isempty', 'filters'),
            self::TEXT_IS_NOT_EMPTY => get_string('isnotempty', 'tool_dynamic_cohorts'),
        ];
    }

    /**
     * Gets a list of comparison operators for menu fields.
     *
     * @return array A list of operators.
     */
    protected function get_menu_operators() : array {
        return [
            self::TEXT_IS_EQUAL_TO => get_string('isequalto', 'filters'),
            self::TEXT_IS_NOT_EQUAL_TO => get_string('isnotequalto', 'filters'),
        ];
    }

    /**
     * Returns a field name for the configured field.
     *
     * @return string
     */
    protected function get_field_name(): string {
        return $this->get_config_data()[static::get_form_field()];
    }

    /**
     * Returns a value of the configured field.
     *
     * @return string|null
     */
    protected function get_field_value(): ?string {
        $fieldvalue = null;
        $field = $this->get_field_name();
        $configdata = $this->get_config_data();

        if (!empty($field) && isset($configdata[$field . '_value'])) {
            $fieldvalue = $configdata[$field . '_value'];
        }

        // A special case for checkbox field.
        $fieldname = $this->get_field_name();
        if (!empty($fieldname) && !empty($this->get_fields_info()[$fieldname])) {
            $datatype = $this->get_fields_info()[$fieldname]->datatype;
            if ($datatype == self::FIELD_DATA_TYPE_CHECKBOX) {
                $fieldvalue = empty($fieldvalue) ? get_string('no') : get_string('yes');
            }
        }

        return $fieldvalue;
    }

    /**
     * Returns a value for the configured operator.
     *
     * @return int
     */
    protected function get_operator_value(): int {
        return $this->get_config_data()[$this->get_field_name() . '_operator'] ?? self::TEXT_IS_EQUAL_TO;
    }

    /**
     *  Returns a text for the configured operator based on a field data type.
     *
     * @param string $fielddatatype Field data type.
     * @return string
     */
    protected function get_operator_text(string $fielddatatype): string {
        if ($fielddatatype == self::FIELD_DATA_TYPE_TEXT) {
            return $this->get_text_operators()[$this->get_operator_value()];
        }

        if ($fielddatatype == self::FIELD_DATA_TYPE_MENU) {
            return $this->get_menu_operators()[$this->get_operator_value()];
        }

        return $this->get_text_operators()[$this->get_operator_value()];
    }

    /**
     * Adds a text field to the form.
     *
     * @param \MoodleQuickForm $mform Form to add the field to.
     * @param array $group A group to add the field to.
     * @param \stdClass $field Field info.
     * @param string $shortname A field shortname.
     */
    protected function add_text_field(\MoodleQuickForm $mform, array &$group, \stdClass $field, string $shortname): void {
        $elements = [];
        $elements[] = $mform->createElement('select', $shortname . '_operator', null, $this->get_text_operators());
        $elements[] = $mform->createElement('text', $shortname . '_value', null);

        $mform->setType($shortname . '_value', $field->paramtype ?? PARAM_TEXT);
        $mform->hideIf($shortname . '_value', $shortname . '_operator', 'in', self::TEXT_IS_EMPTY . '|' . self::TEXT_IS_NOT_EMPTY);

        $group[] = $mform->createElement('group', $shortname, '', $elements, '', false);
        $mform->hideIf($shortname, static::get_form_field(), 'neq', $shortname);
    }

    /**
     * Adds a menu field to the form.
     *
     * @param \MoodleQuickForm $mform Form to add the field to.
     * @param array $group A group to add the field to.
     * @param \stdClass $field Field info.
     * @param string $shortname A field shortname.
     */
    protected function add_menu_field(\MoodleQuickForm $mform, array &$group, \stdClass $field, string $shortname): void {
        $options = (array) $field->param1;
        $elements = [];
        $elements[] = $mform->createElement('select', $shortname . '_operator', null, $this->get_menu_operators());

        $elements[] = $mform->createElement('select', $shortname . '_value', $field->name, $options);
        $mform->hideIf($shortname . '_value', $shortname . '_operator', 'in', self::TEXT_IS_EMPTY . '|' . self::TEXT_IS_NOT_EMPTY);

        $group[] = $mform->createElement('group', $shortname, '', $elements, '', false);
        $mform->hideIf($shortname, static::get_form_field(), 'neq', $shortname);
    }

    /**
     * Adds a check box field to the form.
     *
     * @param \MoodleQuickForm $mform Form to add the field to.
     * @param array $group A group to add the field to.
     * @param \stdClass $field Field info.
     * @param string $shortname A field shortname.
     */
    protected function add_checkbox_field(\MoodleQuickForm $mform, array &$group, \stdClass $field, string $shortname): void {
        $options = (array) $field->param1;

        $elements = [];
        $elements[] = $mform->createElement('hidden', $shortname . '_operator', self::TEXT_IS_EQUAL_TO);
        $elements[] = $mform->createElement('select', $shortname . '_value', $field->name, $options);
        $group[] = $mform->createElement('group', $shortname, '', $elements, '', false);
        $mform->hideIf($shortname, self::get_form_field(), 'neq', $shortname);
    }

    /**
     * Get SQl data for text type fields.
     *
     * @param string $tablealias Alias for a table.
     * @param string $fieldname Field name.
     * @return condition_sql
     */
    protected function get_text_sql(string $tablealias, string $fieldname): condition_sql {
        global $DB;

        $fieldvalue = $this->get_field_value();
        $operatorvalue = $this->get_operator_value();

        if ($this->is_broken()) {
            return new condition_sql('', '', []);
        }

        $param = condition_sql::generate_param_alias();

        switch ($operatorvalue) {
            case self::TEXT_CONTAINS:
                $where = $DB->sql_like("$tablealias.$fieldname", ":$param", false, false);
                $value = $DB->sql_like_escape($fieldvalue);
                $params[$param] = "%$value%";
                break;
            case self::TEXT_DOES_NOT_CONTAIN:
                $where = $DB->sql_like("$tablealias.$fieldname", ":$param", false, false, true);
                $fieldvalue = $DB->sql_like_escape($fieldvalue);
                $params[$param] = "%$fieldvalue%";
                break;
            case self::TEXT_IS_EQUAL_TO:
                $where = $DB->sql_equal($DB->sql_compare_text("{$tablealias}.{$fieldname}"), ":$param", false, false);
                $params[$param] = $fieldvalue;
                break;
            case self::TEXT_IS_NOT_EQUAL_TO:
                $where = $DB->sql_equal($DB->sql_compare_text("{$tablealias}.{$fieldname}"), ":$param", false, false, true);
                $params[$param] = $fieldvalue;
                break;
            case self::TEXT_STARTS_WITH:
                $where = $DB->sql_like("$tablealias.$fieldname", ":$param", false, false);
                $fieldvalue = $DB->sql_like_escape($fieldvalue);
                $params[$param] = "$fieldvalue%";
                break;
            case self::TEXT_ENDS_WITH:
                $where = $DB->sql_like("$tablealias.$fieldname", ":$param", false, false);
                $fieldvalue = $DB->sql_like_escape($fieldvalue);
                $params[$param] = "%$fieldvalue";
                break;
            case self::TEXT_IS_EMPTY:
                $where = $DB->sql_compare_text("$tablealias.$fieldname") . " = " . $DB->sql_compare_text(":$param");
                $params[$param] = '';
                break;
            case self::TEXT_IS_NOT_EMPTY:
                $where = $DB->sql_compare_text("$tablealias.$fieldname") . " != " . $DB->sql_compare_text(":$param");
                $params[$param] = '';
                break;
            default:
                return new condition_sql('', '', []);
        }

        return new condition_sql('', $where, $params);
    }

    /**
     * Get SQL data for menu type fields.
     *
     * @param string $tablealias Alias for a table.
     * @param string $fieldname Field name.
     * @return condition_sql
     */
    protected function get_menu_sql(string $tablealias, string $fieldname): condition_sql {
        global $DB;

        $fieldvalue = $this->get_field_value();
        $operatorvalue = $this->get_operator_value();

        if ($this->is_broken()) {
            return new condition_sql('', '', []);
        }

        $param = condition_sql::generate_param_alias();
        $field = $DB->sql_cast_to_char($tablealias . '.' .$fieldname);

        switch ($operatorvalue) {
            case self::TEXT_IS_EQUAL_TO:
                $where = $DB->sql_equal($DB->sql_compare_text("$field"), ":$param", false, false);
                $params[$param] = $fieldvalue;
                break;
            case self::TEXT_IS_NOT_EQUAL_TO:
                $where = $DB->sql_equal($DB->sql_compare_text("$field"), ":$param", false, false, true);
                $params[$param] = $fieldvalue;
                break;
            default:
                return new condition_sql('', '', []);
        }

        return new condition_sql('', $where, $params);
    }
}
