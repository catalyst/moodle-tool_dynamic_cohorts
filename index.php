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

/**
 * Rules page.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\notification;
use core_reportbuilder\system_report_factory;
use tool_dynamic_cohorts\rule;
use tool_dynamic_cohorts\reportbuilder\local\systemreports\rules;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('tool_dynamic_cohorts_rules');

$manageurl = new moodle_url('/admin/tool/dynamic_cohorts/index.php');
$editurl = new moodle_url('/admin/tool/dynamic_cohorts/edit.php');

foreach (rule::get_records() as $rule) {
    if ($rule->is_broken()) {
        notification::warning(get_string('brokenruleswarning', 'tool_dynamic_cohorts'));
        break;
    }
}

$report = system_report_factory::create(rules::class, context_system::instance(), 'tool_dynamic_cohorts');

$PAGE->requires->js_call_amd('tool_dynamic_cohorts/manage_rules', 'init');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managerules', 'tool_dynamic_cohorts'));
echo $OUTPUT->render_from_template('tool_dynamic_cohorts/button', [
    'url' => $editurl->out(),
    'text' => get_string('addrule', 'tool_dynamic_cohorts'),
]);
echo $report->output();
echo $OUTPUT->footer();
