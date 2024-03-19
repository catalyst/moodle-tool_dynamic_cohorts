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

use core_course_category;
use context_system;
use context_coursecat;
use tool_dynamic_cohorts\condition_base;
use tool_dynamic_cohorts\condition_sql;

/**
 * Condition based on cohort membership.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cohort_field extends condition_base {
    use fields_trait;

    /**
     * Supported fields from 'cohort' table.
     */
    public const SUPPORTED_STANDARD_FIELDS = ['contextid', 'name', 'idnumber', 'visible', 'component',  'theme'];

    /**
     * Custom field prefix.
     */
    public const CUSTOM_FIELD_PREFIX = 'custom_field_';

    /**
     * A field name in the form.
     */
    public const FIELD_NAME = 'cohort_field';

    /**
     * Operator value when need members of cohort(s).
     */
    public const OPERATOR_IS_MEMBER_OF = 1;

    /**
     * Operator value when don't need members of cohort(s).
     */
    public const OPERATOR_IS_NOT_MEMBER_OF = 2;

    /**
     * Condition name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('condition:cohort_field', 'tool_dynamic_cohorts');
    }

    /**
     * Gets an list of comparison operators.
     *
     * @return array A list of operators.
     */
    protected function get_cohort_operators(): array {
        return [
            self::OPERATOR_IS_MEMBER_OF => get_string('ismemberof', 'tool_dynamic_cohorts'),
            self::OPERATOR_IS_NOT_MEMBER_OF => get_string('isnotmemberof', 'tool_dynamic_cohorts'),
        ];
    }

    /**
     * Returns a list of all fields with extra data (shortname, name, datatype, param1 and type).
     *
     * @return \stdClass[]
     */
    protected function get_fields_info(): array {
        $fields = [];

        // Standard fields.
        foreach (self::SUPPORTED_STANDARD_FIELDS as $field) {
            $fields[$field] = new \stdClass();
            $fields[$field]->shortname = $field;

            switch ($field) {
                case 'contextid':
                    $options = $this->get_category_options();
                    $fields[$field]->name = get_string('context', 'role');
                    $fields[$field]->datatype = self::FIELD_DATA_TYPE_MENU;
                    $fields[$field]->paramtype = PARAM_INT;
                    $fields[$field]->param1 = $options;
                    break;
                case 'visible':
                    $fields[$field]->name = get_string($field, 'cohort');
                    $fields[$field]->param1 = array_combine([0, 1], [get_string('no'), get_string('yes')]);
                    $fields[$field]->datatype = self::FIELD_DATA_TYPE_CHECKBOX;
                    $fields[$field]->paramtype = PARAM_INT;
                    break;
                case 'theme':
                    $fields[$field]->name = get_string('theme');
                    $fields[$field]->datatype = self::FIELD_DATA_TYPE_TEXT;
                    $fields[$field]->paramtype = PARAM_TEXT;
                    break;
                default:
                    $fields[$field]->name = get_string($field, 'cohort');
                    $fields[$field]->datatype = self::FIELD_DATA_TYPE_TEXT;
                    $fields[$field]->paramtype = PARAM_TEXT;
                    break;
            }
        }

        return $fields;
    }

    /**
     * Get a list of category options for the form.
     *
     * @return array
     */
    protected function get_category_options(): array {
        $options = [];
        $syscontext = context_system::instance();
        $options[$syscontext->id] = $syscontext->get_context_name();

        foreach (core_course_category::make_categories_list() as $cid => $name) {
            $context = context_coursecat::instance($cid);
            $options[$context->id] = $name;
        }

        return $options;
    }

    /**
     * Add config form elements.
     *
     * @param \MoodleQuickForm $mform
     */
    public function config_form_add(\MoodleQuickForm $mform): void {
        $mform->addElement(
            'select',
            $this->get_cohort_operator_field(),
            get_string('operator', 'tool_dynamic_cohorts'),
            $this->get_cohort_operators()
        );

        $options = [0 => get_string('select')];

        $fields = $this->get_fields_info();
        foreach ($fields as $shortname => $field) {
            $options[$shortname] = $field->name;
        }

        $group = [];
        $group[] = $mform->createElement('select', $this->get_form_field(), '', $options);

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
                    throw new \coding_exception('Invalid field type ' . $field->datatype);
            }
        }

        $mform->addGroup($group, 'fieldgroup', get_string('cohortswith', 'tool_dynamic_cohorts'), '', false);
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
     * Gets required config data from submitted condition form data.
     *
     * @param \stdClass $formdata
     * @return array
     */
    public static function retrieve_config_data(\stdClass $formdata): array {
        $configdata = parent::retrieve_config_data($formdata);
        $fieldname = $configdata[self::get_form_field()];

        $data = [];

        // Get field name itself.
        $data[self::get_form_field()] = $fieldname;

        // Only get required values related to the selected configuration.
        foreach ($configdata as $key => $value) {
            if ( $key == self::get_cohort_operator_field() || strpos($key, $fieldname . '_') === 0 ) {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Field related field.
     *
     * @return string
     */
    protected static function get_form_field(): string {
        return self::FIELD_NAME . '_field';
    }

    /**
     * Operator field.
     *
     * @return string
     */
    protected static function get_cohort_operator_field(): string {
        return self::FIELD_NAME . '_operator';
    }

    /**
     * Gets operator value.
     *
     * @return array|mixed
     */
    protected function get_cohort_operator_value() {
        return $this->get_config_data()[self::get_cohort_operator_field()] ?? self::OPERATOR_IS_MEMBER_OF;
    }

    /**
     * Human-readable description of the configured condition.
     *
     * @return string
     */
    public function get_config_description(): string {
        $cohortoperator = $this->get_cohort_operators()[$this->get_cohort_operator_value()];
        $fieldname = $this->get_field_name();

        if (empty($fieldname)) {
            return '';
        }

        $datatype = $this->get_fields_info()[$fieldname]->datatype;
        $fieldoperator = $this->get_operator_text($datatype);
        $fieldvalue = $this->get_field_value();

        if ($fieldname == 'contextid') {
            $fieldvalue = $this->get_category_options()[$fieldvalue];
        }

        if (in_array($this->get_operator_value(), [self::TEXT_IS_EMPTY, self::TEXT_IS_NOT_EMPTY])) {
            $fieldvalue = null;
        }

        return get_string('condition:cohort_field_description', 'tool_dynamic_cohorts', (object)[
            'operator' => $cohortoperator,
            'field' => $fieldname,
            'fieldoperator' => $fieldoperator,
            'fieldvalue' => $fieldvalue,
        ]);
    }

    /**
     * Gets SQL data for building SQL.
     *
     * @return condition_sql
     */
    public function get_sql(): condition_sql {
        $result = new condition_sql('', '1=0', []);

        if (!$this->is_broken()) {
            $innertable = condition_sql::generate_table_alias();
            $outertable = condition_sql::generate_table_alias();

            $configuredfield = $this->get_field_name();
            $datatype = $this->get_fields_info()[$configuredfield]->datatype;
            $ud = condition_sql::generate_table_alias();

            $cohortsqldata = new condition_sql('', '1=0', []);

            switch ($datatype) {
                case self::FIELD_DATA_TYPE_TEXT:
                    $cohortsqldata = $this->get_text_sql_data($ud, $configuredfield);
                    break;
                case self::FIELD_DATA_TYPE_CHECKBOX:
                case self::FIELD_DATA_TYPE_MENU:
                    $cohortsqldata = $this->get_menu_sql_data($ud, $configuredfield);
                    break;
            }

            // Including only cohorts with configured fields.
            $cohortwhere = $cohortsqldata->get_where();
            $cohortjoin = $cohortsqldata->get_join();
            $cohortsql = "SELECT $ud.id FROM {cohort} $ud $cohortjoin WHERE $cohortwhere";
            $params = $cohortsqldata->get_params();

            // Exclude cohort that managed by a parent rule.
            $rule = $this->get_rule();
            if ($rule) {
                $selectedcohortparam = condition_sql::generate_param_alias();
                $cohortsql .= "AND $ud.id <> :$selectedcohortparam";
                $params[$selectedcohortparam] = $rule->get('cohortid');
            }

            // Are we getting members?
            $needmembers = $this->get_cohort_operator_value() == self::OPERATOR_IS_MEMBER_OF;
            // Select all users that are members or not members of selected cohorts depending on selected operator.
            $join = "LEFT JOIN (SELECT {$innertable}.userid
                          FROM {cohort_members} $innertable
                         WHERE {$innertable}.cohortid IN ({$cohortsql})) {$outertable}
                      ON u.id = {$outertable}.userid";

            $where = $needmembers ? "$outertable.userid is NOT NULL" : "$outertable.userid is NULL";
            $result = new condition_sql($join, $where, $params);
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
            '\core\event\cohort_updated',
            '\core\event\cohort_member_added',
            '\core\event\cohort_member_removed',
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

            // Misconfigured.
            if ($fieldvalue === '' && $operatorvalue != self::TEXT_IS_EMPTY && $operatorvalue != self::TEXT_IS_NOT_EMPTY) {
                return true;
            }

            // Configured field is gone.
            if (!array_key_exists($configuredfield, $this->get_fields_info())) {
                return true;
            }

            // Configured category is gone.
            if ($configuredfield == 'contextid' && !array_key_exists($fieldvalue, $this->get_category_options())) {
                return true;
            }
        }

        return false;
    }
}
