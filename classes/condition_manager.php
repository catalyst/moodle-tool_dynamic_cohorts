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

use core\event\base;
use core_component;
use tool_dynamic_cohorts\event\condition_created;
use tool_dynamic_cohorts\event\condition_deleted;
use tool_dynamic_cohorts\event\condition_updated;

/**
 * Condition manager class.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition_manager {

    /**
     * Get a list of all exising conditions.
     *
     * @param bool $excludebroken Do we need to exclude broken condition?
     * @return condition_base[]
     */
    public static function get_all_conditions(bool $excludebroken = true): array {
        $instances = [];
        $classes = core_component::get_component_classes_in_namespace(null, '\\local\\tool_dynamic_cohorts\\condition');

        foreach (array_keys($classes) as $class) {
            $reflectionclass = new \ReflectionClass($class);
            if (!$reflectionclass->isAbstract()) {
                $instance = $class::get_instance();

                if ($excludebroken && $instance->is_broken()) {
                    continue;
                }

                $instances[$class] = $class::get_instance();
            }
        }

        // Sort conditions by name.
        uasort($instances, function(condition_base $a, condition_base $b) {
            return ($a->get_name() <=> $b->get_name());
        });

        return $instances;
    }

    /**
     * Process conditions for submitted rule.
     *
     * @param rule $rule Rule instance/
     * @param \stdClass $formdata Data received from rule_form.
     */
    public static function process_form(rule $rule, \stdClass $formdata): void {
        if (!empty($formdata->isconditionschanged)) {
            $submittedconditions = self::process_condition_json($formdata->conditionjson);
            $oldconditions = $rule->get_condition_records();

            $toupdate = [];
            foreach ($submittedconditions as $condition) {
                if (empty($condition->get('id'))) {
                    $condition->set('ruleid', $rule->get('id'));
                    $condition->create();
                    self::trigger_condition_event(condition_created::class, $condition, []);
                } else {
                    $toupdate[$condition->get('id')] = $condition;
                }
            }

            $todelete = array_diff_key($oldconditions, $toupdate);
            self::delete_conditions($todelete);

            foreach ($toupdate as $conditiontoupdate) {
                $olddescription = $conditiontoupdate->get('configdata');
                $instance = condition_base::get_instance(0, $conditiontoupdate->to_record());
                if ($instance && !$instance->is_broken()) {
                    $olddescription = $instance->get_config_description();
                }

                $conditiontoupdate->save();

                self::trigger_condition_event(condition_updated::class, $conditiontoupdate, [
                    'other' => [
                        'olddescription' => $olddescription,
                    ],
                ]);
            }
        }
    }

    /**
     * Take JSON from the form and return a list of condition persistents.
     *
     * @param string $formjson Conditions JSON string from the rule form.
     *
     * @return condition[]
     */
    private static function process_condition_json(string $formjson): array {
        // Get only required fields for condition persistent.
        $requiredconditionfield = array_diff(
            array_keys(condition::properties_definition()),
            ['ruleid', 'usermodified', 'timecreated', 'timemodified']
        );

        $formjson = json_decode($formjson, true);
        $submittedrecords = [];

        if (is_array($formjson)) {
            // Filter out submitted conditions data to only fields required for condition persistent.
            $submittedrecords = array_map(function (array $record) use ($requiredconditionfield): array {
                return array_intersect_key($record, array_flip($requiredconditionfield));
            }, $formjson);
        }

        $conditions = [];
        foreach ($submittedrecords as $submittedrecord) {
            $conditions[] = new condition($submittedrecord['id'], (object)$submittedrecord);
        }

        return $conditions;
    }

    /**
     * Delete conditions.
     *
     * @param \tool_dynamic_cohorts\condition[] $conditions A list of conditions to be deleted.
     */
    public static function delete_conditions(array $conditions): void {
        foreach ($conditions as $condition) {
            if ($condition instanceof condition) {
                $condition->delete();
                self::trigger_condition_event(condition_deleted::class, $condition, [
                    'other' => [
                        'ruleid' => $condition->get('ruleid'),
                    ],
                ]);
            }
        }
    }

    /**
     * Trigger condition related event.
     *
     * @param string $eventclass Full event class name, e.g. \tool_dynamic_cohorts\event\condition_updated.
     * @param condition $condition Related condition object.
     * @param array $data Event related data.
     */
    private static function trigger_condition_event(string $eventclass, condition $condition, array $data): void {
        $instance = condition_base::get_instance(0, $condition->to_record());

        if (!isset($data['other']['ruleid'])) {
            $data['other']['ruleid'] = $condition->get('ruleid');
        }

        // In case that the class related to that condition is not found,
        // we use data that we know about that condition such as class name and raw config.
        if (!$instance) {
            $name = $condition->get('classname');
            $description = $condition->get('configdata');
        } else {
            $name = $instance->get_name();
            $description = $instance->is_broken() ? $instance->get_broken_description() : $instance->get_config_description();
        }

        $data['other']['name'] = $name;
        $data['other']['description'] = $description;

        $eventclass::create($data)->trigger();
    }

    /**
     * Gets a list of conditions subscribed for the given event.
     *
     * @param \core\event\base $event Event.
     * @return condition_base[]
     */
    public static function get_conditions_with_event(base $event): array {
        $conditionswithevent = [];

        foreach (self::get_all_conditions(false) as $condition) {
            $class = get_class($event);
            foreach ($condition->get_events() as $eventclass) {
                if (ltrim($class, '\\') === ltrim($eventclass, '\\')) {
                    $conditionswithevent[$condition->get_record()->get('classname')] = $condition;
                    break;
                }
            }
        }

        return $conditionswithevent;
    }
}
