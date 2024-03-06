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

use coding_exception;
use moodle_exception;
use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Condition form.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition_form extends moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', 0);

        $mform->addElement('hidden', 'sortorder');
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', -1);

        $this->get_condition()->config_form_add($mform);
    }

    /**
     * Get condition instance.
     *
     * @return condition_base
     */
    protected function get_condition(): condition_base {
        if (empty($this->_customdata['classname'])) {
            throw new coding_exception('Condition class name is not set');
        }

        $conditions = condition_manager::get_all_conditions();
        if (!array_key_exists($this->_customdata['classname'], $conditions)) {
            throw new moodle_exception('Condition is broken. Invalid condition class.');
        }

        return $conditions[$this->_customdata['classname']];
    }

    /**
     * Extra validation.
     *
     * @param  array $data Data to validate.
     * @param  array $files Array of files.
     * @return array of additional errors, or overridden errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        return array_merge($errors, $this->get_condition()->config_form_validate($data));
    }

}
