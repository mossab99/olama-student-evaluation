<?php
/**
 * Read-only student files report using Olama Core's public read-model contract.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_Student_Evaluation_Student_Files
{
    public static function is_available(): bool
    {
        if (!function_exists('olama_core') || !class_exists('Olama_School_Academic_Bridge')) {
            return false;
        }

        $models = olama_core()->read_models();
        return $models->available('students')
            && $models->available('families')
            && $models->available('student_years');
    }

    /**
     * Return students in one local grade/section and their imported history.
     *
     * The report intentionally contains no cached copy of personal data. All
     * enrollment, history and sibling reads are batched for the selected group.
     *
     * @return object[]|WP_Error
     */
    public static function get_report($grade_id, $section_id, $academic_year_id)
    {
        global $wpdb;

        if (!class_exists('Olama_School_Permissions')
            || !Olama_School_Permissions::can('olama_access_evaluation')
            || !Olama_School_Permissions::can('olama_view_student_files')) {
            return new WP_Error('student_files_forbidden', __('You do not have permission to view student files.', 'olama-student-evaluation'));
        }
        if (!self::is_available()) {
            return new WP_Error('student_files_unavailable', __('Student records are not available from Olama Core.', 'olama-student-evaluation'));
        }

        $grade_id = absint($grade_id);
        $section_id = absint($section_id);
        $academic_year_id = absint($academic_year_id);
        if (!$grade_id || !$section_id || !$academic_year_id) {
            return self::invalid_filters();
        }

        $study_year = self::canonical_year(Olama_School_Academic_Bridge::get_study_year($academic_year_id));
        if ($study_year === '') {
            return new WP_Error('student_files_invalid_year', __('Select a valid academic year.', 'olama-student-evaluation'));
        }

        $sections = $wpdb->prefix . 'olama_sections';
        $grades = $wpdb->prefix . 'olama_grades';
        $section = $wpdb->get_row($wpdb->prepare(
            "SELECT sec.core_grade_id, sec.core_section_id, sec.section_name, g.grade_name
             FROM {$sections} sec
             INNER JOIN {$grades} g ON g.id=sec.grade_id AND g.core_grade_id=sec.core_grade_id
             WHERE sec.id=%d AND sec.grade_id=%d AND sec.academic_year_id=%d
               AND REPLACE(TRIM(sec.core_study_year), '/', '-')=%s",
            $section_id,
            $grade_id,
            $academic_year_id,
            $study_year
        ));
        if ($wpdb->last_error) {
            return self::query_error();
        }
        if (!$section || trim((string) $section->core_grade_id) === '' || trim((string) $section->core_section_id) === '') {
            return self::invalid_filters();
        }

        $models = olama_core()->read_models();
        $students = $models->table('students');
        $families = $models->table('families');
        $years = $models->table('student_years');
        $active = self::active_condition('s', 'y');
        $latest = self::latest_enrollment_condition($years, 'y');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT s.id, s.student_uid, s.student_name, s.student_national_no AS national_id,
                    s.birth_place, s.birth_date, s.family_uid,
                    f.father_mobile,
                    COALESCE(NULLIF(TRIM(f.mother_mobile), ''), s.mother_mobile) AS mother_mobile,
                    COALESCE(NULLIF(TRIM(y.class_name), ''), %s) AS current_grade,
                    COALESCE(NULLIF(TRIM(y.section_name), ''), %s) AS current_section,
                    y.school_id, y.school_name
             FROM {$students} s
             INNER JOIN {$years} y ON y.student_uid=s.student_uid
             LEFT JOIN {$families} f ON f.family_uid=s.family_uid
             WHERE REPLACE(TRIM(y.study_year), '/', '-')=%s
               AND y.class_id=%s AND y.section_id=%s
               AND {$latest} AND {$active}
             ORDER BY s.student_name ASC, s.student_uid ASC",
            $section->grade_name,
            $section->section_name,
            $study_year,
            (string) $section->core_grade_id,
            (string) $section->core_section_id
        ));
        if ($wpdb->last_error) {
            return self::query_error();
        }
        if (!$rows) {
            return array();
        }

        $report = array();
        $student_uids = array();
        $family_uids = array();
        foreach ($rows as $row) {
            $uid = (string) $row->student_uid;
            $student_uids[] = $uid;
            $family_uid = trim((string) $row->family_uid);
            if ($family_uid !== '') {
                $family_uids[$family_uid] = $family_uid;
            }
            $student = new stdClass();
            $student->id = (int) $row->id;
            foreach (array('student_uid', 'student_name', 'national_id', 'birth_place', 'birth_date', 'father_mobile', 'mother_mobile', 'current_grade', 'current_section') as $field) {
                $student->{$field} = (string) $row->{$field};
            }
            $student->study_year = $study_year;
            $student->history = array();
            $student->siblings = array();
            $report[$uid] = $student;
        }

        $history_placeholders = implode(', ', array_fill(0, count($student_uids), '%s'));
        $history = $wpdb->get_results($wpdb->prepare(
            "SELECT y.student_uid, y.study_year, y.class_name AS grade_name, y.section_name
             FROM {$years} y
             WHERE y.student_uid IN ({$history_placeholders})
               AND REPLACE(TRIM(y.study_year), '/', '-') < %s
               AND {$latest}
             ORDER BY REPLACE(TRIM(y.study_year), '/', '-') DESC, y.id DESC",
            array_merge($student_uids, array($study_year))
        ));
        if ($wpdb->last_error) {
            return self::query_error();
        }
        foreach ($history ?: array() as $row) {
            $year = self::canonical_year($row->study_year);
            // Ignore malformed years as well as the selected/future years.
            if ($year === '' || strcmp($year, $study_year) >= 0) {
                continue;
            }
            $report[(string) $row->student_uid]->history[] = (object) array(
                'study_year' => $year,
                'grade_name' => (string) $row->grade_name,
                'section_name' => (string) $row->section_name,
            );
        }

        if ($family_uids) {
            $family_placeholders = implode(', ', array_fill(0, count($family_uids), '%s'));
            $siblings = $wpdb->get_results($wpdb->prepare(
                "SELECT s.student_uid, s.student_name, s.family_uid, y.school_id, y.school_name
                 FROM {$students} s
                 INNER JOIN {$years} y ON y.student_uid=s.student_uid
                 WHERE s.family_uid IN ({$family_placeholders})
                   AND REPLACE(TRIM(y.study_year), '/', '-')=%s
                   AND {$latest} AND {$active}
                 ORDER BY s.student_name ASC, s.student_uid ASC",
                array_merge(array_values($family_uids), array($study_year))
            ));
            if ($wpdb->last_error) {
                return self::query_error();
            }
            $siblings_by_family = array();
            foreach ($siblings ?: array() as $sibling) {
                $siblings_by_family[(string) $sibling->family_uid][(string) $sibling->student_uid] = $sibling;
            }
            foreach ($rows as $row) {
                $names = array();
                foreach ($siblings_by_family[(string) $row->family_uid] ?? array() as $uid => $sibling) {
                    if ((string) $uid === (string) $row->student_uid || !self::same_school($row, $sibling)) {
                        continue;
                    }
                    // Keep distinct students even when they have equal names.
                    $names[(string) $uid] = (string) $sibling->student_name;
                }
                $report[(string) $row->student_uid]->siblings = array_values($names);
            }
        }

        return array_values($report);
    }

    private static function canonical_year($value)
    {
        $value = str_replace('/', '-', trim((string) $value));
        return preg_match('/^\d{4}-\d{4}$/D', $value) ? $value : '';
    }

    private static function active_condition($student_alias, $year_alias)
    {
        // Match attendance eligibility: selected-year values override registry.
        $excluded = "('0', '2', 'inactive', 'disabled', 'withdrawn', 'stopped', 'منسحب', 'غير مستمر', 'غير فعال', 'متوقف')";
        return "LOWER(TRIM(COALESCE(NULLIF(TRIM({$year_alias}.student_status), ''), {$student_alias}.student_status, ''))) NOT IN {$excluded}
            AND LOWER(TRIM(COALESCE(NULLIF(TRIM({$year_alias}.student_status_name), ''), {$student_alias}.student_status_name, ''))) NOT IN {$excluded}";
    }

    private static function latest_enrollment_condition($years, $alias)
    {
        // Slash/hyphen variants can coexist under Core's original unique key.
        // Core refreshes existing IDs in place, including last_synced_at on an
        // unchanged payload; insertion order alone cannot identify fresh data.
        $revision = "COALESCE({$alias}.last_synced_at, {$alias}.updated_at, {$alias}.created_at, '')";
        $newer_revision = "COALESCE(newer.last_synced_at, newer.updated_at, newer.created_at, '')";
        return "NOT EXISTS (SELECT 1 FROM {$years} newer
            WHERE newer.student_uid={$alias}.student_uid
              AND REPLACE(TRIM(newer.study_year), '/', '-')=REPLACE(TRIM({$alias}.study_year), '/', '-')
              AND ({$newer_revision}>{$revision}
                   OR ({$newer_revision}={$revision} AND newer.id>{$alias}.id)))";
    }

    private static function same_school($student, $sibling)
    {
        $school_id = trim((string) $student->school_id);
        $sibling_school_id = trim((string) $sibling->school_id);
        if ($school_id !== '' && $sibling_school_id !== '') {
            return $school_id === $sibling_school_id;
        }
        $school_name = trim((string) $student->school_name);
        $sibling_school_name = trim((string) $sibling->school_name);
        return $school_name === '' || $sibling_school_name === '' || $school_name === $sibling_school_name;
    }

    private static function invalid_filters()
    {
        return new WP_Error('student_files_invalid_filters', __('Select a grade and section belonging to the selected academic year.', 'olama-student-evaluation'));
    }

    private static function query_error()
    {
        return new WP_Error('student_files_query_failed', __('Student files could not be loaded. Please try again.', 'olama-student-evaluation'));
    }
}
