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

    use fields_trait;

    /**
     * A list of supported default fields.
     */
    protected const SUPPORTED_STANDARD_FIELDS = ['auth', 'firstname', 'lastname', 'username', 'email',  'idnumber',
        'city', 'country', 'institution', 'department', 'suspended'];

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
                case self::FIELD_DATA_TYPE_CHECKBOX:
                    $this->add_checkbox_field($mform, $group, $field, $shortname);
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
                case 'suspended':
                    $fields[$field]->name = get_string($field);
                    $fields[$field]->datatype = self::FIELD_DATA_TYPE_CHECKBOX;
                    $fields[$field]->param1 = array_combine([0, 1], [get_string('no'), get_string('yes')]);;
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
     * Human-readable description of the configured condition.
     *
     * @return string
     */
    public function get_config_description(): string {

        $configuredfieldname = $this->get_field_name();

        if (empty($configuredfieldname)) {
            return '';
        }

        $fieldinfo = $this->get_fields_info()[$configuredfieldname];
        $displayedfieldname = $this->get_field_name_text();
        $fieldoperator = $this->get_operator_text($fieldinfo->datatype);

        $fieldvalue = $this->get_field_value_text();

        return get_string('condition:profile_field_description', 'tool_dynamic_cohorts', (object)[
            'field' => $displayedfieldname,
            'fieldoperator' => $fieldoperator,
            'fieldvalue' => $fieldvalue,
        ]);
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
                $result = $this->get_text_sql('u', $this->get_field_name());
                break;
            case self::FIELD_DATA_TYPE_MENU:
            case self::FIELD_DATA_TYPE_CHECKBOX:
                $result = $this->get_menu_sql('u', $this->get_field_name());
                break;
        }

        return $result;
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
