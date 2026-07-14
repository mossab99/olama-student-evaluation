<?php

if (!defined('ABSPATH')) {
    exit;
}

class Olama_Student_Evaluation_Admin extends Olama_School_Admin {
    public function __construct() {
        add_action('admin_menu', array($this, 'register_menu'), 30);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_init', array($this, 'redirect_legacy_page'), 1);
        add_action('admin_init', array($this, 'handle_kg_curriculum_actions'));
        add_action('admin_init', array($this, 'handle_kg_evaluation_save'));
        add_action('admin_init', array($this, 'handle_kg_report_print'));
        add_action('init', array($this, 'handle_attendance_save'));
        add_action('wp_ajax_olama_kg_autosave', array($this, 'ajax_kg_autosave'));
        add_action('wp_ajax_olama_get_ev_progress_students', array($this, 'ajax_get_ev_progress_students'));
        add_action('wp_ajax_olama_get_student_evaluation', array($this, 'ajax_get_student_evaluation'));
        add_action('wp_ajax_olama_approve_evaluation', array($this, 'ajax_approve_evaluation'));
        add_action('wp_ajax_olama_save_supervisor_comments', array($this, 'ajax_save_supervisor_comments'));
        add_action('wp_ajax_olama_bulk_approve_evaluations', array($this, 'ajax_bulk_approve_evaluations'));
        add_action('wp_ajax_olama_save_attendance', array($this, 'ajax_save_attendance'));
        add_action('wp_ajax_olama_mark_all_present', array($this, 'ajax_mark_all_present'));
    }

    public function register_menu() {
        add_menu_page(
            __('Student Evaluation', 'olama-student-evaluation'),
            __('Student Evaluation', 'olama-student-evaluation'),
            'olama_access_evaluation',
            'olama-student-evaluation',
            array($this, 'render_page'),
            'dashicons-star-filled',
            27
        );
        add_submenu_page(
            'olama-student-evaluation',
            __('Student Evaluation', 'olama-student-evaluation'),
            __('Evaluation', 'olama-student-evaluation'),
            'olama_access_evaluation',
            'olama-student-evaluation',
            array($this, 'render_page')
        );
    }

    public function redirect_legacy_page() {
        if (empty($_GET['page'])) {
            return;
        }

        $args = map_deep(wp_unslash($_GET), 'sanitize_text_field');
        $page = sanitize_key($args['page'] ?? '');
        if ('olama-school-follow-up' === $page && 'student_attendance' === ($args['tab'] ?? '')) {
            $args['page'] = 'olama-student-evaluation';
            wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
            exit;
        }
        if ('olama-school-evaluation' !== $page) {
            return;
        }
        if (($args['tab'] ?? '') === 'lesson_planner') {
            $args['page'] = 'olama-school-supervision';
        } else {
            $args['page'] = 'olama-student-evaluation';
        }
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    public function enqueue_assets($hook) {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ('olama-student-evaluation' !== $page) {
            return;
        }
        parent::enqueue_admin_assets('olama-school_student-evaluation');
    }

    public function render_page() {
        if (!Olama_School_Permissions::can('olama_access_evaluation')) {
            wp_die(esc_html__('You do not have permission to access Student Evaluation.', 'olama-student-evaluation'), '', array('response' => 403));
        }

        $tabs_config = array(
            'student_evaluation' => array('label' => Olama_School_Helpers::translate('Student Evaluation'), 'cap' => 'olama_manage_evaluation_students'),
            'evaluation_progress' => array('label' => Olama_School_Helpers::translate('Evaluation Progress'), 'cap' => 'olama_manage_evaluation_progress'),
            'evaluation_mgmt' => array('label' => Olama_School_Helpers::translate('Evaluation Management'), 'cap' => 'olama_manage_evaluation_mgmt'),
            'student_attendance' => array('label' => Olama_School_Helpers::translate('Student Attendance'), 'cap' => 'olama_manage_attendance'),
        );
        $allowed_tabs = array();
        foreach ($tabs_config as $id => $tab) {
            if (Olama_School_Permissions::can($tab['cap'])) {
                $allowed_tabs[$id] = $tab;
            }
        }
        if (!$allowed_tabs) {
            wp_die(esc_html__('You do not have access to an evaluation section.', 'olama-student-evaluation'), '', array('response' => 403));
        }

        $active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : array_key_first($allowed_tabs);
        if (!isset($allowed_tabs[$active_tab])) {
            $active_tab = array_key_first($allowed_tabs);
        }
        ?>
        <div class="wrap olama-school-wrap">
            <h1><?php esc_html_e('Student Evaluation', 'olama-student-evaluation'); ?></h1>
            <h2 class="nav-tab-wrapper">
                <?php foreach ($allowed_tabs as $tab_slug => $tab_data): ?>
                    <a href="<?php echo esc_url(add_query_arg(array('page' => 'olama-student-evaluation', 'tab' => $tab_slug), admin_url('admin.php'))); ?>"
                       class="nav-tab <?php echo $active_tab === $tab_slug ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($tab_data['label']); ?>
                    </a>
                <?php endforeach; ?>
            </h2>
            <div class="olama-tab-content" style="margin-top:20px;">
                <?php
                if ('student_attendance' === $active_tab) {
                    include OLAMA_STUDENT_EVALUATION_PATH . 'admin-views/attendance.php';
                } elseif ('evaluation_mgmt' === $active_tab) {
                    $this->render_evaluation_mgmt_page_content();
                } elseif ('evaluation_progress' === $active_tab) {
                    $this->render_evaluation_progress_page_content();
                } else {
                    $this->render_student_evaluation_page_content();
                }
                ?>
            </div>
        </div>
        <?php
    }
}
