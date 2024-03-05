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

use moodle_exception;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/cohort/lib.php');

/**
 * Cohort manager class.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cohort_manager {

    /**
     * Cohort component.
     */
    const COHORT_COMPONENT = 'tool_dynamic_cohorts';

    /**
     * Get a list of all cohort names in the system keyed by cohort ID.
     *
     * @param bool $excludemanaged Exclude cohorts managed by us.
     * @return array
     */
    public static function get_cohorts(bool $excludemanaged = false): array {
        $cohorts = [];
        foreach (\cohort_get_all_cohorts(0, 0)['cohorts'] as $cohort) {
            if (empty($cohort->component) || (!$excludemanaged && $cohort->component === self::COHORT_COMPONENT)) {
                $cohorts[$cohort->id] = $cohort;
            }
        }

        return $cohorts;
    }

    /**
     * Set cohort to be managed by tool_dynamic_cohorts.
     *
     * @param int $cohortid Cohort ID.
     */
    public static function manage_cohort(int $cohortid): void {
        $cohorts = self::get_cohorts();
        if (!empty($cohorts[$cohortid])) {
            $cohort = $cohorts[$cohortid];

            if ($cohort->component === self::COHORT_COMPONENT) {
                throw new moodle_exception('Cohort ' . $cohortid . ' is already managed by tool_dynamic_cohorts');
            }

            $cohort->component = 'tool_dynamic_cohorts';
            cohort_update_cohort($cohort);
        }
    }

    /**
     * Unset cohort from being managed by tool_dynamic_cohorts.
     *
     * @param int $cohortid Cohort ID.
     */
    public static function unmanage_cohort(int $cohortid): void {
        $cohorts = self::get_cohorts();

        if (!empty($cohorts[$cohortid])) {
            $cohort = $cohorts[$cohortid];
            $cohort->component = '';
            cohort_update_cohort($cohort);
        }
    }
}
