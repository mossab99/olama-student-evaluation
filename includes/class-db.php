<?php

if (!defined('ABSPATH')) {
    exit;
}

class Olama_Student_Evaluation_DB {
    public static function install() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

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
            student_id mediumint(9) DEFAULT NULL,
            student_uid varchar(50) DEFAULT NULL,
            subject_id mediumint(9) DEFAULT NULL,
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
            KEY context_type (context_type)
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
            KEY indicator_id (indicator_id)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$wpdb->prefix}olama_attendance (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            student_id mediumint(9) NOT NULL,
            student_uid varchar(50) DEFAULT NULL,
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
