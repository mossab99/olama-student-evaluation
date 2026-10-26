<?php
/**
 * Standalone page/admin regression tests: php tests/test-student-files-page.php
 * WordPress and school/data APIs are fixtures; no database or generated files.
 * Require this file from another CLI script and call student_files_page_fixture()
 * plus student_files_page_capture() to render the real report view for a preview.
 */

define('ABSPATH', __DIR__ . '/');
define('OLAMA_STUDENT_EVALUATION_PATH', dirname(__DIR__) . '/');
define('OLAMA_STUDENT_EVALUATION_URL', 'https://example.test/plugin/');
define('OLAMA_STUDENT_EVALUATION_VERSION', 'test');

function __($text, $domain = '') { return $text; }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function esc_attr($value) { return esc_html($value); }
function esc_url($value) { return esc_html($value); }
function esc_html__($text, $domain = '') { return esc_html(__($text, $domain)); }
function esc_html_e($text, $domain = '') { echo esc_html__($text, $domain); }
function absint($value) { return abs((int) $value); }
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : stripslashes((string) $value); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function admin_url($path = '') { return 'https://example.test/wp-admin/' . $path; }
function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); }
function selected($actual, $expected, $echo = true) {
    $result = (string) $actual === (string) $expected ? ' selected="selected"' : '';
    if ($echo) { echo $result; }
    return $result;
}
function disabled($actual, $expected = true, $echo = true) {
    $result = (string) $actual === (string) $expected ? ' disabled="disabled"' : '';
    if ($echo) { echo $result; }
    return $result;
}
function add_action($hook, $callback, $priority = 10) { }
function wp_enqueue_style($handle, ...$args) { $GLOBALS['student_files_page_assets']['styles'][$handle] = $args; }
function wp_enqueue_script($handle, ...$args) { $GLOBALS['student_files_page_assets']['scripts'][$handle] = $args; }
function current_time($format) { return '2026-10-26'; }
function wp_die($message, $title = '', $args = array()) { throw new Student_Files_Page_Denied($message, $args['response'] ?? 0); }
function is_wp_error($value) { return $value instanceof WP_Error; }

class Student_Files_Page_Denied extends RuntimeException { }
class WP_Error {
    public function __construct($code, $message) { }
}

// Deliberately load and call the label before the optional School helper exists.
require_once OLAMA_STUDENT_EVALUATION_PATH . 'includes/class-student-files-page.php';
try {
    $student_files_dependency_label = Olama_Student_Evaluation_Student_Files_Page::label('Student Files');
} catch (Throwable $exception) {
    $student_files_dependency_label = $exception;
}

if (!class_exists('Olama_School_Helpers')) {
    class Olama_School_Helpers {
        public static $arabic = false;
        public static function is_arabic() { return self::$arabic; }
        public static function translate($text) { return $text; }
        public static function sanitize_date($text) { return $text; }
    }
}
class Olama_School_Permissions {
    public static $allowed = array();
    public static function can($capability) { return in_array($capability, self::$allowed, true); }
}
class Olama_School_Academic {
    public static $active_year;
    public static $calls = 0;
    public static function get_active_year() { self::$calls++; return self::$active_year; }
    public static function get_active_semester($year) { return null; }
}
class Olama_School_Grade {
    public static $grades = array();
    public static $calls = 0;
    public static function get_grades() { self::$calls++; return self::$grades; }
}
class Olama_School_Section {
    public static $sections = array();
    public static $calls = array();
    public static function get_by_grade($grade, $year) {
        self::$calls[] = array($grade, $year);
        return self::$sections[$grade] ?? array();
    }
}
class Olama_Student_Evaluation_Student_Files {
    public static $available = true;
    public static $availability_calls = 0;
    public static $report_calls = array();
    public static $report = array();
    public static function is_available() { self::$availability_calls++; return self::$available; }
    public static function get_report($grade, $section, $year) {
        self::$report_calls[] = array($grade, $section, $year);
        return self::$report;
    }
}
class Olama_School_Admin {
    public static $asset_hooks = array();
    public function enqueue_admin_assets($hook) { self::$asset_hooks[] = $hook; }
}
trait Olama_Student_Evaluation_Admin_Methods {
    public function render_student_evaluation_page_content() { echo '<p>original-evaluation-content</p>'; }
    public function render_evaluation_progress_page_content() { echo '<p>original-progress-content</p>'; }
    public function render_evaluation_mgmt_page_content() { echo '<p>original-management-content</p>'; }
}

require_once OLAMA_STUDENT_EVALUATION_PATH . 'includes/class-admin.php';

/** Reset fixtures and return an admin controller suitable for rendering. */
function student_files_page_fixture(array $options = array()) {
    $_GET = $options['get'] ?? array('page' => 'olama-student-evaluation', 'tab' => 'student_files', 'grade_id' => '7', 'section_id' => '71');
    Olama_School_Helpers::$arabic = $options['arabic'] ?? false;
    Olama_School_Permissions::$allowed = $options['allowed'] ?? array('olama_access_evaluation', 'olama_view_student_files');
    Olama_School_Academic::$active_year = array_key_exists('year', $options) ? $options['year'] : (object) array('id' => 26, 'year_name' => '2026-2027');
    Olama_School_Academic::$calls = 0;
    Olama_School_Grade::$grades = array((object) array('id' => 7, 'grade_name' => 'Grade 7 <advanced>'), (object) array('id' => 8, 'grade_name' => 'Grade 8'));
    Olama_School_Grade::$calls = 0;
    Olama_School_Section::$sections = array(
        7 => array((object) array('id' => 71, 'section_name' => 'A & B')),
        8 => array((object) array('id' => 81, 'section_name' => 'C')),
    );
    Olama_School_Section::$calls = array();
    Olama_Student_Evaluation_Student_Files::$available = $options['available'] ?? true;
    Olama_Student_Evaluation_Student_Files::$availability_calls = 0;
    Olama_Student_Evaluation_Student_Files::$report_calls = array();
    Olama_Student_Evaluation_Student_Files::$report = $options['report'] ?? array((object) array(
        'student_uid' => 'S-0007',
        'student_name' => 'Student <script>alert("name")</script>',
        'national_id' => '0001234567',
        'birth_place' => 'Amman <img src=x onerror=alert(1)>',
        'birth_date' => '2014-02-03',
        'father_mobile' => '+962 <father>',
        'mother_mobile' => '079" onclick="alert(1)',
        'family_address' => "Amman <address>\nBuilding & 12",
        'current_grade' => 'Grade 7 <advanced>',
        'current_section' => 'A & B',
        'study_year' => '2026-2027',
        'history' => array(
            (object) array('grade_name' => 'Grade 6', 'section_name' => 'C <previous>', 'study_year' => '2025-2026'),
            (object) array('grade_name' => 'Grade 5', 'section_name' => 'D', 'study_year' => '2024-2025'),
        ),
        'siblings' => array('Sibling <b>one</b>', 'Sibling & two'),
        'family_uid' => 'FAMILY-SECRET-SENTINEL',
        'oracle_student_id' => 'ORACLE-SECRET-SENTINEL',
    ));
    $GLOBALS['student_files_page_assets'] = array('styles' => array(), 'scripts' => array());
    Olama_School_Admin::$asset_hooks = array();
    return new Olama_Student_Evaluation_Admin();
}

function student_files_page_capture(callable $callback) {
    ob_start();
    try {
        $callback();
        return ob_get_contents();
    } finally {
        ob_end_clean();
    }
}

function student_files_page_check($condition, $description) {
    if (!$condition) { throw new RuntimeException($description); }
    $GLOBALS['student_files_page_assertions']++;
}
function student_files_page_contains($needle, $haystack, $description) { student_files_page_check(strpos($haystack, $needle) !== false, $description); }
function student_files_page_absent($needle, $haystack, $description) { student_files_page_check(strpos($haystack, $needle) === false, $description); }

function student_files_page_tests() {
    $GLOBALS['student_files_page_assertions'] = 0;
    student_files_page_check($GLOBALS['student_files_dependency_label'] === 'Student Files', 'Tab label must remain usable before School helpers are loaded.');

    foreach (array(array(), array('olama_access_evaluation'), array('olama_view_student_files')) as $permissions) {
        student_files_page_fixture(array('allowed' => $permissions));
        try {
            student_files_page_capture(array('Olama_Student_Evaluation_Student_Files_Page', 'render'));
            throw new RuntimeException('Unauthorized student files page was rendered.');
        } catch (Student_Files_Page_Denied $exception) {
            student_files_page_check($exception->getCode() === 403, 'Unauthorized report responds with HTTP 403.');
        }
        student_files_page_check(Olama_Student_Evaluation_Student_Files::$availability_calls === 0 && Olama_School_Academic::$calls === 0 && !Olama_Student_Evaluation_Student_Files::$report_calls, 'Denied requests must stop before any school/report data calls.');
    }

    student_files_page_fixture(array('available' => false));
    $html = student_files_page_capture(array('Olama_Student_Evaluation_Student_Files_Page', 'render'));
    student_files_page_contains('Student files are unavailable.', $html, 'Missing Core data has a useful unavailable notice.');
    student_files_page_check(!Olama_School_Grade::$calls && !Olama_School_Section::$calls && !Olama_Student_Evaluation_Student_Files::$report_calls, 'Unavailable Core must not query roster or filters.');
    student_files_page_absent('<table', $html, 'Unavailable report does not present a false empty roster.');

    student_files_page_fixture(array('year' => null));
    $html = student_files_page_capture(array('Olama_Student_Evaluation_Student_Files_Page', 'render'));
    student_files_page_contains('Set an active academic year', $html, 'Missing academic year has a useful setup notice.');
    student_files_page_check(!Olama_School_Grade::$calls && !Olama_School_Section::$calls && !Olama_Student_Evaluation_Student_Files::$report_calls, 'Missing active year must not query roster or filters.');

    $invalid_filters = array(
        array('grade_id' => array('7'), 'section_id' => '71'),
        array('grade_id' => '7', 'section_id' => array('71')),
        array('grade_id' => 'bogus', 'section_id' => '71'),
        array('grade_id' => '999', 'section_id' => '71'),
        array('grade_id' => '8', 'section_id' => '71'),
        array('grade_id' => '7', 'section_id' => '81'),
    );
    foreach ($invalid_filters as $filters) {
        student_files_page_fixture(array('get' => array_merge(array('page' => 'olama-student-evaluation', 'tab' => 'student_files'), $filters)));
        $html = student_files_page_capture(array('Olama_Student_Evaluation_Student_Files_Page', 'render'));
        student_files_page_check(!Olama_Student_Evaluation_Student_Files::$report_calls, 'Malformed/mismatched filters must not query a report: ' . json_encode($filters));
        student_files_page_contains('Select a grade and section to view student files.', $html, 'Invalid filters show the selection prompt.');
        student_files_page_absent('<table', $html, 'Invalid filters cannot leak a different section roster.');
    }

    student_files_page_fixture();
    $html = student_files_page_capture(array('Olama_Student_Evaluation_Student_Files_Page', 'render'));
    student_files_page_check(Olama_Student_Evaluation_Student_Files::$report_calls === array(array(7, 71, 26)), 'Valid filters pass grade, section and active year to the report API.');
    student_files_page_check(Olama_School_Section::$calls === array(array(7, 26)), 'Section choices are scoped to grade and active academic year.');
    student_files_page_contains('dir="ltr"', $html, 'English report uses left-to-right layout.');
    foreach (array('Student ID', 'Student Name', 'National ID', 'Place of Birth', 'Date of Birth', 'Father Mobile', 'Mother Mobile', 'Family Address', 'Current Grade / Section', 'Previous Grades / Sections', 'Siblings in School') as $heading) {
        student_files_page_contains('<th scope="col">' . $heading . '</th>', $html, 'Report includes the column ' . $heading);
    }
    student_files_page_check(substr_count($html, '<th scope="col">') === 11, 'Report includes the family address as the eleventh column.');
    foreach (array('S-0007', '0001234567', '03/02/2014', '2026-2027', '2025-2026', '2024-2025') as $value) {
        student_files_page_contains($value, $html, 'Report renders expected student/date/year value ' . $value);
    }
    student_files_page_check(strpos($html, 'Grade 6') < strpos($html, 'Grade 5') && strpos($html, '2025-2026') < strpos($html, '2024-2025'), 'Previous placements retain chronological order with corresponding school years.');
    foreach (array('Student <script>alert("name")</script>', 'Amman <img src=x onerror=alert(1)>', '+962 <father>', '079" onclick="alert(1)', "Amman <address>\nBuilding & 12", 'Grade 7 <advanced>', 'A & B', 'C <previous>', 'Sibling <b>one</b>', 'Sibling & two') as $value) {
        student_files_page_contains(esc_html($value), $html, 'Database value is escaped: ' . $value);
        student_files_page_absent($value, $html, 'Unescaped database value must not enter markup: ' . $value);
    }
    student_files_page_absent('FAMILY-SECRET-SENTINEL', $html, 'Family identifiers must not be rendered with sibling names.');
    student_files_page_absent('ORACLE-SECRET-SENTINEL', $html, 'Displayed student ID is the globally unique student UID.');
    student_files_page_contains('student-files-print', $html, 'Populated report offers printing.');

    student_files_page_fixture(array('arabic' => true));
    $html = student_files_page_capture(array('Olama_Student_Evaluation_Student_Files_Page', 'render'));
    student_files_page_contains('dir="rtl"', $html, 'Arabic report uses right-to-left layout.');
    foreach (array('ملفات الطلاب', 'رقم الطالب', 'اسم الطالب', 'الرقم الوطني', 'مكان الولادة', 'تاريخ الولادة', 'جوال الأب', 'جوال الأم', 'عنوان العائلة', 'الصف / الشعبة الحالية', 'الصفوف / الشعب السابقة', 'الإخوة في المدرسة') as $label) {
        student_files_page_contains($label, $html, 'Arabic label is rendered: ' . $label);
    }

    student_files_page_fixture();
    $student = Olama_Student_Evaluation_Student_Files::$report[0];
    foreach (array('national_id', 'birth_place', 'father_mobile', 'mother_mobile', 'family_address') as $field) { $student->$field = ''; }
    $student->history = array();
    $student->siblings = array();
    foreach (array('0000-00-00', '2014-02-30', '2014-02-03<script>', '') as $date) {
        $student->birth_date = $date;
        $html = student_files_page_capture(array('Olama_Student_Evaluation_Student_Files_Page', 'render'));
        student_files_page_check(substr_count($html, 'Not recorded') >= 6, 'Missing fields and invalid birth dates have explicit placeholders.');
        student_files_page_contains('<td class="student-files-address">Not recorded</td>', $html, 'Missing family address has an explicit placeholder.');
        student_files_page_contains('No previous enrollment recorded.', $html, 'Missing history has an explicit placeholder.');
        student_files_page_contains('None recorded', $html, 'Missing sibling names have an explicit placeholder.');
        student_files_page_absent('03/02/2014', $html, 'Malformed birth dates cannot display an invented valid date.');
    }

    student_files_page_fixture(array('report' => array()));
    $html = student_files_page_capture(array('Olama_Student_Evaluation_Student_Files_Page', 'render'));
    student_files_page_contains('No students found in this section.', $html, 'Empty section has an empty roster message.');
    student_files_page_absent('Unable to load student files.', $html, 'Empty roster is not a query failure.');
    student_files_page_absent('class="button student-files-print"', $html, 'Empty roster does not offer a useless print action.');

    student_files_page_fixture(array('report' => new WP_Error('database_error', 'Fixture query failure')));
    $html = student_files_page_capture(array('Olama_Student_Evaluation_Student_Files_Page', 'render'));
    student_files_page_contains('Unable to load student files.', $html, 'Query failure has a distinct error notice.');
    student_files_page_absent('No students found in this section.', $html, 'Query failure must not masquerade as empty roster.');
    student_files_page_absent('Fixture query failure', $html, 'Database error internals are not exposed.');

    $admin = student_files_page_fixture(array('allowed' => array('olama_access_evaluation', 'olama_manage_evaluation_students')));
    $html = student_files_page_capture(array($admin, 'render_page'));
    student_files_page_contains('original-evaluation-content', $html, 'Forbidden student files tab falls back to the permitted original tab.');
    student_files_page_absent('tab=student_files', $html, 'Student files navigation is hidden without its capability.');
    student_files_page_check(Olama_Student_Evaluation_Student_Files::$availability_calls === 0, 'Forbidden tab never invokes student files renderer.');

    $admin = student_files_page_fixture(array('get' => array('page' => 'olama-student-evaluation')));
    $html = student_files_page_capture(array($admin, 'render_page'));
    student_files_page_contains('tab=student_files', $html, 'Permitted student files tab appears in navigation.');
    student_files_page_contains('class="olama-student-files"', $html, 'Student files renders when it is the only permitted/default tab.');
    $admin->enqueue_assets('toplevel_page_olama-student-evaluation');
    student_files_page_check(isset($GLOBALS['student_files_page_assets']['styles']['olama-student-files']) && isset($GLOBALS['student_files_page_assets']['scripts']['olama-student-files']), 'Default student files tab loads its style and print/filter script without explicit tab parameter.');
    student_files_page_check(isset($GLOBALS['student_files_page_assets']['scripts']['olama-student-evaluation-admin']), 'Original admin script still loads.');
    student_files_page_check(Olama_School_Admin::$asset_hooks === array('olama-school_student-evaluation'), 'Original parent admin assets are retained.');

    $admin = student_files_page_fixture(array('get' => array('page' => 'olama-student-evaluation', 'tab' => array('student_files'))));
    $html = student_files_page_capture(array($admin, 'render_page'));
    student_files_page_contains('class="olama-student-files"', $html, 'Malformed tab falls back safely to the only allowed tab.');
    $admin->enqueue_assets('toplevel_page_olama-student-evaluation');
    student_files_page_check(isset($GLOBALS['student_files_page_assets']['styles']['olama-student-files']), 'Asset selection follows the same safe fallback as page selection.');

    $original_tabs = array(
        'student_evaluation' => array('olama_manage_evaluation_students', 'original-evaluation-content'),
        'evaluation_progress' => array('olama_manage_evaluation_progress', 'original-progress-content'),
        'evaluation_mgmt' => array('olama_manage_evaluation_mgmt', 'original-management-content'),
        'student_attendance' => array('olama_manage_attendance', 'attendance-filters'),
    );
    foreach ($original_tabs as $tab => $settings) {
        $admin = student_files_page_fixture(array(
            'get' => array('page' => 'olama-student-evaluation', 'tab' => $tab),
            'year' => null,
            'allowed' => array('olama_access_evaluation', $settings[0], 'olama_view_student_files'),
        ));
        $html = student_files_page_capture(array($admin, 'render_page'));
        student_files_page_contains($settings[1], $html, 'Existing ' . $tab . ' tab still renders.');
        student_files_page_check(Olama_Student_Evaluation_Student_Files::$availability_calls === 0, 'Existing tabs do not load student files data.');
        $admin->enqueue_assets('toplevel_page_olama-student-evaluation');
        student_files_page_check(!isset($GLOBALS['student_files_page_assets']['styles']['olama-student-files']), 'Other tabs do not load student files styles.');
    }

    foreach (array(array(), array('olama_access_evaluation'), array('olama_view_student_files')) as $permissions) {
        $admin = student_files_page_fixture(array('allowed' => $permissions));
        try {
            student_files_page_capture(array($admin, 'render_page'));
            throw new RuntimeException('Admin controller allowed entry without access and an allowed tab.');
        } catch (Student_Files_Page_Denied $exception) {
            student_files_page_check($exception->getCode() === 403, 'Admin controller enforces menu and tab permissions.');
        }
        student_files_page_check(!Olama_Student_Evaluation_Student_Files::$availability_calls, 'Denied admin page does not load student files data.');
    }

    $admin = student_files_page_fixture(array('get' => array('page' => 'other-plugin', 'tab' => 'student_files')));
    $admin->enqueue_assets('other-plugin');
    student_files_page_check(!$GLOBALS['student_files_page_assets']['styles'] && !$GLOBALS['student_files_page_assets']['scripts'] && !Olama_School_Admin::$asset_hooks, 'Unrelated admin pages load no evaluation assets.');

    echo 'Student files page/admin tests passed (' . $GLOBALS['student_files_page_assertions'] . " assertions).\n";
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    set_error_handler(static function ($severity, $message, $file, $line) {
        if (error_reporting() & $severity) { throw new ErrorException($message, 0, $severity, $file, $line); }
        return false;
    });
    try {
        student_files_page_tests();
    } finally {
        restore_error_handler();
    }
}
