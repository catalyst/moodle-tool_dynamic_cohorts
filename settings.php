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
 * Settings page
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$ADMIN->add('accounts', new admin_category('tool_dynamic_cohorts', get_string('pluginname', 'tool_dynamic_cohorts')));

if ($hassiteconfig) {
    $settings = new admin_settingpage('tool_dynamic_cohorts_settings', new lang_string('settings'));
    $settings->add(new admin_setting_configcheckbox(
        'tool_dynamic_cohorts/releasemembers',
        new lang_string('settings:releasemembers', 'tool_dynamic_cohorts'),
        new lang_string('settings:releasemembers_desc', 'tool_dynamic_cohorts'),
        0
    ));
    $settings->add(new admin_setting_configcheckbox(
        'tool_dynamic_cohorts/realtime',
        new lang_string('settings:realtime', 'tool_dynamic_cohorts'),
        new lang_string('settings:realtime_desc', 'tool_dynamic_cohorts'),
        1
    ));
    $ADMIN->add('tool_dynamic_cohorts', $settings);
}

$ADMIN->add('tool_dynamic_cohorts', new admin_externalpage(
    'tool_dynamic_cohorts_rules',
    get_string('managerules', 'tool_dynamic_cohorts'),
    new moodle_url('/admin/tool/dynamic_cohorts/index.php'),
    'tool/dynamic_cohorts:manage'
));
