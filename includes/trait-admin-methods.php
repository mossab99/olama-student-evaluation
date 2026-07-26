<?php

if (!defined('ABSPATH')) {
    exit;
}

trait Olama_Student_Evaluation_Admin_Methods
{
    function handle_kg_curriculum_actions()
        {
            $manager = new Olama_School_EV_Manager();
            $manager->handle_actions();
        }

    function handle_kg_evaluation_save()
        {
            $form = new Olama_School_EV_Form();
            $form->handle_save();
        }

    function ajax_kg_autosave()
        {
            $form = new Olama_School_EV_Form();
            $form->ajax_autosave();
        }

    function handle_kg_report_print()
        {
            if (isset($_GET['action']) && $_GET['action'] === 'ev_print_report' && isset($_GET['evaluation_id'])) {
                Olama_School_EV_Report::render_report(intval($_GET['evaluation_id']));
                exit;
            }
        }

    function render_evaluation_progress_page_content()
        {
            include OLAMA_STUDENT_EVALUATION_PATH . 'admin-views/ev-progress.php';
        }

    function ajax_get_ev_progress_students()
        {
            check_ajax_referer('olama_admin_nonce', 'nonce');

            $template_id = isset($_GET['template_id']) ? intval($_GET['template_id']) : 0;
            $section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;

            if (!$template_id || !$section_id) {
                wp_die('Invalid parameters.');
            }

            global $wpdb;
            $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olama_ev_templates WHERE id = %d", $template_id));
            if (!$template)
                wp_die('Template not found.');

            // Get all students in section
            $students = Olama_School_Student::get_students(array(
                'academic_year_id' => $template->academic_year_id,
                'section_id' => $section_id
            ));

            if (empty($students)) {
                echo '<p style="text-align:center; padding:20px;">' . Olama_School_Helpers::translate('No students found in this section.') . '</p>';
                wp_die();
            }

            // Get statuses and record IDs
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT s.id as student_id, r.status, r.id as record_id
                 FROM {$wpdb->prefix}olama_ev_records r
                 JOIN {$wpdb->prefix}olama_students s ON r.student_uid = s.student_uid
                 WHERE r.academic_year_id = %d AND r.semester_id = %d AND r.template_id = %d",
                $template->academic_year_id,
                $template->semester_id,
                $template_id
            ));

            $statuses = array();
            $statuses_ids = array();
            foreach ($results as $row) {
                $statuses[$row->student_id] = $row->status;
                $statuses_ids[$row->student_id] = $row->record_id;
            }

            foreach ($students as $student) {
                $status = $statuses[$student->id] ?? 'none';
                $status_label = Olama_School_Helpers::translate(ucfirst($status ?: 'none'));

                $status_class = $status === 'published' ? 'published' : ($status === 'draft' ? 'draft' : 'none');
                echo '<div class="ev-student-item" data-student-id="' . $student->id . '" data-template-id="' . $template_id . '">';
                echo '<div class="ev-student-info">';
                echo '<span class="ev-student-name">' . esc_html($student->student_name) . '</span>';
                echo '<span class="ev-student-uid">' . esc_html($student->student_uid) . '</span> ';
                echo '<span class="ev-status-badge status-' . $status_class . '">' . $status_label . '</span>';
                echo '</div>';

                echo '<div class="ev-student-actions" style="display:flex; gap:5px;">';
                if ($status === 'published') {
                    echo '<a href="' . admin_url('admin.php?action=ev_print_report&evaluation_id=' . ($statuses_ids[$student->id] ?? 0)) . '" target="_blank" class="button button-small v-publish-btn" title="' . Olama_School_Helpers::translate('Publish') . '">';
                    echo '<span class="dashicons dashicons-visibility" style="font-size:16px; width:16px; height:16px; margin-top:3px;"></span>';
                    echo '</a>';
                } elseif ($status === 'draft') {
                    echo '<button type="button" class="button button-small button-primary ev-approve-btn" data-student-id="' . $student->id . '" data-template-id="' . $template_id . '">';
                    echo Olama_School_Helpers::translate('Approve');
                    echo '</button>';
                }
                echo '</div>';
                echo '</div>';
            }
            wp_die();
        }

    function ajax_get_student_evaluation()
        {
            check_ajax_referer('olama_admin_nonce', 'nonce');

            $student_id = intval($_GET['student_id']);
            $template_id = intval($_GET['template_id']);

            global $wpdb;
            $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olama_ev_templates WHERE id = %d", $template_id));

            if (!$template)
                wp_die('Template not found');

            $record = Olama_School_EV_Record::get_evaluation($student_id, $template->academic_year_id, $template->semester_id, $template_id);

            if (!$record) {
                echo '<div style="text-align:center; padding:50px; color:#94a3b8;">' . Olama_School_Helpers::translate('No evaluation record found for this student.') . '</div>';
                wp_die();
            }

            // Display evaluation details matching ev-form.php design
            $curriculum = Olama_School_EV_Curriculum::get_full_curriculum($template_id);
            $scores = Olama_School_EV_Record::get_scores($record->id);
            $score_config = Olama_School_EV_Template::get_score_config($template_id);

            echo '<div class="ev-review-wrapper" style="direction: ' . (Olama_School_Helpers::is_arabic() ? 'rtl' : 'ltr') . ';">';
            echo '<div style="margin-bottom:30px; border-bottom:1px solid #e2e8f0; padding-bottom:20px; display: flex; justify-content: space-between; align-items: center;">';
            echo '<div>';
            echo '<h2 style="margin:0; font-size: 1.5em; color: #1e293b;">' . esc_html($template->template_name) . '</h2>';
            echo '<div style="margin-top:10px;"><span class="ev-status-badge status-' . $record->status . '">' . Olama_School_Helpers::translate(ucfirst($record->status)) . '</span></div>';
            echo '</div>';

            if ($record->status !== 'published') {
                echo '<div>';
                echo '<button type="button" class="button button-large button-primary ev-approve-btn" data-student-id="' . $student_id . '" data-template-id="' . $template_id . '">';
                echo '<span class="dashicons dashicons-yes" style="margin-top: 4px; margin-right: 5px;"></span> ';
                echo Olama_School_Helpers::translate('Approve');
                echo '</button>';
                echo '</div>';
            }
            echo '</div>';

            foreach ($curriculum as $domain) {
                echo '<div class="ev-domain-section" style="margin-bottom: 40px;">';
                echo '<div style="background: #1e293b; color: #fff; padding: 12px 25px; border-radius: 8px; margin-bottom: 20px;">';
                echo '<h3 style="margin: 0; color: #fff !important;">' . esc_html($domain->title_ar) . '</h3>';
                echo '</div>';

                foreach ($domain->categories as $category) {
                    echo '<div class="ev-category-container" style="margin-bottom: 25px; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">';
                    echo '<div style="background: #f8fafc; padding: 12px 20px; border-bottom: 1px solid #e2e8f0;">';
                    echo '<h4 style="margin: 0; color: #475569; font-weight: 600;">' . esc_html($category->title_ar) . '</h4>';
                    echo '</div>';

                    echo '<table style="width: 100%; border-collapse: collapse;">';
                    foreach ($category->indicators as $indicator) {
                        $score_row = $scores[$indicator->id] ?? null;
                        $score_val = $score_row ? $score_row->score : null;
                        $notes = $score_row ? $score_row->notes : '';

                        echo '<tr style="border-bottom: 1px solid #f1f5f9;">';
                        echo '<td style="padding: 15px 20px; width: 40%; font-size: 1.05em; vertical-align: middle;">' . esc_html($indicator->indicator_text) . '</td>';
                        echo '<td style="padding: 15px 20px; vertical-align: middle;">';

                        echo '<div class="ev-scoring-grid" style="display: flex; gap: 8px; justify-content: flex-end; flex-wrap: nowrap; align-items: center;">';

                        $total_levels = count($score_config);
                        $i = 0;
                        foreach ($score_config as $val => $label) {
                            $i++;
                            $is_active = ($score_val == $val);

                            // Dynamic color assignment matching ev-form.php
                            $color_class = 'not-mastered';
                            if ($i === 1)
                                $color_class = 'mastered';
                            elseif ($i === $total_levels)
                                $color_class = 'not-mastered';
                            elseif ($i === 2 && $total_levels > 2)
                                $color_class = 'partial';

                            echo '<div class="ev-score-option ' . $color_class . ' ' . ($is_active ? 'active' : '') . '" style="opacity: ' . ($score_val !== null && !$is_active ? '0.4' : '1') . ';">';
                            echo '<span class="ev-circle"></span>';
                            echo '<span class="ev-label">' . esc_html(Olama_School_Helpers::translate($label)) . '</span>';
                            echo '</div>';
                        }
                        echo '</div>';

                        if ($notes) {
                            echo '<div style="font-size:12px; color:#64748b; margin-top:10px; padding: 8px; background: #f8fafc; border-radius: 4px; border-right: 3px solid #6366f1;">';
                            echo '<strong>' . Olama_School_Helpers::translate('Note') . ':</strong> ' . esc_html($notes);
                            echo '</div>';
                        }

                        echo '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                    echo '</div>';
                }
                echo '</div>';
            }

            // Supervisor Comments Block
            echo '<div class="ev-supervisor-comments-block" style="margin-top: 40px; padding: 25px; background: #fdf2f2; border: 1px solid #fecaca; border-radius: 12px; border-right: 5px solid #ef4444;">';
            echo '<h3 style="margin: 0 0 15px 0; color: #991b1b; font-size: 1.25em;">' . Olama_School_Helpers::translate('Supervisor Comments') . '</h3>';
            echo '<textarea id="ev-supervisor-comments-text" style="width: 100%; min-height: 120px; border-radius: 8px; border: 1px solid #fecaca; padding: 15px; margin-bottom: 20px;" placeholder="' . Olama_School_Helpers::translate('Write comments for the teacher...') . '">' . esc_textarea($record->supervisor_comments ?? '') . '</textarea>';

            echo '<div style="display: flex; gap: 10px; justify-content: flex-end;">';
            echo '<button type="button" class="button button-large ev-save-comments-btn" data-student-id="' . $student_id . '" data-template-id="' . $template_id . '">';
            echo Olama_School_Helpers::translate('Save Comments');
            echo '</button>';

            if ($record->status !== 'published') {
                echo '<button type="button" class="button button-large button-primary ev-approve-btn" style="background: #ef4444; border-color: #ef4444;" data-student-id="' . $student_id . '" data-template-id="' . $template_id . '">';
                echo Olama_School_Helpers::translate('Approve');
                echo '</button>';
            }
            echo '</div>';
            echo '</div>';

            echo '</div>'; // End ev-review-wrapper

            wp_die();
        }

    function ajax_approve_evaluation()
        {
            check_ajax_referer('olama_admin_nonce', 'nonce');

            if (!current_user_can('manage_options') && !Olama_School_Permissions::can('olama_manage_evaluation_progress')) {
                wp_send_json_error('Unauthorized');
            }

            $student_id = intval($_POST['student_id']);
            $template_id = intval($_POST['template_id']);

            global $wpdb;
            $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olama_ev_templates WHERE id = %d", $template_id));
            if (!$template)
                wp_send_json_error('Template not found');

            $record = Olama_School_EV_Record::get_evaluation($student_id, $template->academic_year_id, $template->semester_id, $template_id);

            if ($record) {
                $update_data = array('status' => 'published');
                if (isset($_POST['supervisor_comments'])) {
                    $update_data['supervisor_comments'] = sanitize_textarea_field($_POST['supervisor_comments']);
                }

                $wpdb->update(
                    "{$wpdb->prefix}olama_ev_records",
                    $update_data,
                    array('id' => $record->id)
                );
                wp_send_json_success();
            } else {
                wp_send_json_error('Record not found');
            }
        }

    function ajax_save_supervisor_comments()
        {
            check_ajax_referer('olama_admin_nonce', 'nonce');

            if (!current_user_can('manage_options') && !Olama_School_Permissions::can('olama_manage_evaluation_progress')) {
                wp_send_json_error('Unauthorized');
            }

            $student_id = intval($_POST['student_id']);
            $template_id = intval($_POST['template_id']);
            $comments = sanitize_textarea_field($_POST['supervisor_comments']);

            global $wpdb;
            $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olama_ev_templates WHERE id = %d", $template_id));
            if (!$template)
                wp_send_json_error('Template not found');

            $record = Olama_School_EV_Record::get_evaluation($student_id, $template->academic_year_id, $template->semester_id, $template_id);

            if ($record) {
                $wpdb->update(
                    "{$wpdb->prefix}olama_ev_records",
                    array('supervisor_comments' => $comments),
                    array('id' => $record->id)
                );
                wp_send_json_success();
            } else {
                wp_send_json_error('Record not found');
            }
        }

    function ajax_bulk_approve_evaluations()
        {
            check_ajax_referer('olama_admin_nonce', 'nonce');

            if (!current_user_can('manage_options') && !Olama_School_Permissions::can('olama_manage_evaluation_progress')) {
                wp_send_json_error('Unauthorized');
            }

            $template_id = intval($_POST['template_id']);
            $section_id = intval($_POST['section_id']);

            global $wpdb;
            $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}olama_ev_templates WHERE id = %d", $template_id));
            if (!$template)
                wp_send_json_error('Template not found');

            // Get all draft evaluation records for this template and section
            $students = Olama_School_Student::get_students(array(
                'academic_year_id' => $template->academic_year_id,
                'section_id' => $section_id
            ));

            if (empty($students)) {
                wp_send_json_error('No students found');
            }

            $student_uids = wp_list_pluck($students, 'student_uid');
            $placeholders = implode(',', array_fill(0, count($student_uids), '%s'));

            $query = $wpdb->prepare(
                "UPDATE {$wpdb->prefix}olama_ev_records 
                 SET status = 'published' 
                 WHERE template_id = %d AND academic_year_id = %d AND semester_id = %d 
                 AND status = 'draft' AND student_uid IN ($placeholders)",
                array_merge(array($template_id, $template->academic_year_id, $template->semester_id), $student_uids)
            );

            $result = $wpdb->query($query);
            wp_send_json_success(array('count' => $result));
        }

    function render_evaluation_mgmt_page_content()
        {
            $manager = new Olama_School_EV_Manager();
            $manager->render_page();
        }

    function render_student_evaluation_page_content($context = null)
        {
            $form = new Olama_School_EV_Form();
            $form->render_page($context);
        }

    function handle_attendance_save()
        {
            if (isset($_POST['olama_save_bulk_attendance']) && check_admin_referer('olama_save_bulk_attendance')) {
                if (!Olama_School_Permissions::can('olama_manage_attendance')) {
                    wp_die(__('Unauthorized', 'olama-school'));
                }
                $date = Olama_School_Helpers::sanitize_date($_POST['attendance_date']);
                $section_id = intval($_POST['section_id']);
                $academic_year_id = intval($_POST['academic_year_id']);
                $semester_id = intval($_POST['semester_id']);
                $attendance_data = $_POST['attendance'] ?? array();

                global $wpdb;
                $table = $wpdb->prefix . 'olama_attendance';

                // Ensure table exists
                $table_sheets = $wpdb->prefix . 'olama_attendance_sheets';
                if ($wpdb->get_var("SHOW TABLES LIKE '$table_sheets'") !== $table_sheets) {
                    if (class_exists('Olama_Student_Evaluation_DB') && !olama_school_should_load_legacy_student_evaluation_module()) {
                        Olama_Student_Evaluation_DB::install();
                    } else {
                        Olama_Student_Evaluation_DB::install();
                    }
                }

                foreach ($attendance_data as $student_id => $data) {
                    $status = sanitize_text_field($data['status'] ?? 'present');
                    $reason = sanitize_text_field($data['reason'] ?? '');

                    // Fetch student_uid for stable linkage
                    $student_uid = $wpdb->get_var($wpdb->prepare(
                        "SELECT student_uid FROM {$wpdb->prefix}olama_students WHERE id = %d",
                        $student_id
                    ));

                    $res = $wpdb->query($wpdb->prepare(
                        "INSERT INTO $table (student_id, student_uid, academic_year_id, semester_id, section_id, attendance_date, status, reason, recorded_by)
                        VALUES (%d, %s, %d, %d, %d, %s, %s, %s, %d)
                        ON DUPLICATE KEY UPDATE status = %s, student_uid = %s, reason = %s, recorded_by = %d",
                        $student_id,
                        $student_uid,
                        $academic_year_id,
                        $semester_id,
                        $section_id,
                        $date,
                        $status,
                        $reason,
                        get_current_user_id(),
                        $status,
                        $student_uid,
                        $reason,
                        get_current_user_id()
                    ));

                    if ($res === false) {
                        error_log("Olama Attendance: Failed to save attendance for student $student_id on date $date. DB Error: " . $wpdb->last_error);
                    }
                }

                // Also mark the attendance sheet as completed for this section/date
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO {$wpdb->prefix}olama_attendance_sheets (academic_year_id, section_id, attendance_date, recorded_by, status)
                    VALUES (%d, %d, %s, %d, 'completed')
                    ON DUPLICATE KEY UPDATE recorded_by = %d, status = 'completed'",
                    $academic_year_id,
                    $section_id,
                    $date,
                    get_current_user_id(),
                    get_current_user_id()
                ));

                wp_redirect(add_query_arg('message', 'attendance_saved', wp_get_referer()));
                exit;
            }
        }

    function ajax_save_attendance()
        {
            check_ajax_referer('olama_admin_nonce', 'nonce');

            if (!Olama_School_Permissions::can('olama_manage_attendance')) {
                wp_send_json_error(__('Unauthorized', 'olama-school'));
            }

            $student_id = intval($_POST['student_id']);
            $status = sanitize_text_field($_POST['status']);
            $date = Olama_School_Helpers::sanitize_date($_POST['date']);
            $section_id = intval($_POST['section_id'] ?? 0);
            $academic_year_id = intval($_POST['academic_year_id'] ?? 0);
            $semester_id = intval($_POST['semester_id'] ?? 0);

            if (!$student_id || !$date) {
                wp_send_json_error('Missing parameters');
            }

            // Auto-fetch active parameters if not provided
            if (!$academic_year_id || !$semester_id || !$section_id) {
                $active_year = Olama_School_Academic::get_active_year();
                $academic_year_id = $academic_year_id ?: ($active_year ? $active_year->id : 0);
                $active_sem = Olama_School_Academic::get_active_semester($academic_year_id);
                $semester_id = $semester_id ?: ($active_sem ? $active_sem->id : 0);

                if (!$section_id) {
                    $enrollment = Olama_School_Student::get_student_enrollment($student_id, $academic_year_id);
                    $section_id = $enrollment ? $enrollment->section_id : 0;
                }
            }

            global $wpdb;
            $table = $wpdb->prefix . 'olama_attendance';

            // Check if table exists
            $table_sheets = $wpdb->prefix . 'olama_attendance_sheets';
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_sheets'") !== $table_sheets) {
                if (class_exists('Olama_Student_Evaluation_DB') && !olama_school_should_load_legacy_student_evaluation_module()) {
                    Olama_Student_Evaluation_DB::install();
                } else {
                    Olama_Student_Evaluation_DB::install();
                }
            }

            // Fetch student_uid for stable linkage
            $student_uid = $wpdb->get_var($wpdb->prepare(
                "SELECT student_uid FROM {$wpdb->prefix}olama_students WHERE id = %d",
                $student_id
            ));

            $result = $wpdb->query($wpdb->prepare(
                "INSERT INTO $table (student_id, student_uid, academic_year_id, semester_id, section_id, attendance_date, status, recorded_by)
                VALUES (%d, %s, %d, %d, %d, %s, %s, %d)
                ON DUPLICATE KEY UPDATE status = %s, student_uid = %s, recorded_by = %d",
                $student_id,
                $student_uid,
                $academic_year_id,
                $semester_id,
                $section_id,
                $date,
                $status,
                get_current_user_id(),
                $status,
                $student_uid,
                get_current_user_id()
            ));

            if ($result !== false) {
                // Also mark the attendance sheet as completed for this section/date
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO {$wpdb->prefix}olama_attendance_sheets (academic_year_id, section_id, attendance_date, recorded_by, status)
                    VALUES (%d, %d, %s, %d, 'completed')
                    ON DUPLICATE KEY UPDATE recorded_by = %d, status = 'completed'",
                    $academic_year_id,
                    $section_id,
                    $date,
                    get_current_user_id(),
                    get_current_user_id()
                ));

                wp_send_json_success();
            } else {
                error_log("Olama Attendance AJAX: Failed to save attendance for student $student_id on date $date. DB Error: " . $wpdb->last_error);
                wp_send_json_error(__('Database error', 'olama-school'));
            }
        }

    function ajax_mark_all_present()
        {
            check_ajax_referer('olama_admin_nonce', 'nonce');

            if (!Olama_School_Permissions::can('olama_manage_attendance')) {
                wp_send_json_error(__('Unauthorized', 'olama-school'));
            }

            $section_id = intval($_POST['section_id'] ?? 0);
            $date = Olama_School_Helpers::sanitize_date($_POST['date']);
            $academic_year_id = intval($_POST['academic_year_id'] ?? 0);

            if (!$section_id || !$date) {
                wp_send_json_error('Missing parameters');
            }

            if (!$academic_year_id) {
                $active_year = Olama_School_Academic::get_active_year();
                $academic_year_id = $active_year ? $active_year->id : 0;
            }

            global $wpdb;
            $table = $wpdb->prefix . 'olama_attendance';

            // 1. Get all students in this section
            $students = $wpdb->get_results($wpdb->prepare(
                "SELECT s.id, s.student_uid 
                 FROM {$wpdb->prefix}olama_student_enrollment e
                 JOIN {$wpdb->prefix}olama_students s ON e.student_uid = s.student_uid
                 WHERE e.section_id = %d AND e.academic_year_id = %d AND e.status = 'active'",
                $section_id,
                $academic_year_id
            ));

            // 2. Clear existing attendance records for this section/date (so we reset to All Present)
            $wpdb->delete($table, array(
                'section_id' => $section_id,
                'attendance_date' => $date
            ));

            // Note: In this system, "present" seems to be the default state if no record exists.
            // However, we want to record that the sheet IS completed.
            
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}olama_attendance_sheets (academic_year_id, section_id, attendance_date, recorded_by, status)
                VALUES (%d, %d, %s, %d, 'completed')
                ON DUPLICATE KEY UPDATE recorded_by = %d, status = 'completed'",
                $academic_year_id,
                $section_id,
                $date,
                get_current_user_id(),
                get_current_user_id()
            ));

            wp_send_json_success();
        }
}
