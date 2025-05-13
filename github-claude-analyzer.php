<?php
/**
 * Plugin Name: GitHub Claude Analyzer
 * Plugin URI: https://example.com/github-claude-analyzer
 * Description: Analizuj repozytoria GitHub przy użyciu Claude AI oraz prowadź interaktywne rozmowy na tematy programistyczne.
 * Version: 2.0.0
 * Author: Twoje Imię
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: github-claude-analyzer
 * Domain Path: /languages
 */

// Jeśli ten plik jest wywołany bezpośrednio, przerwij.
if (!defined('WPINC')) {
    die;
}

// Definiuj stałe wtyczki
define('GCA_VERSION', '2.0.0');
define('GCA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GCA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GCA_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('GCA_LOG_CHAT_MESSAGES', false); // Włącz lub wyłącz logowanie wiadomości czatu

// Załaduj dodatkowe klasy
require_once GCA_PLUGIN_DIR . 'includes/class-github-claude-analyzer.php';
require_once GCA_PLUGIN_DIR . 'includes/class-activator.php';
require_once GCA_PLUGIN_DIR . 'includes/class-deactivator.php';

/**
 * Kod uruchamiany podczas aktywacji wtyczki.
 */
function activate_github_claude_analyzer() {
    GitHub_Claude_Analyzer_Activator::activate();
}

/**
 * Kod uruchamiany podczas dezaktywacji wtyczki.
 */
function deactivate_github_claude_analyzer() {
    GitHub_Claude_Analyzer_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_github_claude_analyzer');
register_deactivation_hook(__FILE__, 'deactivate_github_claude_analyzer');

/**
 * Rozpoczyna wykonanie wtyczki.
 */
function run_github_claude_analyzer() {
    $plugin = new GitHub_Claude_Analyzer();
    $plugin->run();
}

run_github_claude_analyzer();