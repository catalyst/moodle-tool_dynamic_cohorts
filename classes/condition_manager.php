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

use core_component;

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
                } else {
                    $toupdate[$condition->get('id')] = $condition;
                }
            }

            $todelete = array_diff_key($oldconditions, $toupdate);

            foreach ($todelete as $conditiontodelete) {
                $conditiontodelete->delete();
            }

            foreach ($toupdate as $conditiontoupdate) {
                $conditiontoupdate->save();
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
}
