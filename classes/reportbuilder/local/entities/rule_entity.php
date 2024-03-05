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

namespace tool_dynamic_cohorts\reportbuilder\local\entities;

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\report\column;
use lang_string;
use tool_dynamic_cohorts\rule;

/**
 * Report builder entity for rules.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2024 Catalyst IT
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule_entity extends base {

    /**
     * Returns the default table aliases.
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return [
            'tool_dynamic_cohorts' => 'tdc',
        ];
    }

    /**
     * Returns the default table name.
     * @return \lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('rule_entity', 'tool_dynamic_cohorts');
    }

    /**
     * Initialises the entity.
     * @return \core_reportbuilder\local\entities\base
     */
    public function initialise(): base {
        foreach ($this->get_all_columns() as $column) {
            $this->add_column($column);
        }

        return $this;
    }

    /**
     * Returns list of available columns.
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $alias = $this->get_table_alias('tool_dynamic_cohorts');

        $columns[] = (new column(
            'id',
            new lang_string('rule_entity.id', 'tool_dynamic_cohorts'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$alias}.id")
            ->set_is_sortable(true);

        $columns[] = (new column(
            'name',
            new lang_string('rule_entity.name', 'tool_dynamic_cohorts'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.name")
            ->add_field("{$alias}.id")
            ->set_is_sortable(true)
            ->add_callback(function ($value, $row) {
                return $value;
            });

        $columns[] = (new column(
            'description',
            new lang_string('rule_entity.description', 'tool_dynamic_cohorts'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.description")
            ->set_is_sortable(false);

        $columns[] = (new column(
            'bulkprocessing',
            new lang_string('rule_entity.bulkprocessing', 'tool_dynamic_cohorts'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.bulkprocessing")
            ->add_fields("{$alias}.id, {$alias}.name, {$alias}.bulkprocessing")
            ->set_is_sortable(true)
            ->add_callback(function ($value, $row) {
                $rule = new rule(0, $row);
                return !empty($rule->is_bulk_processing()) ? get_string('yes') : get_string('no');
            });

        $columns[] = (new column(
            'status',
            new lang_string('rule_entity.status', 'tool_dynamic_cohorts'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.broken")
            ->add_fields("{$alias}.id, {$alias}.name, {$alias}.broken,  {$alias}.enabled")
            ->set_is_sortable(true)
            ->add_callback(function ($value, $row) {
                global $OUTPUT;

                $rule = new rule(0, $row);

                if ($rule->is_enabled()) {
                    $enabled = $OUTPUT->pix_icon('t/hide', get_string('enabled', 'tool_dynamic_cohorts'));
                } else {
                    $enabled = $OUTPUT->pix_icon('t/show', get_string('disabled', 'tool_dynamic_cohorts'));
                }

                if ($rule->is_broken()) {
                    $broken = $OUTPUT->pix_icon('i/invalid', get_string('statuserror'));
                } else {
                    $broken = $OUTPUT->pix_icon('i/valid', get_string('ok'));
                }

                return $broken . $enabled;
            });

        return $columns;
    }
}
