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

use html_writer;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * A form for adding/editing rules.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', 0);

        $mform->addElement('text', 'name', get_string('name', 'tool_dynamic_cohorts'), 'size="50"');
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required');
        $mform->addHelpButton('name', 'name', 'tool_dynamic_cohorts');

        $mform->addElement(
            'textarea',
            'description',
            get_string('description', 'tool_dynamic_cohorts'),
            ['rows' => 5, 'cols' => 50]
        );
        $mform->addHelpButton('description', 'description', 'tool_dynamic_cohorts');
        $mform->setType('description', PARAM_TEXT);

        $mform->addElement(
            'autocomplete',
            'cohortid',
            get_string('cohortid', 'tool_dynamic_cohorts'),
            $this->get_cohort_options(),
            ['noselectionstring' => get_string('choosedots')]
        );
        $mform->addHelpButton('cohortid', 'cohortid', 'tool_dynamic_cohorts');
        $mform->addRule('cohortid', get_string('required'), 'required');

        $link = html_writer::link(new moodle_url('/cohort/index.php'), get_string('managecohorts', 'tool_dynamic_cohorts'));
        $mform->addElement('static', '', '', $link);

        // Hidden field for storing condition json string.
        $mform->addElement('hidden', 'conditionjson', '', ['id' => 'id_conditionjson']);
        $mform->setType('conditionjson', PARAM_RAW_TRIMMED);

        // A flag to indicate whether the conditions were updated or not.
        $mform->addElement('hidden', 'isconditionschanged', 0, ['id' => 'id_isconditionschanged']);
        $mform->setType('isconditionschanged', PARAM_BOOL);
        $mform->setDefault('isstepschanged', 0);

        $conditions = ['' => get_string('choosedots')];
        foreach (condition_manager::get_all_conditions() as $class => $condition) {
            $conditions[$class] = $condition->get_name();
        }

        $group = [];
        $group[] = $mform->createElement('select', 'condition', '', $conditions);
        $group[] = $mform->createElement('button', 'conditionmodalbutton', get_string('addcondition', 'tool_dynamic_cohorts'));
        $mform->addGroup($group, 'conditiongroup', get_string('condition', 'tool_dynamic_cohorts'), ' ', false);

        $mform->addElement(
            'advcheckbox',
            'bulkprocessing',
            get_string('bulkprocessing', 'tool_dynamic_cohorts'),
            get_string('enable'),
            [],
            [0, 1]
        );
        $mform->addHelpButton('bulkprocessing', 'bulkprocessing', 'tool_dynamic_cohorts');

        $mform->addElement('select',
            'operator',
            get_string('logical_operator', 'tool_dynamic_cohorts'),
            [rule_manager::CONDITIONS_OPERATOR_AND => 'AND', rule_manager::CONDITIONS_OPERATOR_OR => 'OR']);
        $mform->addHelpButton('operator', 'logical_operator', 'tool_dynamic_cohorts');
        $mform->setType('operator', PARAM_INT);

        $this->add_action_buttons();
    }

    /**
     * Get a list of all cohorts in the system.
     *
     * @return array
     */
    protected function get_cohort_options(): array {
        $options = ['' => get_string('choosedots')];

        // Retrieve only available cohorts to display in the select.
        foreach (cohort_manager::get_cohorts(true) as $cohort) {
            $options[$cohort->id] = $cohort->name;
        }

        // Add the currently selected cohort as it won't be in the list.
        if (isset($this->_customdata['defaultcohort'])) {
            $cohort = $this->_customdata['defaultcohort'];
            $options[$cohort->id] = $cohort->name;
        }

        return $options;
    }

    /**
     * Definition after data is set.
     */
    public function definition_after_data() {
        global $OUTPUT;

        $mform = $this->_form;
        $conditionjson = $mform->getElementValue('conditionjson');
        $conditions = $OUTPUT->render_from_template('tool_dynamic_cohorts/conditions', [
            'conditions' => json_decode($conditionjson, true),
        ]);

        $mform->insertElementBefore(
            $mform->createElement(
                'html',
                '<div id="conditions">' . $conditions . '</div>'
            ),
            'buttonar'
        );
    }
}
