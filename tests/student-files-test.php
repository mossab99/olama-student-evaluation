<?php
/**
 * Standalone SQL regression test: php tests/student-files-test.php
 * Requires PHP's PDO SQLite extension; no WordPress or live database needed.
 */

define('ABSPATH', __DIR__ . '/');

function __($text, $domain = '') { return $text; }
function absint($value) { return abs((int) $value); }

class WP_Error
{
    private $code;
    public function __construct($code, $message) { $this->code = $code; }
    public function get_error_code() { return $this->code; }
}

class Olama_School_Permissions
{
    public static $allowed = array('olama_access_evaluation', 'olama_view_student_files');
    public static function can($permission) { return in_array($permission, self::$allowed, true); }
}

class Olama_School_Academic_Bridge
{
    public static function get_study_year($id) {
        $years = array(1 => '2026/2027', 2 => '2025-2026', 3 => 'invalid');
        return $years[$id] ?? '';
    }
}

class Student_Files_Test_Read_Models
{
    public $unavailable = '';
    // Deliberately different names verify that the public contract is used.
    public function table($model) {
        $tables = array('students' => 'registry_people', 'families' => 'family_contacts', 'student_years' => 'year_enrollments');
        return $tables[$model];
    }
    public function available($model) { return $this->unavailable !== $model; }
}

class Student_Files_Test_Core
{
    public $models;
    public function __construct() { $this->models = new Student_Files_Test_Read_Models(); }
    public function read_models() { return $this->models; }
}

$test_core = new Student_Files_Test_Core();
function olama_core() { global $test_core; return $test_core; }

class Student_Files_Test_Wpdb
{
    public $prefix = 'wp_';
    public $last_error = '';
    public $queries = 0;
    public $pdo;
    public function __construct() {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    public function prepare($sql, ...$args) {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }
        $i = 0;
        return preg_replace_callback('/%[sd]/', function ($match) use (&$i, $args) {
            if (!array_key_exists($i, $args)) {
                throw new RuntimeException('Missing prepared SQL value.');
            }
            $value = $args[$i++];
            return $match[0] === '%d' ? (string) (int) $value : $this->pdo->quote((string) $value);
        }, $sql);
    }
    public function get_results($sql) {
        $this->queries++;
        $this->last_error = '';
        try {
            return $this->pdo->query($sql)->fetchAll(PDO::FETCH_OBJ);
        } catch (PDOException $exception) {
            $this->last_error = $exception->getMessage();
            return null;
        }
    }
    public function get_row($sql) {
        $rows = $this->get_results($sql);
        return $rows ? $rows[0] : null;
    }
    public function insert($table, array $values) {
        $columns = implode(', ', array_keys($values));
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->pdo->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})")->execute(array_values($values));
    }
}

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "PDO SQLite is required to run student-files-test.php.\n");
    exit(1);
}

$wpdb = new Student_Files_Test_Wpdb();
$wpdb->pdo->exec('CREATE TABLE registry_people (
    id INTEGER PRIMARY KEY AUTOINCREMENT, student_uid TEXT UNIQUE, student_name TEXT,
    family_uid TEXT, student_national_no TEXT, birth_place TEXT, birth_date TEXT,
    mother_mobile TEXT, student_status TEXT, student_status_name TEXT
);
CREATE TABLE family_contacts (family_uid TEXT PRIMARY KEY, father_mobile TEXT, mother_mobile TEXT);
CREATE TABLE year_enrollments (
    id INTEGER PRIMARY KEY AUTOINCREMENT, student_uid TEXT, study_year TEXT,
    class_id TEXT, section_id TEXT, class_name TEXT, section_name TEXT,
    student_status TEXT, student_status_name TEXT, school_id TEXT, school_name TEXT,
    last_synced_at TEXT, updated_at TEXT, created_at TEXT
);
CREATE TABLE wp_olama_grades (id INTEGER PRIMARY KEY, core_grade_id TEXT, grade_name TEXT);
CREATE TABLE wp_olama_sections (
    id INTEGER PRIMARY KEY, grade_id INTEGER, academic_year_id INTEGER,
    core_grade_id TEXT, core_section_id TEXT, core_study_year TEXT, section_name TEXT
);');

function add_person($uid, $name, $family = 'family-one', $status = '1', $status_name = 'active') {
    global $wpdb;
    $wpdb->insert('registry_people', array(
        'student_uid' => $uid, 'student_name' => $name, 'family_uid' => $family,
        'student_national_no' => '0012345678', 'birth_place' => 'Amman', 'birth_date' => '2015-04-09',
        'mother_mobile' => '0790000001', 'student_status' => $status, 'student_status_name' => $status_name,
    ));
}

function add_enrollment($uid, array $overrides = array()) {
    global $wpdb;
    $wpdb->insert('year_enrollments', array_merge(array(
        'student_uid' => $uid, 'study_year' => '2026-2027',
        'class_id' => 'G5', 'section_id' => 'A', 'class_name' => 'Grade Five', 'section_name' => 'A',
        'student_status' => '1', 'student_status_name' => 'active', 'school_id' => 'school-one', 'school_name' => 'Olama',
        'last_synced_at' => null, 'updated_at' => null, 'created_at' => null,
    ), $overrides));
}

function expect($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expect_error($result, $code) {
    expect($result instanceof WP_Error && $result->get_error_code() === $code, 'Expected error: ' . $code);
}

$wpdb->insert('wp_olama_grades', array('id' => 5, 'core_grade_id' => 'G5', 'grade_name' => 'Local Grade Five'));
$wpdb->insert('wp_olama_grades', array('id' => 6, 'core_grade_id' => 'G6', 'grade_name' => 'Local Grade Six'));
foreach (array(
    array(51, 5, 1, 'G5', 'A', '2026/2027'),
    array(52, 5, 1, 'G5', 'EMPTY', '2026-2027'),
    array(61, 6, 1, 'G6', 'A', '2026-2027'),
    array(41, 5, 2, 'G5', 'A', '2025-2026'),
) as $section) {
    $wpdb->insert('wp_olama_sections', array(
        'id' => $section[0], 'grade_id' => $section[1], 'academic_year_id' => $section[2],
        'core_grade_id' => $section[3], 'core_section_id' => $section[4], 'core_study_year' => $section[5], 'section_name' => 'Local A',
    ));
}
$wpdb->insert('family_contacts', array('family_uid' => 'family-one', 'father_mobile' => '0780000000', 'mother_mobile' => '  '));
$wpdb->insert('family_contacts', array('family_uid' => 'family-two', 'father_mobile' => '0770000000', 'mother_mobile' => '0799999999'));

add_person('A', 'Alice', 'family-one', 'disabled', 'withdrawn');
add_enrollment('A');
add_enrollment('A', array('study_year' => '2026/2027', 'student_status' => '0', 'section_id' => 'B', 'last_synced_at' => '2026-10-01 10:00:00'));
add_enrollment('A', array('study_year' => '2024-2025', 'class_name' => 'Old Three'));
add_enrollment('A', array('study_year' => '2024/2025', 'class_name' => 'Stale Three', 'last_synced_at' => '2026-10-01 10:00:00'));
add_enrollment('A', array('study_year' => '2025/2026', 'class_name' => 'Grade Four'));
add_enrollment('A', array('study_year' => '2027-2028', 'class_name' => 'Future Six'));
add_enrollment('A', array('study_year' => '2000-bad', 'class_name' => 'Malformed'));
add_person('B', 'Bob');
add_enrollment('B');
add_person('C', 'Casey', 'missing-family');
add_enrollment('C', array('student_status' => '', 'student_status_name' => null, 'class_name' => '', 'section_name' => null));
add_person('D', 'Drew', '');
add_enrollment('D');
add_person('blank-unrelated', 'Unknown Family', '');
add_enrollment('blank-unrelated', array('class_id' => 'G6'));
add_person('E', 'Excluded By Status Name', 'family-two');
add_enrollment('E', array('student_status_name' => ' withdrawn '));
add_person('F', 'Excluded Numeric Status', 'family-two');
add_enrollment('F', array('student_status' => '2'));
add_person('G', 'Excluded Registry Fallback', 'family-two', 'disabled');
add_enrollment('G', array('student_status' => ' ', 'student_status_name' => ''));
add_person('H', 'Same Name');
add_enrollment('H', array('class_id' => 'G6', 'student_status' => '0'));
add_enrollment('H', array('study_year' => '2026/2027', 'class_id' => 'G6', 'student_status' => '0', 'last_synced_at' => '2026-10-01 10:00:00'));
add_person('I', 'Same Name');
add_enrollment('I', array('class_id' => 'G6', 'created_at' => '2026-10-20 10:00:00'));
add_enrollment('I', array('study_year' => '2026/2027', 'class_id' => 'G6', 'school_id' => 'school-two', 'created_at' => '2026-10-01 10:00:00'));
add_person('future-only', 'Future Sibling');
add_enrollment('future-only', array('study_year' => '2027-2028'));
add_person('historical-only', 'Former Sibling');
add_enrollment('historical-only', array('study_year' => '2025-2026'));
add_person('withdrawn-sibling', 'Withdrawn Sibling');
add_enrollment('withdrawn-sibling', array('student_status_name' => 'منسحب'));
add_person('other-school', 'Other School Sibling');
add_enrollment('other-school', array('class_id' => 'G6', 'school_id' => 'school-two'));
add_person('unknown-school', 'Unknown School Sibling');
add_enrollment('unknown-school', array('class_id' => 'G6', 'school_id' => null, 'school_name' => null));
add_person('stale-active', 'Stale Active');
add_enrollment('stale-active');
add_enrollment('stale-active', array('study_year' => '2026/2027', 'last_synced_at' => '2026-10-01 10:00:00'));
add_person('moved-section', 'Moved Section');
add_enrollment('moved-section');
add_enrollment('moved-section', array('study_year' => '2026/2027', 'created_at' => '2026-10-01 10:00:00'));
add_person('registry-name', 'Registry Name Fallback', 'family-two', '1', 'stopped');
add_enrollment('registry-name', array('student_status_name' => ''));
add_person('R', 'Registry Overridden', 'family-two', '0', 'stopped');
add_enrollment('R');
add_person('tie-break', 'Timestamp Tie');
add_enrollment('tie-break');
add_enrollment('tie-break', array('study_year' => '2026/2027', 'student_status' => '0'));

// Reproduce Core updating lower-ID canonical rows after slash variants exist.
// These updates exercise timestamps for selected placement/status, history,
// sibling status/school, plus updated_at/created_at fallback and ID tie-breaks.
$wpdb->pdo->exec("UPDATE year_enrollments
    SET last_synced_at='2026-10-20 10:00:00'
    WHERE student_uid='A' AND study_year='2026-2027';
UPDATE year_enrollments
    SET class_name='Updated Three', last_synced_at='2026-10-20 10:00:00'
    WHERE student_uid='A' AND study_year='2024-2025';
UPDATE year_enrollments
    SET student_status='1', last_synced_at='2026-10-20 10:00:00'
    WHERE student_uid='H' AND study_year='2026-2027';
UPDATE year_enrollments
    SET student_status='0', last_synced_at='2026-10-20 10:00:00'
    WHERE student_uid='stale-active' AND study_year='2026-2027';
UPDATE year_enrollments
    SET section_id='B', updated_at='2026-10-20 10:00:00'
    WHERE student_uid='moved-section' AND study_year='2026-2027';");

require __DIR__ . '/../includes/class-student-files.php';

try {
    expect(Olama_Student_Evaluation_Student_Files::is_available(), 'All three read models are available.');
    foreach (array('students', 'families', 'student_years') as $model) {
        $test_core->models->unavailable = $model;
        expect(!Olama_Student_Evaluation_Student_Files::is_available(), 'Availability requires ' . $model);
        expect_error(Olama_Student_Evaluation_Student_Files::get_report(5, 51, 1), 'student_files_unavailable');
    }
    $test_core->models->unavailable = '';

    foreach (array('olama_access_evaluation', 'olama_view_student_files') as $required) {
        Olama_School_Permissions::$allowed = array_values(array_diff(array('olama_access_evaluation', 'olama_view_student_files'), array($required)));
        $query_count = $wpdb->queries;
        expect_error(Olama_Student_Evaluation_Student_Files::get_report(5, 51, 1), 'student_files_forbidden');
        expect($wpdb->queries === $query_count, 'Authorization must precede all report SQL.');
    }
    Olama_School_Permissions::$allowed = array('olama_access_evaluation', 'olama_view_student_files');
    expect_error(Olama_Student_Evaluation_Student_Files::get_report(0, 51, 1), 'student_files_invalid_filters');
    expect_error(Olama_Student_Evaluation_Student_Files::get_report(6, 51, 1), 'student_files_invalid_filters');
    expect_error(Olama_Student_Evaluation_Student_Files::get_report(5, 41, 1), 'student_files_invalid_filters');
    expect_error(Olama_Student_Evaluation_Student_Files::get_report(5, 51, 3), 'student_files_invalid_year');
    expect_error(Olama_Student_Evaluation_Student_Files::get_report(5, 51, 999), 'student_files_invalid_year');

    $query_count = $wpdb->queries;
    $rows = Olama_Student_Evaluation_Student_Files::get_report(5, 51, 1);
    expect(is_array($rows), 'The report must load successfully. SQL error: ' . $wpdb->last_error);
    expect($wpdb->queries - $query_count === 4, 'Group report uses four batch queries, regardless of student count.');
    $by_uid = array();
    foreach ($rows as $row) { $by_uid[$row->student_uid] = $row; }
    expect(array_keys($by_uid) === array('A', 'B', 'C', 'D', 'R'), 'Only selected-grade, selected-section, active students are returned.');
    expect(!isset($by_uid['tie-break']), 'Equal timestamp revisions use the greater ID as a deterministic tie-breaker.');
    $alice = $by_uid['A'];
    expect($alice->student_uid === 'A' && $alice->id > 0, 'Both the stable report ID and internal numeric ID are retained.');
    expect($alice->national_id === '0012345678' && $alice->birth_place === 'Amman' && $alice->birth_date === '2015-04-09', 'Personal fields retain original values and leading zeroes.');
    expect($alice->father_mobile === '0780000000' && $alice->mother_mobile === '0790000001', 'Family father mobile and student mother fallback are used.');
    expect($by_uid['R']->mother_mobile === '0799999999', 'Family mother mobile takes precedence.');
    expect($by_uid['C']->father_mobile === '' && $by_uid['C']->mother_mobile === '0790000001', 'Missing families preserve students and mother fallback.');
    expect($by_uid['C']->current_grade === 'Local Grade Five' && $by_uid['C']->current_section === 'Local A', 'Missing enrollment names use validated local labels.');
    expect($alice->current_grade === 'Grade Five' && $alice->current_section === 'A' && $alice->study_year === '2026-2027', 'Current placement uses the selected year and canonical year label.');
    expect(array_map(function ($row) { return $row->study_year; }, $alice->history) === array('2025-2026', '2024-2025'), 'History is newest-first, excludes current/future/malformed years and deduplicates year variants.');
    expect($alice->history[1]->grade_name === 'Updated Three', 'Canonical duplicate history uses the refreshed lower-ID row.');
    expect($alice->siblings === array('Bob', 'Moved Section', 'Same Name', 'Same Name', 'Unknown School Sibling'), 'Siblings are active in the selected year, same school where known, and equal names retain distinct UIDs.');
    expect($by_uid['D']->siblings === array(), 'Blank family IDs must not connect unrelated students.');
    expect($by_uid['C']->history === array() && $by_uid['C']->siblings === array(), 'Missing history/family yield empty lists.');
    expect(!property_exists($alice, 'family_uid') && !property_exists($alice, 'school_id'), 'Internal grouping details are not exposed in report objects.');

    $query_count = $wpdb->queries;
    expect(Olama_Student_Evaluation_Student_Files::get_report(5, 52, 1) === array(), 'An empty valid section returns an empty report.');
    expect($wpdb->queries - $query_count === 2, 'Empty reports skip history and sibling SQL.');

    $previous = Olama_Student_Evaluation_Student_Files::get_report(5, 41, 2);
    $previous_by_uid = array();
    foreach ($previous as $row) { $previous_by_uid[$row->student_uid] = $row; }
    expect(isset($previous_by_uid['A']) && $previous_by_uid['A']->study_year === '2025-2026', 'Reports can use an earlier selected academic year.');
    expect(count($previous_by_uid['A']->history) === 1 && $previous_by_uid['A']->history[0]->study_year === '2024-2025', 'History cutoff follows the selected year.');
    expect(in_array('Former Sibling', $previous_by_uid['A']->siblings, true) && !in_array('Bob', $previous_by_uid['A']->siblings, true), 'Sibling enrollment is scoped to the report year.');

    $wpdb->pdo->exec('DROP TABLE year_enrollments');
    expect_error(Olama_Student_Evaluation_Student_Files::get_report(5, 51, 1), 'student_files_query_failed');
    fwrite(STDOUT, "Student files SQL regression tests passed.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
