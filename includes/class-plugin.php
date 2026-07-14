<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Olama_Student_Evaluation_Plugin {
    private static $instance = null;
    private $available = false;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function activate() {
        if (!class_exists('Olama_School_Admin') || !class_exists('Olama_School_Permissions')) {
            deactivate_plugins(plugin_basename(OLAMA_STUDENT_EVALUATION_FILE));
            wp_die(
                esc_html__('Olama Student Evaluation requires Olama School to be installed and active.', 'olama-student-evaluation'),
                esc_html__('Plugin dependency missing', 'olama-student-evaluation'),
                array('back_link' => true)
            );
        }

        Olama_Student_Evaluation_DB::install();
        self::add_capabilities();
        update_option('olama_student_evaluation_db_version', OLAMA_STUDENT_EVALUATION_VERSION);
    }

    private function __construct() {
        load_plugin_textdomain('olama-student-evaluation', false, dirname(plugin_basename(OLAMA_STUDENT_EVALUATION_FILE)) . '/languages');
        add_action('admin_notices', array($this, 'dependency_notice'));
        add_filter('olama_core_capability_groups', array($this, 'register_capability_group'), 30);
        add_filter('olama_dashboard_cards', array($this, 'register_hub_card'), 20);

        $this->available = $this->dependencies_available();
        if (!$this->available) {
            return;
        }

        require_once OLAMA_STUDENT_EVALUATION_PATH . 'includes/class-admin.php';
        if (is_admin()) {
            new Olama_Student_Evaluation_Admin();
            add_action('admin_init', array($this, 'maybe_update_schema'), 5);
        }
    }

    private function dependencies_available() {
        return defined('OLAMA_SCHOOL_FILE')
            && class_exists('Olama_School_Admin')
            && class_exists('Olama_School_Permissions')
            && class_exists('Olama_School_EV_Manager')
            && class_exists('Olama_School_EV_Form')
            && class_exists('Olama_School_EV_Record');
    }

    public function maybe_update_schema() {
        if (get_option('olama_student_evaluation_db_version') === OLAMA_STUDENT_EVALUATION_VERSION) {
            return;
        }
        Olama_Student_Evaluation_DB::install();
        self::add_capabilities();
        update_option('olama_student_evaluation_db_version', OLAMA_STUDENT_EVALUATION_VERSION);
    }

    public static function add_capabilities() {
        $manager_caps = array(
            'olama_access_evaluation',
            'olama_manage_evaluation_students',
            'olama_manage_evaluation_progress',
            'olama_manage_evaluation_mgmt',
            'olama_manage_attendance',
        );
        $teacher_caps = array(
            'olama_access_evaluation',
            'olama_manage_evaluation_students',
            'olama_manage_evaluation_progress',
            'olama_manage_attendance',
        );

        foreach (array('administrator', 'editor', 'supervisor') as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($manager_caps as $cap) {
                    $role->add_cap($cap);
                }
            }
        }
        foreach (array('author', 'teacher', 'assistant') as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($teacher_caps as $cap) {
                    $role->add_cap($cap);
                }
            }
        }
    }

    public function register_capability_group($groups) {
        $groups['evaluation'] = array(
            'label' => __('Student Evaluation', 'olama-student-evaluation'),
            'caps' => array(
                'olama_access_evaluation' => __('Access Student Evaluation', 'olama-student-evaluation'),
                'olama_manage_evaluation_students' => __('Evaluate Students', 'olama-student-evaluation'),
                'olama_manage_evaluation_progress' => __('Manage Evaluation Progress', 'olama-student-evaluation'),
                'olama_manage_evaluation_mgmt' => __('Manage Evaluation Structures', 'olama-student-evaluation'),
                'olama_manage_attendance' => __('Manage Student Attendance', 'olama-student-evaluation'),
            ),
        );
        return $groups;
    }

    public function register_hub_card($cards) {
        if (!$this->available) {
            return $cards;
        }

        foreach ($cards as &$card) {
            if (($card['id'] ?? '') === 'olama-school' && !empty($card['submenus'])) {
                $card['submenus'] = array_values(array_filter($card['submenus'], function ($submenu) {
                    return ($submenu['id'] ?? '') !== 'school.evaluation';
                }));
            }
        }
        unset($card);

        $cards[] = array(
            'id' => 'olama-student-evaluation',
            'label' => __('Student Evaluation', 'olama-student-evaluation'),
            'description' => __('Evaluate students, track progress, manage evaluation structures, and record attendance.', 'olama-student-evaluation'),
            'icon' => 'dashicons-star-filled',
            'accent' => '#2563eb',
            'accent_rgb' => '37,99,235',
            'active' => true,
            'capability' => 'olama_access_evaluation',
            'primary_url' => admin_url('admin.php?page=olama-student-evaluation'),
            'submenus' => array(
                array('id' => 'student-evaluation.evaluate', 'label' => __('Student Evaluation', 'olama-student-evaluation'), 'icon' => 'dashicons-star-filled', 'url' => admin_url('admin.php?page=olama-student-evaluation&tab=student_evaluation'), 'capability' => 'olama_manage_evaluation_students', 'color' => '#2563eb'),
                array('id' => 'student-evaluation.progress', 'label' => __('Evaluation Progress', 'olama-student-evaluation'), 'icon' => 'dashicons-chart-bar', 'url' => admin_url('admin.php?page=olama-student-evaluation&tab=evaluation_progress'), 'capability' => 'olama_manage_evaluation_progress', 'color' => '#2563eb'),
                array('id' => 'student-evaluation.manage', 'label' => __('Evaluation Management', 'olama-student-evaluation'), 'icon' => 'dashicons-admin-generic', 'url' => admin_url('admin.php?page=olama-student-evaluation&tab=evaluation_mgmt'), 'capability' => 'olama_manage_evaluation_mgmt', 'color' => '#2563eb'),
                array('id' => 'student-evaluation.attendance', 'label' => __('Student Attendance', 'olama-student-evaluation'), 'icon' => 'dashicons-yes-alt', 'url' => admin_url('admin.php?page=olama-student-evaluation&tab=student_attendance'), 'capability' => 'olama_manage_attendance', 'color' => '#2563eb'),
            ),
        );

        return $cards;
    }

    public function dependency_notice() {
        if ($this->available || !current_user_can('activate_plugins')) {
            return;
        }
        echo '<div class="notice notice-error"><p>'
            . esc_html__('Olama Student Evaluation is inactive because Olama School is not active.', 'olama-student-evaluation')
            . '</p></div>';
    }
}
