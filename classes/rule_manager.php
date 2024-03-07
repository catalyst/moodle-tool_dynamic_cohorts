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

namespace tool_dynamic_cohorts;

use moodle_url;
use moodle_exception;
use tool_dynamic_cohorts\event\rule_created;
use tool_dynamic_cohorts\event\rule_deleted;
use tool_dynamic_cohorts\event\rule_updated;

/**
 * Rule manager class.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule_manager {

    /**
     * Builds rule edit URL.
     *
     * @param rule $rule Rule instance.
     * @return moodle_url
     */
    public static function build_edit_url(rule $rule): moodle_url {
        return new moodle_url('/admin/tool/dynamic_cohorts/edit.php', ['ruleid' => $rule->get('id')]);
    }

    /**
     * Builds rule delete URL.
     *
     * @param rule $rule Rule instance.
     * @return moodle_url
     */
    public static function build_delete_url(rule $rule): moodle_url {
        return new \moodle_url('/admin/tool/dynamic_cohorts/delete.php', [
            'ruleid' => $rule->get('id'),
            'sesskey' => sesskey(),
        ]);
    }

    /**
     * Build data for setting into a rule form as default values.
     *
     * @param rule $rule Rule to build a data for.
     * @return array
     */
    public static function build_data_for_form(rule $rule): array {
        $data = (array) $rule->to_record();
        $data['conditionjson'] = '';
        $conditions = [];

        foreach ($rule->get_condition_records() as $condition) {
            $instance = condition_base::get_instance(0, $condition->to_record());

            if (!$instance) {
                $name = $condition->get('classname');
                $description = $condition->get('configdata');
            } else {
                $name = $instance->get_name();
                $description = $instance->is_broken() ? $instance->get_broken_description() : $instance->get_config_description();
            }

            $conditions[] = (array)$condition->to_record() +
                ['description' => $description] +
                ['name' => $name];
        }

        if (!empty($conditions)) {
            $data['conditionjson'] = json_encode($conditions);
        }

        return $data;
    }

    /**
     * A helper method for processing rule form data.
     *
     * @param \stdClass $formdata Data received from rule_form.
     * @return rule Rule instance.
     */
    public static function process_form(\stdClass $formdata): rule {
        global $DB;

        $formdata->enabled = 0;
        self::validate_submitted_data($formdata);

        $ruledata = (object) [
            'name' => $formdata->name,
            'enabled' => $formdata->enabled,
            'cohortid' => $formdata->cohortid,
            'description' => $formdata->description,
            'bulkprocessing' => $formdata->bulkprocessing,
        ];

        $oldcohortid = 0;

        $transaction = $DB->start_delegated_transaction();

        try {
            if (empty($formdata->id)) {
                $rule = new rule(0, $ruledata);
                $rule->create();
                rule_created::create(['other' => ['ruleid' => $rule->get('id')]])->trigger();
            } else {
                $rule = new rule($formdata->id);
                $oldcohortid = $rule->get('cohortid');
                $rule->from_record($ruledata);
                $rule->update();
                rule_updated::create(['other' => ['ruleid' => $rule->get('id')]])->trigger();
            }

            if ($oldcohortid != $formdata->cohortid) {
                cohort_manager::unmanage_cohort($oldcohortid);
                cohort_manager::manage_cohort($formdata->cohortid);
            }

            condition_manager::process_form($rule, $formdata);

            if ($rule->is_broken(true)) {
                $rule->mark_broken();
            } else {
                $rule->mark_unbroken();
            }

            $transaction->allow_commit();
            return $rule;
        } catch (\Exception $exception) {
            $transaction->rollback($exception);
            throw new $exception;
        }
    }

    /**
     * Validate rule data.
     *
     * @param \stdClass $formdata Data received from rule_form.
     */
    private static function validate_submitted_data(\stdClass $formdata): void {
        $requiredfields = array_diff(
            array_keys(rule::properties_definition()),
            ['id', 'broken', 'usermodified', 'timecreated', 'timemodified']
        );

        foreach ($requiredfields as $field) {
            if (!isset($formdata->{$field})) {
                throw new moodle_exception('Invalid rule data. Missing field: ' . $field);
            }
        }

        if (!array_key_exists($formdata->cohortid, cohort_manager::get_cohorts())) {
            throw new moodle_exception('Invalid rule data. Cohort is invalid: ' . $formdata->cohortid);
        }

        if (!isset($formdata->conditionjson)) {
            throw new moodle_exception('Invalid rule data. Missing condition data.');
        }
    }

    /**
     * Delete rule.
     *
     * @param rule $rule
     */
    public static function delete_rule(rule $rule): void {
        $oldruleid = $rule->get('id');
        $conditions = $rule->get_condition_records();

        if ($rule->delete()) {
            rule_deleted::create(['other' => ['ruleid' => $oldruleid]])->trigger();
            condition_manager::delete_conditions($conditions);
            cohort_manager::unmanage_cohort($rule->get('cohortid'));
        }
    }
}
