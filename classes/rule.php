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
use cache_helper;
use core\persistent;

/**
 * Rules persistent
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule extends persistent {

    /**
     * Table.
     */
    const TABLE = 'tool_dynamic_cohorts';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'name' => [
                'type' => PARAM_TEXT,
            ],
            'description' => [
                'type' => PARAM_TEXT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'cohortid' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'enabled' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'bulkprocessing' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'broken' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
            'operator' => [
                'type' => PARAM_INT,
                'default' => 0,
            ],
        ];
    }

    /**
     * Get a list of condition records for that rule.
     *
     * @return condition[]
     */
    public function get_condition_records(): array {
        $cache = cache::make('tool_dynamic_cohorts', 'conditionrecords');
        $key = $this->get('id');
        $conditions = $cache->get($key);

        if ($conditions === false) {
            $conditions = [];
            foreach (condition::get_records(['ruleid' => $this->get('id')], 'sortorder') as $condition) {
                $conditions[$condition->get('id')] = $condition;
            }
            $cache->set($key, $conditions);
        }

        return $conditions;
    }

    /**
     * Return if the rule is enabled.
     *
     * @return bool
     */
    public function is_enabled(): bool {
        return (bool) $this->get('enabled');
    }

    /**
     * Check if this rule should process in bulk.
     * @return bool
     */
    public function is_bulk_processing(): bool {
        return (bool) $this->get('bulkprocessing');
    }

    /**
     * Return if the rule is broken.
     *
     * @param bool $checkconditions If false, only DB state will be checked, otherwise conditions state will be checked.
     * @return bool
     */
    public function is_broken(bool $checkconditions = false): bool {
        if ($checkconditions) {
            $broken = false;

            foreach ($this->get_condition_records() as $condition) {
                $instance = condition_base::get_instance(0, $condition->to_record());
                if (!$instance || $instance->is_broken()) {
                    $broken = true;
                    break;
                }
            }
        } else {
            $broken = (bool) $this->get('broken');
        }

        return $broken;
    }

    /**
     * Mark rule as broken.
     */
    public function mark_broken(): void {
        $this->set('broken', 1);
        $this->set('enabled', 0);
        $this->save();
    }

    /**
     * Mark rule as unbroken,
     */
    public function mark_unbroken(): void {
        $this->set('broken', 0);
        $this->save();
    }

    /**
     * Hook after a rule is deleted.
     *
     * @param bool $result Whether or not the delete was successful.
     * @return void
     */
    protected function after_delete($result): void {
        if ($result) {
            cache_helper::purge_by_event('ruleschanged');
        }
    }

    /**
     * Hook after created a rule.
     */
    protected function after_create() {
        cache_helper::purge_by_event('ruleschanged');
    }

    /**
     * Hook after updating a rule.
     *
     * @param bool $result
     */
    protected function after_update($result) {
        cache_helper::purge_by_event('ruleschanged');
    }
}
