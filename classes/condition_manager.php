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
}
