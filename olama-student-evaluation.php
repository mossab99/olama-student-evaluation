<?php
/**
 * Plugin Name: Olama Student Evaluation
 * Description: Standalone student evaluation, progress tracking, and evaluation structure management for Olama School.
 * Version: 1.1.1
 * Author: Olama
 * Text Domain: olama-student-evaluation
 * Requires Plugins: olama-school
 */

if (!defined('ABSPATH')) {
    exit;
}

define('OLAMA_STUDENT_EVALUATION_VERSION', '1.1.1');
define('OLAMA_STUDENT_EVALUATION_FILE', __FILE__);
define('OLAMA_STUDENT_EVALUATION_PATH', plugin_dir_path(__FILE__));
define('OLAMA_STUDENT_EVALUATION_URL', plugin_dir_url(__FILE__));

require_once OLAMA_STUDENT_EVALUATION_PATH . 'includes/class-db.php';
require_once OLAMA_STUDENT_EVALUATION_PATH . 'includes/EvaluationScoringService.php';
require_once OLAMA_STUDENT_EVALUATION_PATH . 'includes/class-ev-curriculum.php';
require_once OLAMA_STUDENT_EVALUATION_PATH . 'includes/class-ev-form.php';
require_once OLAMA_STUDENT_EVALUATION_PATH . 'includes/class-ev-manager.php';
require_once OLAMA_STUDENT_EVALUATION_PATH . 'includes/class-ev-record.php';
require_once OLAMA_STUDENT_EVALUATION_PATH . 'includes/class-ev-report.php';
require_once OLAMA_STUDENT_EVALUATION_PATH . 'includes/class-ev-template.php';
require_once OLAMA_STUDENT_EVALUATION_PATH . 'includes/trait-admin-methods.php';
require_once OLAMA_STUDENT_EVALUATION_PATH . 'includes/class-plugin.php';

register_activation_hook(__FILE__, array('Olama_Student_Evaluation_Plugin', 'activate'));
add_action('plugins_loaded', array('Olama_Student_Evaluation_Plugin', 'instance'), 20);
