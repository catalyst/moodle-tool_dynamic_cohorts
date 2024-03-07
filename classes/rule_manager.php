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

use cache;
use moodle_url;
use moodle_exception;
use tool_dynamic_cohorts\event\matching_failed;
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
     * A number of users for a bulk processing.
     */
    const BULK_PROCESSING_SIZE = 10000;

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

    /**
     * Returns a list of all matching users for provided rule.
     *
     * @param rule $rule A rule to get a list of users.
     * @param int|null $userid Optional user ID if we need to check just one user.
     *
     * @return array
     */
    public static function get_matching_users(rule $rule, ?int $userid = null): array {
        global $DB;

        $conditions = $rule->get_condition_records();

        if (empty($conditions)) {
            return [];
        }

        $where = ' u.deleted = 0 ';
        $join = '';
        $params = [];

        $sql = "SELECT DISTINCT u.id FROM {user} u";

        foreach ($conditions as $condition) {
            try {
                $instance = condition_base::get_instance(0, $condition->to_record());

                if (!$instance || $instance->is_broken()) {
                    return [];
                }

                $sqldata = $instance->get_sql();

                if (!empty($sqldata->get_join())) {
                    $join .= ' ' . $sqldata->get_join();
                }

                if (!empty($sqldata->get_where())) {
                    $where .= ' AND (' . $sqldata->get_where() . ')';
                }

                if (!empty($sqldata->get_params())) {
                    $params += $sqldata->get_params();
                }
            } catch (\Exception $exception ) {
                matching_failed::create([
                    'other' => [
                        'ruleid' => $rule->get('id'),
                        'error' => $exception->getMessage(),
                    ],
                ])->trigger();

                $rule->mark_broken();
                return [];
            }
        }

        if ($userid) {
            $userparam = condition_sql::generate_param_alias();
            $where .= " AND u.id = :{$userparam} ";
            $params += [$userparam => $userid];
        }

        try {
            return $DB->get_records_sql($sql . $join . ' WHERE ' . $where, $params);
        } catch (\Exception $exception) {
            matching_failed::create([
                'other' => [
                    'ruleid' => $rule->get('id'),
                    'error' => $exception->getMessage(),
                ],
            ])->trigger();

            $rule->mark_broken();

            return  [];
        }
    }

    /**
     * Process a given rule.
     *
     * @param rule $rule A rule to process.
     * @param int|null $userid Optional user ID for processing a rule just for a single user.
     */
    public static function process_rule(rule $rule, ?int $userid = null): void {
        global $DB;

        if (!$rule->is_enabled() || $rule->is_broken()) {
            return;
        }

        if ($rule->is_broken(true)) {
            $rule->mark_broken();
            return;
        }

        $cohortid = $rule->get('cohortid');

        if (!$DB->record_exists('cohort', ['id' => $cohortid])) {
            $rule->mark_broken();
            return;
        }

        $users = self::get_matching_users($rule, $userid);

        $cohortmembersparams = ['cohortid' => $cohortid];

        if (!empty($userid)) {
            $cohortmembersparams['userid'] = $userid;
        }

        $cohortmembers = $DB->get_records('cohort_members', $cohortmembersparams, '', 'userid');

        $userstoadd = array_diff_key($users, $cohortmembers);
        $userstodelete = array_diff_key($cohortmembers, $users);

        if ($rule->is_bulk_processing()) {
            $timeadded = time();
            foreach (array_chunk($userstoadd, self::BULK_PROCESSING_SIZE) as $users) {
                $records = [];
                foreach ($users as $user) {
                    $record = new \stdClass();
                    $record->userid = $user->id;
                    $record->cohortid = $cohortid;
                    $record->timeadded = $timeadded;
                    $records[] = $record;
                }
                $DB->insert_records('cohort_members', $records);
            }

            foreach (array_chunk($userstodelete, self::BULK_PROCESSING_SIZE) as $users) {
                $userids = array_column($users, 'userid');
                list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
                $sql = "userid $insql AND cohortid = :cohort";
                $inparams['cohort'] = $cohortid;
                $DB->delete_records_select('cohort_members', $sql, $inparams);
            }
        } else {
            foreach ($userstoadd as $user) {
                cohort_add_member($cohortid, $user->id);
            }

            foreach ($userstodelete as $user) {
                cohort_remove_member($cohortid, $user->userid);
            }
        }
    }

    /**
     * Returns a list of rules with provided condition.
     *
     * @param \tool_dynamic_cohorts\condition_base $condition Condition to check.
     * @return rule[]
     */
    public static function get_rules_with_condition(condition_base $condition): array {
        global $DB;

        $classname = get_class($condition);

        $cache = cache::make('tool_dynamic_cohorts', 'rulesconditions');
        $key = $classname;

        $rules = $cache->get($key);

        if ($rules === false) {
            $rules = [];
            $sql = 'SELECT DISTINCT r.id
                      FROM {tool_dynamic_cohorts} r
                      JOIN {tool_dynamic_cohorts_c} c ON c.ruleid = r.id
                     WHERE c.classname = ?
                       AND r.enabled = 1 ORDER BY r.id';

            $records = $DB->get_records_sql($sql, [$classname]);

            foreach ($records as $record) {
                $rules[$record->id] = new rule($record->id);
            }

            $cache->set($key, $rules);
        }

        return $rules;
    }
}
