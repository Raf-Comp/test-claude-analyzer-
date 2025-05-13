<?php
// Jeśli odinstalowanie nie zostało wywołane z WordPressa, zakończ
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Usuń opcje wtyczki
delete_option('gca_settings');
delete_option('gca_claude_api_key');
delete_option('gca_github_token');
delete_option('gca_recent_errors');
delete_option('gca_js_errors');
delete_option('gca_php_errors');

// Usuń transients
delete_transient('gca_available_claude_models');

// Usuń wszystkie zadania analizy
global $wpdb;
$tasks = $wpdb->get_results("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'gca_task_%'");
if ($tasks) {
    foreach ($tasks as $task) {
        delete_option($task->option_name);
    }
}

// Usuń zaplanowane zadania
wp_clear_scheduled_hook('gca_process_analysis_task');

// Usuń niestandardowe tabele
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}claude_api_keys");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}claude_logs");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}claude_processed_files");