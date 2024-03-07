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

use tool_dynamic_cohorts\cohort_manager;
use tool_dynamic_cohorts\condition_base;
use tool_dynamic_cohorts\condition_sql;
use html_writer;

/**
 * Condition based on cohort membership.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cohort_membership extends condition_base {

    /**
     * A field name in the form.
     */
    public const FIELD_NAME = 'cohort_membership';

    /**
     * Operator value when need members of cohort(s).
     */
    public const OPERATOR_IS_MEMBER_OF = 1;

    /**
     * Operator value when don't need members of cohort(s).
     */
    public const OPERATOR_IS_NOT_MEMBER_OF = 2;

    /**
     * Cached locally list of all cohorts.
     * @var null|array
     */
    protected $allcohorts = null;

    /**
     * Condition name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('condition:cohort_membership', 'tool_dynamic_cohorts');
    }

    /**
     * Gets an list of comparison operators.
     *
     * @return array A list of operators.
     */
    protected function get_operators(): array {
        return [
            self::OPERATOR_IS_MEMBER_OF => get_string('ismemberof', 'tool_dynamic_cohorts'),
            self::OPERATOR_IS_NOT_MEMBER_OF => get_string('isnotmemberof', 'tool_dynamic_cohorts'),
        ];
    }

    /**
     * Returns a list of all cohorts.
     *
     * @return array
     */
    protected function get_all_cohorts(): array {
        if (is_null($this->allcohorts)) {
            $this->allcohorts = [];
            foreach (cohort_manager::get_cohorts() as $cohort) {
                $this->allcohorts[$cohort->id] = $cohort->name;
            }
        }

        return $this->allcohorts;
    }

    /**
     * Add config form elements.
     *
     * @param \MoodleQuickForm $mform
     */
    public function config_form_add(\MoodleQuickForm $mform): void {
        $mform->addElement(
            'select',
            $this->get_operator_field(),
            get_string('operator', 'tool_dynamic_cohorts'),
            $this->get_operators()
        );

        $mform->addElement(
            'autocomplete',
            $this->get_cohort_field(),
            get_string('cohort', 'cohort'),
            $this->get_all_cohorts(),
            ['noselectionstring' => get_string('choosedots'), 'multiple' => true]
        );

        $mform->addRule($this->get_cohort_field(), get_string('required'), 'required');
    }

    /**
     * Validate config form elements.
     *
     * @param array $data Data to validate.
     * @return array
     */
    public function config_form_validate(array $data): array {
        $errors = [];

        if (empty($data[$this->get_cohort_field()])) {
            $errors[$this->get_cohort_field()] = get_string('pleaseselectcohort', 'tool_dynamic_cohorts');
        }

        return $errors;
    }

    /**
     * Operator field.
     *
     * @return string
     */
    protected function get_operator_field(): string {
        return self::FIELD_NAME . '_operator';
    }

    /**
     * Get cohort field.
     *
     * @return string
     */
    protected function get_cohort_field(): string {
        return self::FIELD_NAME . '_value';
    }

    /**
     * Gets a list of configured cohort IDs.
     *
     * @return array
     */
    protected function get_configured_cohorts(): array {
        return $this->get_config_data()[$this->get_cohort_field()] ?? [];
    }

    /**
     * Gets operator value.
     *
     * @return array|mixed
     */
    protected function get_operator_value() {
        return $this->get_config_data()[$this->get_operator_field()] ?? self::OPERATOR_IS_MEMBER_OF;
    }

    /**
     * Human-readable description of the configured condition.
     *
     * @return string
     */
    public function get_config_description(): string {
        $operator = $this->get_operators()[$this->get_operator_value()];

        $cohorts = array_map(function ($cohortid) {
            return $this->get_all_cohorts()[$cohortid] ?? $cohortid;
        }, $this->get_configured_cohorts());

        $cohorts = implode(' ' . get_string('or', 'tool_dynamic_cohorts') . ' ', $cohorts);

        return get_string('condition:cohort_membership_description', 'tool_dynamic_cohorts', (object)[
            'operator' => $operator,
            'cohorts' => $cohorts,
        ]);
    }

    /**
     * Human readable description of the broken condition.
     *
     * @return string
     */
    public function get_broken_description(): string {
        if ($this->is_using_rule_cohort()) {
            $description = get_string('condition:cohort_membership_broken_description', 'tool_dynamic_cohorts');
            $description .= html_writer::empty_tag('br');
            $description .= $this->get_config_description();
        } else {
            $description = parent::get_broken_description();
        }

        return $description;
    }

    /**
     * Gets SQL data for building SQL.
     *
     * @return condition_sql
     */
    public function get_sql(): condition_sql {
        global $DB;

        $result = new condition_sql('', '1=0', []);

        if (!$this->is_broken() && !empty($this->get_configured_cohorts())) {
            $innertable = condition_sql::generate_table_alias();
            $outertable = condition_sql::generate_table_alias();

            list($sql, $params) = $DB->get_in_or_equal(
                $this->get_configured_cohorts(),
                SQL_PARAMS_NAMED,
                condition_sql::generate_param_alias()
            );

            // Are we getting members?
            $needmembers = $this->get_operator_value() == self::OPERATOR_IS_MEMBER_OF;
            // Select all users that are members or not members of given cohorts depending on selected operator.
            $join = "LEFT JOIN (SELECT {$innertable}.userid
                          FROM {cohort_members} $innertable
                         WHERE {$innertable}.cohortid {$sql}) {$outertable}
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
            '\core\event\cohort_member_added',
            '\core\event\cohort_member_removed',
        ];
    }

    /**
     * Check if condition is configured to check the same cohort that set for the related rule.
     *
     * @return bool
     */
    protected function is_using_rule_cohort(): bool {
        $rule = $this->get_rule();
        if ($rule && in_array($rule->get('cohortid'), $this->get_configured_cohorts())) {
            return true;
        }

        return false;
    }

    /**
     * Is condition broken.
     *
     * @return bool
     */
    public function is_broken(): bool {
        // Check if configured cohort is still exist.
        foreach ($this->get_configured_cohorts() as $cohortid) {
            if (!array_key_exists($cohortid, $this->get_all_cohorts())) {
                return true;
            }
        }
        // Check if rule manages one of the configured cohorts.
        if ($this->is_using_rule_cohort()) {
            return true;
        }

        return false;
    }
}
