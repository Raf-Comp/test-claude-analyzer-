<?php
/**
 * Główna klasa wtyczki.
 */
class GitHub_Claude_Analyzer {

    /**
     * Obiekt administratora wtyczki.
     */
    protected $admin;

    /**
     * Obiekt publicznej strony wtyczki.
     */
    protected $public;
    
    /**
     * Obiekt optymalizatora.
     */
    protected $optimizer;
    
    /**
     * Obiekt obsługi czatu.
     */
    protected $chat_handler;

    /**
     * Definiuje główną funkcjonalność wtyczki.
     */
    public function __construct() {
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Ładuje wymagane zależności dla tej wtyczki.
     */
    private function load_dependencies() {
        // Podstawowe klasy
        require_once GCA_PLUGIN_DIR . 'admin/class-admin.php';
        require_once GCA_PLUGIN_DIR . 'public/class-public.php';
        require_once GCA_PLUGIN_DIR . 'includes/class-claude-api.php';
        require_once GCA_PLUGIN_DIR . 'includes/class-github-api.php';
        
        // Nowe klasy
        require_once GCA_PLUGIN_DIR . 'includes/class-optimizer.php';
        require_once GCA_PLUGIN_DIR . 'includes/class-chat-handler.php';
        require_once GCA_PLUGIN_DIR . 'includes/class-i18n.php';
    }
    
    /**
     * Definiuje obsługę wielojęzyczności.
     */
    private function set_locale() {
        $i18n = new GitHub_Claude_Analyzer_i18n();
        add_action('plugins_loaded', array($i18n, 'load_plugin_textdomain'));
    }

    /**
     * Rejestruje wszystkie hooki związane z obszarem administracyjnym.
     */
    private function define_admin_hooks() {
        $this->admin = new GitHub_Claude_Analyzer_Admin();
    }

    /**
     * Rejestruje wszystkie hooki związane z publiczną stroną witryny.
     */
    private function define_public_hooks() {
        $this->public = new GitHub_Claude_Analyzer_Public();
        
        // Inicjalizuj optymalizator i obsługę czatu
        $this->optimizer = new GitHub_Claude_Optimizer();
        $this->chat_handler = new GitHub_Claude_Chat_Handler();
        
        // Dodaj obsługę zadań w tle
        add_action('gca_process_analysis_task', array($this->public, 'process_analysis_task'));
    }

    /**
     * Uruchamia wtyczkę.
     */
    public function run() {
        $this->admin->run();
        $this->public->run();
    }
}