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
 * Toggle action page.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;
use tool_dynamic_cohorts\rule;
use tool_dynamic_cohorts\event\rule_updated;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/formslib.php');

admin_externalpage_setup('tool_dynamic_cohorts_rules');

$ruleid = required_param('ruleid', PARAM_INT);
$confirm = optional_param('confirm', '', PARAM_ALPHANUM);

$rule = rule::get_record(['id' => $ruleid], MUST_EXIST);
$manageurl = new moodle_url('/admin/tool/dynamic_cohorts/index.php');

if (!$rule->is_broken()) {
    $identificator = $rule->is_enabled() ? 'ruledisabled' : 'ruleenabled';
    $action = $rule->is_enabled() ? 'disable' : 'enable';
    $message = get_string($identificator, 'tool_dynamic_cohorts');
    $newvalue = (int) !$rule->is_enabled();
    $messagetype = notification::NOTIFY_SUCCESS;

    if ($confirm != md5($ruleid)) {
        $confirmstring = get_string($action . '_confirm', 'tool_dynamic_cohorts', $rule->get('name'));
        $cinfirmoptions = ['ruleid' => $ruleid, 'confirm' => md5($ruleid), 'sesskey' => sesskey()];
        $deleteurl = new moodle_url('/admin/tool/dynamic_cohorts/toggle.php', $cinfirmoptions);

        $PAGE->navbar->add(get_string('delete_rule', 'tool_dynamic_cohorts'));

        echo $OUTPUT->header();
        echo $OUTPUT->confirm($confirmstring, $deleteurl, $manageurl);
        echo $OUTPUT->footer();

    } else if (data_submitted() && confirm_sesskey()) {
        $rule->set('enabled', $newvalue);
        $rule->save();
        rule_updated::create(['other' => ['ruleid' => $rule->get('id')]])->trigger();
        redirect($manageurl, $message, null, $messagetype);
    }
} else {
    $newvalue = 0;
    $message = get_string('ruleisbroken', 'tool_dynamic_cohorts');
    $messagetype = notification::NOTIFY_ERROR;

    $rule->set('enabled', $newvalue);
    $rule->save();
    rule_updated::create(['other' => ['ruleid' => $rule->get('id')]])->trigger();
    redirect($manageurl, $message, null, $messagetype);
}