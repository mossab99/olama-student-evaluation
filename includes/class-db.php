<?php

if (!defined('ABSPATH')) {
    exit;
}

class Olama_Student_Evaluation_DB {
    public static function install() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        self::align_identifier_columns();
        self::prepare_existing_records();

        dbDelta("CREATE TABLE {$wpdb->prefix}olama_ev_templates (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            academic_year_id mediumint(9) NOT NULL,
            grade_id mediumint(9) NOT NULL,
            subject_id mediumint(9) DEFAULT NULL,
            semester_id mediumint(9) DEFAULT NULL,
            template_name varchar(255) NOT NULL,
            context_type varchar(50) DEFAULT 'student' NOT NULL,
            score_config longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY year_grade (academic_year_id, grade_id),
            KEY subject_id (subject_id),
            KEY context_type (context_type)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$wpdb->prefix}olama_ev_domains (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            template_id mediumint(9) NOT NULL,
            grade_id mediumint(9) DEFAULT NULL,
            title_ar varchar(255) NOT NULL,
            context_type varchar(50) DEFAULT 'student' NOT NULL,
            sort_order int(11) DEFAULT 0 NOT NULL,
            PRIMARY KEY  (id),
            KEY template_id (template_id),
            KEY context_type (context_type)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$wpdb->prefix}olama_ev_categories (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            domain_id mediumint(9) NOT NULL,
            title_ar varchar(255) NOT NULL,
            sort_order int(11) DEFAULT 0 NOT NULL,
            PRIMARY KEY  (id),
            KEY domain_id (domain_id)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$wpdb->prefix}olama_ev_indicators (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            category_id mediumint(9) NOT NULL,
            indicator_text text NOT NULL,
            max_score int(11) DEFAULT 5 NOT NULL,
            weight decimal(5,2) DEFAULT 1.00 NOT NULL,
            is_critical boolean DEFAULT 0 NOT NULL,
            context_type varchar(50) DEFAULT 'student' NOT NULL,
            sort_order int(11) DEFAULT 0 NOT NULL,
            PRIMARY KEY  (id),
            KEY category_id (category_id),
            KEY context_type (context_type)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$wpdb->prefix}olama_ev_records (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            template_id mediumint(9) NOT NULL,
            student_id bigint(20) UNSIGNED DEFAULT NULL,
            student_uid varchar(100) DEFAULT NULL,
            subject_id mediumint(9) DEFAULT 0 NOT NULL,
            teacher_id bigint(20) UNSIGNED NOT NULL,
            academic_year_id mediumint(9) NOT NULL,
            semester_id mediumint(9) NOT NULL,
            context_type varchar(50) DEFAULT 'student' NOT NULL,
            related_entity_type varchar(50) DEFAULT NULL,
            related_entity_id bigint(20) DEFAULT NULL,
            status varchar(20) DEFAULT 'draft' NOT NULL,
            supervisor_comments text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY template_id (template_id),
            KEY student_id (student_id),
            KEY student_uid (student_uid),
            KEY subject_id (subject_id),
            KEY teacher_id (teacher_id),
            KEY year_semester (academic_year_id, semester_id),
            KEY context_type (context_type),
            UNIQUE KEY student_evaluation (template_id, student_uid, academic_year_id, semester_id, context_type, subject_id),
            UNIQUE KEY related_evaluation (template_id, context_type, related_entity_type, related_entity_id)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$wpdb->prefix}olama_ev_scores (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            evaluation_id mediumint(9) NOT NULL,
            indicator_id mediumint(9) NOT NULL,
            score tinyint(1) DEFAULT NULL,
            calculated_score decimal(5,2) DEFAULT NULL,
            notes text DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY evaluation_id (evaluation_id),
            KEY indicator_id (indicator_id),
            UNIQUE KEY evaluation_indicator (evaluation_id, indicator_id)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$wpdb->prefix}olama_attendance (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            student_id bigint(20) UNSIGNED NOT NULL,
            student_uid varchar(100) DEFAULT NULL,
            academic_year_id mediumint(9) NOT NULL,
            semester_id mediumint(9) NOT NULL,
            section_id mediumint(9) NOT NULL,
            attendance_date date NOT NULL,
            status varchar(20) DEFAULT 'present' NOT NULL,
            reason text DEFAULT NULL,
            recorded_by bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY student_uid_date (student_uid, attendance_date),
            KEY student_id (student_id),
            KEY student_uid (student_uid),
            KEY academic_year_id (academic_year_id),
            KEY semester_id (semester_id),
            KEY section_id (section_id),
            KEY attendance_date (attendance_date)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$wpdb->prefix}olama_attendance_sheets (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            academic_year_id mediumint(9) NOT NULL,
            section_id mediumint(9) NOT NULL,
            attendance_date date NOT NULL,
            recorded_by bigint(20) UNSIGNED DEFAULT NULL,
            status varchar(20) DEFAULT 'completed' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY section_date (section_id, attendance_date),
            KEY academic_year_id (academic_year_id),
            KEY attendance_date (attendance_date)
        ) {$charset_collate};");
    }

    /**
     * Widen Core-backed identifiers before backfilling them. Doing this before
     * dbDelta prevents a 100-character Core UID from being truncated by the
     * legacy 50-character evaluation columns during migration.
     */
    private static function align_identifier_columns() {
        global $wpdb;

        $records = $wpdb->prefix . 'olama_ev_records';
        if (self::table_exists($records)) {
            $wpdb->query(
                "ALTER TABLE {$records}
                 MODIFY student_id bigint(20) UNSIGNED DEFAULT NULL,
                 MODIFY student_uid varchar(100) DEFAULT NULL"
            );
        }

        $attendance = $wpdb->prefix . 'olama_attendance';
        if (self::table_exists($attendance)) {
            $wpdb->query(
                "ALTER TABLE {$attendance}
                 MODIFY student_id bigint(20) UNSIGNED NOT NULL,
                 MODIFY student_uid varchar(100) DEFAULT NULL"
            );
        }
    }

    /**
     * Normalize legacy rows before dbDelta adds the logical unique indexes.
     * Duplicate scores are merged into the newest evaluation record.
     */
    private static function prepare_existing_records() {
        global $wpdb;

        $records = $wpdb->prefix . 'olama_ev_records';
        if (!self::table_exists($records)) {
            return;
        }

        $students = $wpdb->prefix . 'olama_students';
        if (self::table_exists($students)) {
            $wpdb->query(
                "UPDATE {$records} r
                 INNER JOIN {$students} s ON s.id = r.student_id
                 SET r.student_uid = s.student_uid
                 WHERE r.student_id IS NOT NULL
                   AND (
                       r.student_uid IS NULL
                       OR r.student_uid = ''
                       OR (CHAR_LENGTH(r.student_uid) = 50 AND s.student_uid LIKE CONCAT(r.student_uid, '%'))
                   )"
            );

            $attendance = $wpdb->prefix . 'olama_attendance';
            if (self::table_exists($attendance)) {
                $wpdb->query(
                    "DELETE older FROM {$attendance} older
                     INNER JOIN {$attendance} newer
                        ON newer.student_id = older.student_id
                       AND newer.attendance_date = older.attendance_date
                       AND newer.id > older.id"
                );
                $wpdb->query(
                    "UPDATE {$attendance} a
                     INNER JOIN {$students} s ON s.id = a.student_id
                     SET a.student_uid = s.student_uid
                     WHERE a.student_uid IS NULL
                        OR a.student_uid = ''
                        OR (CHAR_LENGTH(a.student_uid) = 50 AND s.student_uid LIKE CONCAT(a.student_uid, '%'))"
                );
            }
        }

        $wpdb->query("UPDATE {$records} SET subject_id = 0 WHERE subject_id IS NULL");
        self::merge_duplicate_records($records, 'student');
        self::merge_duplicate_records($records, 'related');

        $scores = $wpdb->prefix . 'olama_ev_scores';
        if (self::table_exists($scores)) {
            $wpdb->query(
                "DELETE older FROM {$scores} older
                 INNER JOIN {$scores} newer
                    ON newer.evaluation_id = older.evaluation_id
                   AND newer.indicator_id = older.indicator_id
                   AND newer.id > older.id"
            );
        }
    }

    private static function merge_duplicate_records($records, $scope) {
        global $wpdb;

        if ($scope === 'student') {
            $groups = $wpdb->get_results(
                "SELECT MAX(id) AS keep_id, GROUP_CONCAT(id ORDER BY id DESC) AS record_ids
                 FROM {$records}
                 WHERE context_type = 'student' AND student_uid IS NOT NULL AND student_uid <> ''
                 GROUP BY template_id, student_uid, academic_year_id, semester_id, context_type, subject_id
                 HAVING COUNT(*) > 1"
            );
        } else {
            $groups = $wpdb->get_results(
                "SELECT MAX(id) AS keep_id, GROUP_CONCAT(id ORDER BY id DESC) AS record_ids
                 FROM {$records}
                 WHERE context_type <> 'student' AND related_entity_type IS NOT NULL AND related_entity_id IS NOT NULL
                 GROUP BY template_id, context_type, related_entity_type, related_entity_id
                 HAVING COUNT(*) > 1"
            );
        }

        foreach ((array) $groups as $group) {
            $keep_id = absint($group->keep_id);
            $ids = array_values(array_diff(array_map('absint', explode(',', (string) $group->record_ids)), array($keep_id)));
            foreach ($ids as $duplicate_id) {
                self::merge_record_scores($keep_id, $duplicate_id);
                $wpdb->delete($records, array('id' => $duplicate_id), array('%d'));
            }
        }
    }

    private static function merge_record_scores($keep_id, $duplicate_id) {
        global $wpdb;

        $scores = $wpdb->prefix . 'olama_ev_scores';
        if (!self::table_exists($scores)) {
            return;
        }

        $wpdb->query($wpdb->prepare(
            "DELETE duplicate_score FROM {$scores} duplicate_score
             INNER JOIN {$scores} kept_score
                ON kept_score.evaluation_id = %d
               AND kept_score.indicator_id = duplicate_score.indicator_id
             WHERE duplicate_score.evaluation_id = %d",
            $keep_id,
            $duplicate_id
        ));
        $wpdb->update($scores, array('evaluation_id' => $keep_id), array('evaluation_id' => $duplicate_id), array('%d'), array('%d'));
    }

    private static function table_exists($table) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    public static function table_names() {
        return array(
            'olama_ev_templates',
            'olama_ev_domains',
            'olama_ev_categories',
            'olama_ev_indicators',
            'olama_ev_records',
            'olama_ev_scores',
            'olama_attendance',
            'olama_attendance_sheets',
        );
    }
}
