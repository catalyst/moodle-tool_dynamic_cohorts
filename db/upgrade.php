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

use tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\course_completion;

/**
 * Upgrade hook.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion The old version of the plugin
 * @return bool
 */
function xmldb_tool_dynamic_cohorts_upgrade($oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2024032501) {
        $table = new xmldb_table('tool_dynamic_cohorts');
        $field = new xmldb_field('operator', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0);

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024032501, 'tool', 'dynamic_cohorts');
    }

    if ($oldversion < 2024091300) {
        // Define field realtime to be added to tool_dynamic_cohorts.
        $table = new xmldb_table('tool_dynamic_cohorts');
        $field = new xmldb_field('realtime', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'operator');

        // Conditionally launch add field realtime.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Dynamic_cohorts savepoint reached.
        upgrade_plugin_savepoint(true, 2024091300, 'tool', 'dynamic_cohorts');
    }

    if ($oldversion < 2026052600) {
        // Migrate legacy course completion conditions to the new course completion class.
        // This script does not automatically collapse legacy completion condition chains.

        $legacycompleted = 'tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\course_completed';
        $legacynotcompleted = 'tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition\course_not_completed';

        [$insql, $inparams] = $DB->get_in_or_equal([$legacycompleted, $legacynotcompleted], SQL_PARAMS_NAMED);
        $records = $DB->get_recordset_select(
            'tool_dynamic_cohorts_c',
            "classname $insql",
            $inparams,
            '',
            'id, classname, configdata'
        );

        foreach ($records as $record) {
            $oldconfig = json_decode($record->configdata, true);

            // Skip migration for invalid records. They will need to be fixed manually.
            if (!is_array($oldconfig)) {
                continue;
            }

            $courseid = (int) ($oldconfig['courseid'] ?? 0);
            if (empty($courseid)) {
                continue;
            }

            if ($record->classname === $legacycompleted) {
                $periodoperator = match ((int) ($oldconfig['operator'] ?? 1)) {
                    1 => course_completion::PERIOD_ANY,
                    2 => course_completion::PERIOD_BEFORE,
                    3 => course_completion::PERIOD_AFTER,
                    default => course_completion::PERIOD_ANY,
                };

                $newconfig = [
                    'completionoperator' => course_completion::OPERATOR_HAVE_COMPLETED,
                    'selectionoperator' => course_completion::SELECTION_ALL,
                    'courseids' => [$courseid],
                    'periodoperator' => $periodoperator,
                    'timecompleted' => (int) ($oldconfig['timecompleted'] ?? 0),
                ];
            } else {
                $newconfig = [
                    'completionoperator' => course_completion::OPERATOR_HAVE_NOT_COMPLETED,
                    'selectionoperator' => course_completion::SELECTION_ANY,
                    'courseids' => [$courseid],
                    'periodoperator' => course_completion::PERIOD_ANY,
                    'timecompleted' => 0,
                ];
            }

            $DB->update_record('tool_dynamic_cohorts_c', (object) [
                'id' => $record->id,
                'classname' => course_completion::class,
                'configdata' => json_encode($newconfig),
            ]);
        }
        $records->close();

        upgrade_plugin_savepoint(true, 2026052600, 'tool', 'dynamic_cohorts');
    }

    return true;
}
