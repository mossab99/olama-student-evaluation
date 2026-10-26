<?php

if (!defined('ABSPATH')) {
    exit;
}

class Olama_Student_Evaluation_Student_Files_Page {
    public static function label($text) {
        if (!class_exists('Olama_School_Helpers') || !Olama_School_Helpers::is_arabic()) {
            return __($text, 'olama-student-evaluation');
        }

        static $labels = array(
            'Student Files' => 'ملفات الطلاب',
            'View Student Files' => 'عرض ملفات الطلاب',
            'Student details, school enrollment history, and siblings by grade and section.' => 'بيانات الطلاب وسجل الصفوف والشعب والإخوة في المدرسة حسب الصف والشعبة.',
            'Academic Year' => 'العام الدراسي',
            'Grade' => 'الصف',
            'Section' => 'الشعبة',
            'Select Grade' => 'اختر الصف',
            'Select Section' => 'اختر الشعبة',
            'View Report' => 'عرض التقرير',
            'Print Report' => 'طباعة التقرير',
            'Student ID' => 'رقم الطالب',
            'Student Name' => 'اسم الطالب',
            'National ID' => 'الرقم الوطني',
            'Place of Birth' => 'مكان الولادة',
            'Date of Birth' => 'تاريخ الولادة',
            'Father Mobile' => 'جوال الأب',
            'Mother Mobile' => 'جوال الأم',
            'Family Address' => 'عنوان العائلة',
            'Current Grade / Section' => 'الصف / الشعبة الحالية',
            'Previous Grades / Sections' => 'الصفوف / الشعب السابقة',
            'Siblings in School' => 'الإخوة في المدرسة',
            'Student count' => 'عدد الطلاب',
            'Not recorded' => 'غير مسجل',
            'None recorded' => 'لا يوجد مسجل',
            'No previous enrollment recorded.' => 'لا يوجد سجل التحاق سابق.',
            'No students found in this section.' => 'لا يوجد طلاب في هذه الشعبة.',
            'Select a grade and section to view student files.' => 'اختر الصف والشعبة لعرض ملفات الطلاب.',
            'Set an active academic year in Olama Core before viewing student files.' => 'حدد عاماً دراسياً فعالاً في نظام علماء الأساسي قبل عرض ملفات الطلاب.',
            'Student files are unavailable. Check that Olama Core student, family, and enrollment data is installed.' => 'ملفات الطلاب غير متاحة. تحقق من توفر بيانات الطلاب والعائلات والالتحاق في نظام علماء الأساسي.',
            'Previous placements reflect imported school years. Siblings include students enrolled in the current school year.' => 'يعرض السجل السابق الأعوام الدراسية المستوردة. تشمل قائمة الإخوة الطلاب الملتحقين في العام الدراسي الحالي.',
            'You do not have permission to view student files.' => 'ليس لديك صلاحية لعرض ملفات الطلاب.',
            'Unable to load student files. Please try again.' => 'تعذر تحميل ملفات الطلاب. يرجى المحاولة مرة أخرى.',
            'Student files report' => 'تقرير ملفات الطلاب',
        );

        return $labels[$text] ?? Olama_School_Helpers::translate($text);
    }

    public static function render() {
        if (!Olama_School_Permissions::can('olama_access_evaluation')
            || !Olama_School_Permissions::can('olama_view_student_files')) {
            wp_die(esc_html(self::label('You do not have permission to view student files.')), '', array('response' => 403));
        }

        $available = Olama_Student_Evaluation_Student_Files::is_available();
        $active_year = Olama_School_Academic::get_active_year();
        $grades = $available && $active_year ? Olama_School_Grade::get_grades() : array();
        $grade_id = isset($_GET['grade_id']) && is_scalar($_GET['grade_id']) ? absint($_GET['grade_id']) : 0;
        $section_id = isset($_GET['section_id']) && is_scalar($_GET['section_id']) ? absint($_GET['section_id']) : 0;
        $selected_grade = null;
        $selected_section = null;

        foreach ($grades as $grade) {
            if ((int) $grade->id === $grade_id) {
                $selected_grade = $grade;
                break;
            }
        }
        if (!$selected_grade) {
            $grade_id = 0;
        }

        $sections = $grade_id ? Olama_School_Section::get_by_grade($grade_id, $active_year->id) : array();
        foreach ($sections as $section) {
            if ((int) $section->id === $section_id) {
                $selected_section = $section;
                break;
            }
        }
        if (!$selected_section) {
            $section_id = 0;
        }

        $students = array();
        $report_error = false;
        if ($grade_id && $section_id) {
            $result = Olama_Student_Evaluation_Student_Files::get_report($grade_id, $section_id, $active_year->id);
            if (is_wp_error($result)) {
                $report_error = true;
            } else {
                $students = $result;
            }
        }

        $label = array(__CLASS__, 'label');
        $display_value = static function ($value) use ($label) {
            return null === $value || '' === trim((string) $value) ? $label('Not recorded') : (string) $value;
        };
        $placement = static function ($grade, $section) use ($display_value) {
            return $display_value($grade) . ' / ' . $display_value($section);
        };
        $direction = Olama_School_Helpers::is_arabic() ? 'rtl' : 'ltr';
        include OLAMA_STUDENT_EVALUATION_PATH . 'admin-views/student-files.php';
    }
}
