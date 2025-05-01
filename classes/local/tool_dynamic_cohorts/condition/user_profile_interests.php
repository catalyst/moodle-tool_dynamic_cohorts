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

namespace tool_dynamic_cohorts\local\tool_dynamic_cohorts\condition;

use html_writer;
use lang_string;
use moodle_url;
use tool_dynamic_cohorts\condition_base;
use tool_dynamic_cohorts\condition_sql;

/**
 * Condition using user profile interests.
 *
 * @package     tool_dynamic_cohorts
 * @copyright   2025 Catalyst IT Australia Pty Ltd
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_profile_interests extends condition_base {

    use fields_trait;

    /**
     * Gets a list of comparison operators for tag fields.
     *
     * @return array A list of operators.
     */
    protected function get_tag_operators(): array {
        return [
            self::TEXT_CONTAINS => get_string('contains', 'filters'),
            self::TEXT_DOES_NOT_CONTAIN => get_string('doesnotcontain', 'filters'),
        ];
    }

    /**
     * Return field name in the condition config form.
     *
     * @return string
     */
    protected static function get_form_field(): string {
        return 'tags';
    }

    /**
     * Condition name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('condition:user_profile_interests', 'tool_dynamic_cohorts');
    }

    /**
     * Add config form elements.
     *
     * @param \MoodleQuickForm $mform
     */
    public function config_form_add(\MoodleQuickForm $mform): void {
        // Operator selection.
        $mform->addElement(
            'select',
            'tags_operator',
            new lang_string('condition:user_profile_interests', 'tool_dynamic_cohorts'),
            $this->get_tag_operators()
        );

        // Tag selection.
        $mform->addElement('autocomplete', 'tags', null, $this->get_tags(), ['multiple' => true]);

        // Link to the tag management page.
        $mform->addElement(
            'static',
            'managetags',
            null,
            html_writer::link(new moodle_url('/tag/manage.php'), get_string('managetags', 'core_tag'), ['target' => '_blank'])
        );

        // Increase the height of the form to make room for the autocomplete dropdown.
        $mform->addElement('html', html_writer::empty_tag('div', ['style' => 'height: 12rem;']));
    }

    /**
     * Validate config form elements.
     *
     * @param array $data Data to validate.
     * @return array
     */
    public function config_form_validate(array $data): array {
        $errors = [];

        if (empty($data['tags'])) {
            $errors['tags'] = get_string('required');
            return $errors;
        }

        return $errors;
    }

    /**
     * Human-readable description of the configured condition.
     *
     * @return string
     */
    public function get_config_description(): string {
        $data = $this->get_config_data();
        $tags = $this->get_tags();
        $badges = [];
        foreach ($data['tags'] as $id) {
            $badges[] = html_writer::tag('span', $tags[$id], ['class' => 'badge badge-secondary']);
        }

        return (int) $data['tags_operator'] === self::TEXT_CONTAINS
            ? get_string('condition:user_profile_interests_description', 'tool_dynamic_cohorts', implode(' ', $badges))
            : get_string('condition:user_profile_interests_description_not', 'tool_dynamic_cohorts', implode(' ', $badges));
    }

    /**
     * Gets SQL for a given condition.
     *
     * @return condition_sql
     */
    public function get_sql(): condition_sql {
        global $DB;

        $data = $this->get_config_data();
        $inner = condition_sql::generate_param_alias();
        [$insql, $params] = $DB->get_in_or_equal($data['tags'], SQL_PARAMS_NAMED, condition_sql::generate_param_alias());

        $join = "LEFT JOIN (SELECT ti.id, ti.itemid
                      FROM {tag_instance} ti
                     WHERE ti.component = 'core' AND ti.itemtype = 'user'
                           AND ti.tagid $insql) $inner ON u.id = $inner.itemid";

        $where = (int) $data['tags_operator'] === self::TEXT_CONTAINS ? "$inner.itemid IS NOT NULL" : "$inner.itemid IS NULL";

        $result = new condition_sql($join, $where, $params);

        return $result;
    }

    /**
     * A list of events the condition is listening to.
     *
     * @return string[]
     */
    public function get_events(): array {
        return [
            '\core\event\user_created',
            '\core\event\user_updated',
        ];
    }

    /**
     * Is condition broken.
     *
     * @return bool
     */
    public function is_broken(): bool {
        $data = $this->get_config_data();

        if (empty($data)) {
            return false;
        }

        return empty($data['tags']) || empty($data['tags_operator']);
    }

    /**
     * Get a list of all user profile interest tags.
     *
     * @return array A list of tags with tag ID as the key and tag rawname (display name) as the value.
     */
    private function get_tags(): array {
        global $DB;

        $params = [
            'component' => 'core',
            'itemtype' => 'user',
        ];
        $sql = "SELECT DISTINCT tag.id, tag.rawname
                           FROM {tag} tag
                           JOIN {tag_instance} ti ON ti.tagid = tag.id
                          WHERE ti.component = :component AND ti.itemtype = :itemtype";

        $records = $DB->get_records_sql($sql, $params);
        return array_combine(array_column($records, 'id'), array_column($records, 'rawname'));
    }
}
