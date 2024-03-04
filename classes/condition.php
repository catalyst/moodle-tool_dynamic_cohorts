<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_dynamic_cohorts;

use core\persistent;

/**
 * Conditions persistent
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition extends persistent {

    /**
     * Table.
     */
    const TABLE = 'tool_dynamic_cohorts_c';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'ruleid' => [
                'type' => PARAM_INT,
            ],
            'classname' => [
                'type' => PARAM_TEXT,
            ],
            'configdata' => [
                'type' => PARAM_RAW,
                'default' => '{}',
            ],
            'sortorder' => [
                'type' => PARAM_INT,
            ],
        ];
    }
}
