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
    protected function get_text_operators(): array {
        return [
            self::TEXT_CONTAINS => get_string('contains', 'filters'),
            self::TEXT_DOES_NOT_CONTAIN => get_string('doesnotcontain', 'filters'),
            self::TEXT_IS_EQUAL_TO => get_string('isequalto', 'filters'),
            self::TEXT_IS_NOT_EQUAL_TO => get_string('isnotequalto', 'filters'),
            self::TEXT_STARTS_WITH => get_string('startswith', 'filters'),
            self::TEXT_ENDS_WITH => get_string('endswith', 'filters'),
            self::TEXT_IS_EMPTY => get_string('isempty', 'filters'),
            self::TEXT_IS_NOT_EMPTY => get_string('isnotempty', 'tool_dynamic_cohorts'),
            self::TEXT_IN => get_string('textin', 'tool_dynamic_cohorts'),
            self::TEXT_NOT_IN => get_string('textnotin', 'tool_dynamic_cohorts'),
        ];
    }

    /**
     * Gets a list of comparison operators for menu fields.
     *
     * @return array A list of operators.
     */
    protected function get_menu_operators(): array {
        return [
            self::TEXT_IS_EQUAL_TO => get_string('isequalto', 'filters'),
            self::TEXT_IS_NOT_EQUAL_TO => get_string('isnotequalto', 'filters'),
        ];
    }

    /**
     * Gets a list of comparison operators for date fields.
     *
     * @return array A list of operators.
     */
    protected function get_date_operators(): array {
        return [
            self::DATE_IS_AFTER => get_string('isafter', 'tool_dynamic_cohorts'),
            self::DATE_IS_BEFORE => get_string('isbefore', 'tool_dynamic_cohorts'),
            self::TEXT_IS_EMPTY => get_string('isempty', 'filters'),
            self::TEXT_IS_NOT_EMPTY => get_string('isnotempty', 'tool_dynamic_cohorts'),
            self::DATE_IN_THE_PAST => get_string('inthepast', 'tool_dynamic_cohorts'),
            self::DATE_IN_THE_FUTURE => get_string('inthefuture', 'tool_dynamic_cohorts'),
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

        return $fieldvalue;
    }

    /**
     * Return the field name as a text.
     *
     * @return string
     */
    protected function get_field_name_text(): string {
        return $this->get_fields_info()[$this->get_field_name()]->name ?? '-';
    }

    /**
     * Return a field value as a human-readable text.
     *
     * @return string|null
     */
    protected function get_field_value_text(): ?string {
        $fieldname = $this->get_field_name();
        $fieldvalue = $this->get_field_value();
        $fieldinfo = $this->get_fields_info();

        if (!empty($fieldname) && !empty($fieldinfo[$fieldname])) {
            switch ($fieldinfo[$fieldname]->datatype) {
                case self::FIELD_DATA_TYPE_SELECT:
                case self::FIELD_DATA_TYPE_MENU:
                case self::FIELD_DATA_TYPE_CHECKBOX:
                    $fieldvalue = $fieldinfo[$fieldname]->param1[$fieldvalue];
                    break;
                case self::FIELD_DATA_TYPE_DATE:
                case self::FIELD_DATA_TYPE_DATETIME:
                    $fieldvalue = userdate($fieldvalue);
            }
        }

        $nullvalue = [self::TEXT_IS_EMPTY, self::TEXT_IS_NOT_EMPTY, self::DATE_IN_THE_PAST, self::DATE_IN_THE_FUTURE];
        if (in_array($this->get_operator_value(), $nullvalue)) {
            $fieldvalue = null;
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
     *  Returns a human-readable text for the configured operator based on a field data type.
     *
     * @param string $fielddatatype Field data type.
     * @return string
     */
    protected function get_operator_text(string $fielddatatype): string {
        switch ($fielddatatype) {
            case self::FIELD_DATA_TYPE_MENU:
            case self::FIELD_DATA_TYPE_SELECT:
                return $this->get_menu_operators()[$this->get_operator_value()];
            case self::FIELD_DATA_TYPE_DATETIME:
            case self::FIELD_DATA_TYPE_DATE:
                return $this->get_date_operators()[$this->get_operator_value()];
            default:
                return $this->get_text_operators()[$this->get_operator_value()];
        }
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
     * Adds a date field to the form.
     *
     * @param \MoodleQuickForm $mform Form to add the field to.
     * @param array $group A group to add the field to.
     * @param \stdClass $field Field info.
     * @param string $shortname A field shortname.
     */
    protected function add_date_field(\MoodleQuickForm $mform, array &$group, \stdClass $field, string $shortname): void {
        $elements = [];
        $elements[] = $mform->createElement('select', $shortname . '_operator', null, $this->get_date_operators());

        $elements[] = $mform->createElement('date_time_selector', $shortname . '_value');
        $mform->setDefault($shortname . '_value', usergetmidnight(time()));
        $mform->hideIf(
            $shortname . '_value',
            $shortname . '_operator',
            'in',
            self::TEXT_IS_EMPTY . '|' .
            self::TEXT_IS_NOT_EMPTY . '|' .
            self::DATE_IN_THE_PAST . '|' .
            self::DATE_IN_THE_FUTURE
        );

        $group[] = $mform->createElement('group', $shortname, '', $elements, '', false);

        $mform->hideIf($shortname . '_operator', static::get_form_field(), 'neq', $shortname);
        $mform->hideIf($shortname . '_value', static::get_form_field(), 'neq', $shortname);
        $mform->hideIf($shortname . '_value1', static::get_form_field(), 'neq', $shortname);
        $mform->hideIf($shortname, static::get_form_field(), 'neq', $shortname);
    }

    /**
     * Validate config form elements.
     *
     * @param array $data Data to validate.
     * @return array
     */
    public function config_form_validate(array $data): array {
        $errors = [];

        $fields = $this->get_fields_info();
        if (empty($data[static::get_form_field()]) || !isset($fields[$data[static::get_form_field()]])) {
            $errors['fieldgroup'] = get_string('pleaseselectfield', 'tool_dynamic_cohorts');
        }

        $fieldvalue = $data[static::get_form_field()] . '_value';
        $operator = $data[static::get_form_field()] . '_operator';
        $datatype = $fields[$data[static::get_form_field()]]->datatype ?? '';

        if (empty($data[$fieldvalue])) {
            if ($datatype == 'text' && !in_array($data[$operator], [self::TEXT_IS_EMPTY, self::TEXT_IS_NOT_EMPTY])) {
                $errors['fieldgroup'] = get_string('invalidfieldvalue', 'tool_dynamic_cohorts');
            }
        }

        return $errors;
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
            case self::TEXT_IN:
                [$insql, $inparams] = $DB->get_in_or_equal(explode(', ', $fieldvalue), SQL_PARAMS_QM);
                
                foreach($inparams as $key => $val) {    
                    $insql = $this->str_replace_first('?', ':'.$param, $insql);
                    $params[$param] = $val;
                    $param = condition_sql::generate_param_alias();
                }
                $where = $DB->sql_compare_text("$tablealias.$fieldname") . " " . $insql;
                break;
            case self::TEXT_NOT_IN:
                [$insql, $inparams] = $DB->get_in_or_equal(explode(', ', $fieldvalue), SQL_PARAMS_QM, 'param', false);

                foreach ($inparams as $key => $val) {
                    $insql = $this->str_replace_first('?', ':' . $param, $insql);
                    $params[$param] = $val;
                    $param = condition_sql::generate_param_alias();
                }
                $where = $DB->sql_compare_text("$tablealias.$fieldname") . " " . $insql;
                break;
            default:
                return new condition_sql('', '', []);
        }

        
        return new condition_sql('', $where, $params);
    }

    protected function str_replace_first($search, $replace, $subject)
    {
        $search = '/' . preg_quote($search, '/') . '/';
        return preg_replace($search, $replace, $subject, 1);
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

    /**
     * Get SQL data for date type fields.
     *
     * @param string $tablealias Alias for a table.
     * @param string $fieldname Field name.
     * @return condition_sql
     */
    protected function get_date_sql(string $tablealias, string $fieldname): condition_sql {
        $fieldvalue = $this->get_field_value();
        $operatorvalue = $this->get_operator_value();

        if ($this->is_broken()) {
            return new condition_sql('', '', []);
        }

        $param = condition_sql::generate_param_alias();
        switch ($operatorvalue) {
            case self::TEXT_IS_EMPTY:
                $where = "$tablealias.$fieldname = :$param OR $tablealias.$fieldname IS NULL";
                $params[$param] = 0;
                break;
            case self::TEXT_IS_NOT_EMPTY:
                $where = "$tablealias.$fieldname <> :$param";
                $params[$param] = (int) $fieldvalue;
                break;
            case self::DATE_IS_BEFORE:
                $where = "$tablealias.$fieldname <= :$param";
                $params[$param] = (int) $fieldvalue;
                break;
            case self::DATE_IS_AFTER:
                $where = "$tablealias.$fieldname >= :$param";
                $params[$param] = (int) $fieldvalue;
                break;
            case self::DATE_IN_THE_FUTURE:
                $where = "$tablealias.$fieldname >= :$param";
                $params[$param] = time();
                break;
            case self::DATE_IN_THE_PAST:
                $where = "$tablealias.$fieldname <= :$param";
                $params[$param] = time();
                break;
            default:
                return new condition_sql('', '', []);
        }

        return new condition_sql('', $where, $params);
    }

    /**
     * Get SQL data for multi select type fields.
     *
     * @param string $tablealias Alias for a table.
     * @param string $fieldname Field name.
     * @param bool $addspace Does a field type add space?
     * @return condition_sql
     */
    protected function get_multiselect_sql(string $tablealias, string $fieldname, bool $addspace = true): condition_sql {
        global $DB;

        $fieldvalue = $this->get_field_value();
        $operatorvalue = $this->get_operator_value();

        if ($this->is_broken()) {
            return new condition_sql('', '', []);
        }
        // For some reason user profile field autocomplete adds space when saving field values
        // like implode(', ', $data). However multiselect custom field doesn't do it, instead it does
        // like implode(',', $data). To make it flexible we add space when we consider (or not) an extra
        // space when we build following SQL.
        $space = $addspace ? ' ' : '';

        // User data for multiselect fields is stored like  Option 1, Option 2, Option 3.
        // So to be accurate in our SQL we have to cover three scenarios:
        // 1. Value is in the beginning of the string.
        // 2. Value is somewhere in the middle.
        // 3. Value is at the end of the string.
        // So our SQL should like:
        // WHERE data like 'value%' OR data like '% value, %' OR data like '%, value'
        // This is a bit hacky, but should give us accurate results.
        $startparam = condition_sql::generate_param_alias();
        $middleparam = condition_sql::generate_param_alias();
        $endparam = condition_sql::generate_param_alias();

        switch ($operatorvalue) {
            case self::TEXT_IS_EQUAL_TO:
                $value = $DB->sql_like_escape($fieldvalue);

                $where = $DB->sql_like("$tablealias.$fieldname", ":$startparam", false, false);
                $params[$startparam] = "$value%";

                $where .= ' OR ' . $DB->sql_like("$tablealias.$fieldname", ":$middleparam", false, false);
                $params[$middleparam] = "%,$space$value,$space%";

                $where .= ' OR ' . $DB->sql_like("$tablealias.$fieldname", ":$endparam", false, false);
                $params[$endparam] = "%,$space$value";

                break;
            case self::TEXT_IS_NOT_EQUAL_TO:
                $value = $DB->sql_like_escape($fieldvalue);

                $where = $DB->sql_like("$tablealias.$fieldname", ":$startparam", false, false, true);
                $params[$startparam] = "$value%";

                $where .= ' AND ' . $DB->sql_like("$tablealias.$fieldname", ":$middleparam", false, false, true);
                $params[$middleparam] = "%,$space$value,$space%";

                $where .= ' AND ' . $DB->sql_like("$tablealias.$fieldname", ":$endparam", false, false, true);
                $params[$endparam] = "%,$space$value";

                break;
            default:
                return new condition_sql('', '', []);
        }

        return new condition_sql('', $where, $params);
    }
}
