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

namespace tool_dynamic_cohorts\event;

use core\event\base;

 /**
  * Event triggered when a rule created.
  *
  * @property-read array $other {
  *      Extra information about event.
  *      - string ruleid: new rule id.
  * }
  *
  * @package     tool_dynamic_cohorts
  * @copyright   2024 Catalyst IT
  * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
  */
class rule_created extends base {

    /**
     * Initialise the rule data.
     */
    protected function init() {
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['crud'] = 'u';
        $this->context = \context_system::instance();
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event:rulecreated', 'tool_dynamic_cohorts');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description(): string {
        return "User with id '{$this->userid}' created a dynamic cohort rule with id '{$this->other['ruleid']}'";
    }

    /**
     * Validates the custom data.
     *
     * @throws \coding_exception if missing required data.
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['ruleid'])) {
            throw new \coding_exception('The \'ruleid\' value must be set in other.');
        }
    }
}
