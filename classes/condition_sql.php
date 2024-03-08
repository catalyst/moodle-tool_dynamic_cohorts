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

/**
 * Condition SQl class for storing condition related SQl to filter out users.
 *
 * @see condition_base::get_sql
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition_sql {

    /**
     * Join string for SQL.
     * @var string
     */
    protected $join = '';

    /** Where string for SQL.
     * @var string
     */
    protected $where = '';

    /**
     * A list of params for SQL.
     * @var array
     */
    protected $params = [];

    /**
     * condition_sql constructor.
     *
     * @param string $join Join string for SQL.
     * @param string $where Where string for SQL.
     * @param array $params A list of params for SQL.
     */
    public function __construct(string $join, string $where, array $params) {
        $this->join = $join;
        $this->where = $where;
        $this->params = $params;
    }

    /**
     * Returns Join string for SQL.
     * @return string
     */
    public function get_join(): string {
        return $this->join;
    }

    /**
     * Returns Where string for SQL.
     * @return string
     */
    public function get_where(): string {
        return $this->where;
    }

    /**
     * Returns A list of params for SQL.
     * @return array
     */
    public function get_params(): array {
        return $this->params;
    }

    /**
     * Generate an alias for prepending parameters in SQL.
     *
     * @return string
     */
    public static function generate_param_alias(): string {
        static $sqlcnt = 1000;

        // We need to match report builder parameter prefix, otherwise it doesn't pass validation when we use it in RB.
        return 'rbparam' . ($sqlcnt++);
    }

    /**
     * Generating an alias for tables in SQLs.
     *
     * @return string
     */
    public static function generate_table_alias(): string {
        static $sqlcnt = 1000;

        // We need to match report builder alias prefix, otherwise it doesn't pass validation when we use it in RB.
        return 'rbalias' . ($sqlcnt++);
    }
}
