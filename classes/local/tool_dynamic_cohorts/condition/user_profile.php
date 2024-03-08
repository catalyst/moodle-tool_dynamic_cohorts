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

use tool_dynamic_cohorts\condition_base;
use tool_dynamic_cohorts\condition_sql;
use core_user;
use core_plugin_manager;
use coding_exception;

/**
 * Condition using standard user profile.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_profile extends condition_base {

    /**
     * Value for text field types.
     */
    public const FIELD_DATA_TYPE_TEXT = 'text';

    /**
     * Value for menu field types.
     */
    public const FIELD_DATA_TYPE_MENU = 'menu';

    /**
     * Value for operator text contains.
     */
    public const TEXT_CONTAINS = 1;

    /**
     * Value for operator text doesn't contain.
     */
    public const TEXT_DOES_NOT_CONTAIN = 2;

    /**
     * Value for operator text is equal to.
     */
    public const TEXT_IS_EQUAL_TO = 3;

    /**
     * Value for operator text starts with.
     */
    public const TEXT_STARTS_WITH = 4;

    /**
     * Value for operator text ends with.
     */
    public const TEXT_ENDS_WITH = 5;

    /**
     * Value for operator text is empty.
     */
    public const TEXT_IS_EMPTY = 6;

    /**
     * Value for operator text is not empty.
     */
    public const TEXT_IS_NOT_EMPTY = 7;

    /**
     * Value for operator text is not equal to.
     */
    public const TEXT_IS_NOT_EQUAL_TO = 8;

    /**
     * A list of supported default fields.
     */
    protected const SUPPORTED_STANDARD_FIELDS = ['auth', 'firstname', 'lastname', 'username', 'email',  'idnumber',
        'city', 'country', 'institution', 'department'];

    /**
     * Return field name in the condition config form.
     *
     * @return string
     */
    protected static function get_form_field(): string {
        return 'profilefield';
    }

    /**
     * Condition name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('condition:user_profile', 'tool_dynamic_cohorts');
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
                case self::FIELD_DATA_TYPE_MENU:
                    $this->add_menu_field($mform, $group, $field, $shortname);
                    break;
                default:
                    throw new coding_exception('Invalid field type ' . $field->datatype);
            }
        }

        $mform->addGroup($group, 'profilefieldgroup', get_string('profilefield', 'tool_dynamic_cohorts'), '', false);
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
            $errors['profilefieldgroup'] = get_string('pleaseselectfield', 'tool_dynamic_cohorts');
        }

        $fieldvalue = $data[static::get_form_field()] . '_value';
        $operator = $data[static::get_form_field()] . '_operator';
        $datatype = $fields[$data[static::get_form_field()]]->datatype ?? '';

        if (empty($data[$fieldvalue])) {
            if ($datatype == 'text' && !in_array($data[$operator], [self::TEXT_IS_EMPTY, self::TEXT_IS_NOT_EMPTY])) {
                $errors['profilefieldgroup'] = get_string('invalidfieldvalue', 'tool_dynamic_cohorts');
            }
        }

        return $errors;
    }

    /**
     * Gets required config data from submitted condition form data.
     *
     * @param \stdClass $formdata
     * @return array
     */
    public static function retrieve_config_data(\stdClass $formdata): array {
        $configdata = parent::retrieve_config_data($formdata);
        $fieldname = $configdata[static::get_form_field()];

        $data = [];

        // Get field name itself.
        $data[static::get_form_field()] = $fieldname;

        // Only get values related to the selected field name, e.g firstname_operator, firstname_value.
        foreach ($configdata as $key => $value) {
            if (strpos($key, $fieldname . '_') === 0) {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Gets an list of comparison operators for text fields.
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
     * Gets an list of comparison operators for menu fields.
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
     * Returns a list of all fields with extra data (shortname, name, datatype, param1 and type).
     *
     * @return \stdClass[]
     */
    protected function get_fields_info(): array {
        $fields = [];

        foreach (self::SUPPORTED_STANDARD_FIELDS as $field) {
            $fields[$field] = new \stdClass();
            $fields[$field]->shortname = $field;

            switch ($field) {
                case 'auth':
                    $options = [];
                    foreach (core_plugin_manager::instance()->get_plugins_of_type('auth') as $plugin) {
                        $options[$plugin->name] = $plugin->displayname;
                    }
                    $fields[$field]->name = get_string('type_auth', 'plugin');
                    $fields[$field]->datatype = self::FIELD_DATA_TYPE_MENU;
                    $fields[$field]->param1 = $options;
                    break;
                default:
                    $fields[$field]->name = get_string($field);
                    $fields[$field]->datatype = self::FIELD_DATA_TYPE_TEXT;
                    $fields[$field]->paramtype = core_user::get_property_type($field);
                    break;
            }
        }

        return $fields;
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
    protected function get_field_text(): string {
        return $this->get_fields_info()[$this->get_field_name()]->name ?? '-';
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
     * Human-readable description of the configured condition.
     *
     * @return string
     */
    public function get_config_description(): string {
        $fieldname = $this->get_field_name();

        if (empty($fieldname)) {
            return '';
        }

        $datatype = $this->get_fields_info()[$fieldname]->datatype;

        if (in_array($this->get_operator_value(), [self::TEXT_IS_EMPTY, self::TEXT_IS_NOT_EMPTY])) {
            return $this->get_field_text() . ' ' . $this->get_operator_text($datatype);
        } else {
            $fieldvalue = $this->get_field_value();
            if ($fieldname == 'auth') {
                $authplugins = core_plugin_manager::instance()->get_plugins_of_type('auth');
                $fieldvalue = $authplugins[$fieldvalue]->displayname;
            }

            return $this->get_field_text() . ' '. $this->get_operator_text($datatype) . ' ' . $fieldvalue;
        }
    }

    /**
     * Gets SQL for a given condition.
     *
     * @return condition_sql
     */
    public function get_sql(): condition_sql {
        $result = new condition_sql('', '1=0', []);

        $datatype = $this->get_fields_info()[$this->get_field_name()]->datatype;

        switch ($datatype) {
            case self::FIELD_DATA_TYPE_TEXT:
                $result = $this->get_text_sql_data('u', $this->get_field_name());
                break;
            case self::FIELD_DATA_TYPE_MENU:
                $result = $this->get_menu_sql_data('u', $this->get_field_name());
                break;
        }

        return $result;
    }

    /**
     * Get SQl data for text type fields.
     *
     * @param string $tablealias Alias for a table.
     * @param string $fieldname Field name.
     * @return condition_sql
     */
    protected function get_text_sql_data(string $tablealias, string $fieldname): condition_sql {
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
    protected function get_menu_sql_data(string $tablealias, string $fieldname): condition_sql {
        global $DB;

        $fieldvalue = $this->get_field_value();
        $operatorvalue = $this->get_operator_value();

        if ($this->is_broken()) {
            return new condition_sql('', '', []);
        }

        $param = condition_sql::generate_param_alias();

        switch ($operatorvalue) {
            case self::TEXT_IS_EQUAL_TO:
                $where = $DB->sql_equal($DB->sql_compare_text("{$tablealias}.{$fieldname}"), ":$param", false, false);
                $params[$param] = $fieldvalue;
                break;
            case self::TEXT_IS_NOT_EQUAL_TO:
                $where = $DB->sql_equal($DB->sql_compare_text("{$tablealias}.{$fieldname}"), ":$param", false, false, true);
                $params[$param] = $fieldvalue;
                break;
            default:
                return new condition_sql('', '', []);
        }

        return new condition_sql('', $where, $params);
    }

    /**
     * A list of events the condition is listening to.
     *
     * @return string[]
     */
    public function get_events(): array {
        return [
            '\core\event\user_created',
            '\core\event\user_updated',
        ];
    }

    /**
     * Is condition broken.
     *
     * @return bool
     */
    public function is_broken(): bool {
        if ($this->get_config_data()) {
            $configuredfield = $this->get_field_name();
            $fieldvalue = $this->get_field_value();
            $operatorvalue = $this->get_operator_value();

            if ($fieldvalue === '' && $operatorvalue != self::TEXT_IS_EMPTY && $operatorvalue != self::TEXT_IS_NOT_EMPTY) {
                return true;
            }

            return !array_key_exists($configuredfield, $this->get_fields_info());
        }

        return false;
    }
}
