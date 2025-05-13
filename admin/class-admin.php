<?php
/**
 * Klasa obsługująca administracyjną część wtyczki.
 */
class GitHub_Claude_Analyzer_Admin {

    /**
     * Konstruktor.
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Uruchamia funkcjonalność administratora.
     */
    public function run() {
        // Obsługa formularza API kluczy
        add_action('admin_post_save_claude_api_key', array($this, 'save_claude_api_key'));
        
        // Obsługa testowania połączeń
        add_action('wp_ajax_test_claude_connection', array($this, 'test_claude_connection'));
        
        // Dodaj obsługę odświeżania modeli Claude
        add_action('wp_ajax_refresh_claude_models', array($this, 'refresh_claude_models'));
        
        // Dodaj obsługę weryfikacji tokenu GitHub
        add_action('wp_ajax_verify_github_token', array($this, 'ajax_verify_github_token'));
        
        // Dodaj obsługę diagnostyki wtyczki
        add_action('wp_ajax_gca_verify_plugin', array($this, 'verify_plugin'));
        
        // Dodaj obsługę naprawy bazy danych
        add_action('wp_ajax_gca_repair_database', array($this, 'repair_database'));
    }

    /**
     * Dodaje strony menu do panelu administratora.
     */
    public function add_admin_menu() {
        add_menu_page(
            'GitHub Claude Analyzer',
            'GitHub Claude',
            'manage_options',
            'github-claude-analyzer',
            array($this, 'display_admin_page'),
            'dashicons-code-standards',
            100
        );

        add_submenu_page(
            'github-claude-analyzer',
            'Ustawienia',
            'Ustawienia',
            'manage_options',
            'github-claude-analyzer-settings',
            array($this, 'display_settings_page')
        );
        
        add_submenu_page(
            'github-claude-analyzer',
            'Diagnostyka',
            'Diagnostyka',
            'manage_options',
            'github-claude-analyzer-diagnostics',
            array($this, 'display_diagnostics_page')
        );
    }

    /**
     * Rejestruje ustawienia wtyczki.
     */
    public function register_settings() {
        register_setting(
            'gca_settings_group',              // Grupa opcji
            'gca_settings',                    // Nazwa opcji
            array($this, 'sanitize_settings')  // Funkcja sanityzacji
        );
    }

    /**
     * Sanityzuje ustawienia przed zapisaniem.
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Sanityzuj max_tokens
        if (isset($input['max_tokens'])) {
            $sanitized['max_tokens'] = (int)$input['max_tokens'];
            // Upewnij się, że wartość jest w dozwolonym zakresie
            if ($sanitized['max_tokens'] < 1 || $sanitized['max_tokens'] > 100000) {
                $sanitized['max_tokens'] = 4000; // Domyślna wartość
                add_settings_error(
                    'gca_settings',
                    'invalid_max_tokens',
                    'Nieprawidłowa wartość dla maksymalnej liczby tokenów. Użyto wartości domyślnej (4000).',
                    'error'
                );
            }
        } else {
            $sanitized['max_tokens'] = 4000; // Domyślna wartość
        }
        
        // Sanityzuj max_files_per_analysis
        if (isset($input['max_files_per_analysis'])) {
            $sanitized['max_files_per_analysis'] = (int)$input['max_files_per_analysis'];
            if ($sanitized['max_files_per_analysis'] < 1 || $sanitized['max_files_per_analysis'] > 150) {
                $sanitized['max_files_per_analysis'] = 30; // Domyślna wartość
            }
        } else {
            $sanitized['max_files_per_analysis'] = 30; // Domyślna wartość
        }
        
        // Sanityzuj model Claude
        if (isset($input['claude_model'])) {
            $sanitized['claude_model'] = sanitize_text_field($input['claude_model']);
            
            // Sprawdź, czy model jest dostępny
            if (class_exists('GitHub_Claude_API')) {
                $claude_api = new GitHub_Claude_API();
                $available_models = $claude_api->get_available_models();
                
                if (!empty($available_models) && !in_array($sanitized['claude_model'], $available_models)) {
                    // Jeśli model nie jest dostępny, wybierz domyślny
                    if (in_array('claude-3-7-sonnet-20250219', $available_models)) {
                        $sanitized['claude_model'] = 'claude-3-7-sonnet-20250219';
                    } elseif (in_array('claude-3-5-sonnet-20240620', $available_models)) {
                        $sanitized['claude_model'] = 'claude-3-5-sonnet-20240620';
                    } elseif (!empty($available_models)) {
                        $sanitized['claude_model'] = $available_models[0];
                    }
                    
                    add_settings_error(
                        'gca_settings',
                        'invalid_model',
                        'Wybrany model Claude nie jest dostępny. Użyto modelu: ' . $sanitized['claude_model'],
                        'error'
                    );
                }
            }
        } else {
            $sanitized['claude_model'] = 'claude-3-7-sonnet-20250219'; // Zmieniono domyślny model
        }
        
        // Sanityzuj default_extensions
        if (isset($input['default_extensions']) && is_array($input['default_extensions'])) {
            $sanitized['default_extensions'] = array_map('sanitize_text_field', $input['default_extensions']);
        } else {
            $sanitized['default_extensions'] = array('php', 'js', 'css', 'html'); // Domyślne wartości
        }
        
        return $sanitized;
    }

    /**
     * Dołącza style administratora.
     */
    public function enqueue_styles($hook) {
        if (strpos($hook, 'github-claude-analyzer') !== false) {
            wp_enqueue_style('gca-admin', GCA_PLUGIN_URL . 'admin/css/admin.css', array(), GCA_VERSION);
            
            // Dodaj dodatkowe style inline
            wp_add_inline_style('gca-admin', '
                /* Style dla panelu statusu kluczy */
                .gca-key-status-panel {
                    margin-bottom: 20px;
                }
                
                .gca-notice {
                    padding: 12px 16px;
                    border-radius: 4px;
                    margin-bottom: 10px;
                }
                
                .gca-notice-success {
                    background-color: #d1fae5;
                    border: 1px solid #a7f3d0;
                    color: #065f46;
                }
                
                .gca-notice-warning {
                    background-color: #fef3c7;
                    border: 1px solid #fde68a;
                    color: #92400e;
                }
                
                .gca-notice-error {
                    background-color: #fee2e2;
                    border: 1px solid #fca5a5;
                    color: #b91c1c;
                }
                
                .gca-notice .dashicons {
                    font-size: 20px;
                    margin-right: 10px;
                }
            ');
        }
    }

    /**
     * Dołącza skrypty administratora.
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'github-claude-analyzer') !== false) {
            wp_enqueue_script('gca-admin', GCA_PLUGIN_URL . 'admin/js/admin.js', array('jquery'), GCA_VERSION, true);
            
            wp_localize_script('gca-admin', 'gca_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gca_ajax_nonce'),
                'version' => GCA_VERSION,
                'plugin_url' => GCA_PLUGIN_URL
            ));
        }
    }

    /**
     * Wyświetla główną stronę administratora.
     */
    public function display_admin_page() {
        $template_path = GCA_PLUGIN_DIR . 'admin/partials/admin-display.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            // Fallback, jeśli plik nie istnieje
            echo '<div class="wrap">';
            echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
            echo '<div class="notice notice-error"><p>Nie znaleziono szablonu <code>admin-display.php</code>. Sprawdź, czy wszystkie pliki wtyczki zostały prawidłowo załadowane.</p></div>';
            echo '</div>';
        }
    }

    /**
     * Wyświetla stronę ustawień.
     */
    public function display_settings_page() {
        // Pobierz ustawienia i dane konta
        $settings = get_option('gca_settings', array());
        
        // Pobierz API key i GitHub token dla bieżącego użytkownika
        global $wpdb;
        $table_name = $wpdb->prefix . 'claude_api_keys';
        $user_id = get_current_user_id();
        
        $api_key_data = $wpdb->get_row(
            $wpdb->prepare("SELECT api_key, github_token, model FROM $table_name WHERE user_id = %d", $user_id),
            ARRAY_A
        );
        
        // Jeśli istnieją dane API, sprawdź stan konta
        $account_info = null;
        $connection_error = null;
        
        if ($api_key_data && !empty($api_key_data['api_key'])) {
            try {
                $claude_api = new GitHub_Claude_API($api_key_data['api_key']);
                $account_response = $claude_api->get_account_info();
                
                if (isset($account_response['error'])) {
                    $connection_error = $account_response['error'];
                } else {
                    $account_info = $account_response;
                }
            } catch (Exception $e) {
                $connection_error = $e->getMessage();
            }
        }
        
        // Sprawdź status tokenów GitHub
        $github_token_status = [
            'valid' => false,
            'message' => 'Brak tokenu GitHub',
            'details' => null
        ];
        
        if ($api_key_data && !empty($api_key_data['github_token'])) {
            try {
                if (!class_exists('GitHub_API')) {
                    require_once GCA_PLUGIN_DIR . 'includes/class-github-api.php';
                }
                
                $github_api = new GitHub_API($api_key_data['github_token']);
                $github_token_status = $github_api->verify_token();
            } catch (Exception $e) {
                $github_token_status['message'] = 'Błąd weryfikacji tokenu GitHub: ' . $e->getMessage();
            }
        }
        
        include GCA_PLUGIN_DIR . 'admin/partials/settings-display.php';
    }
    
    /**
     * Wyświetla stronę diagnostyki.
     */
    public function display_diagnostics_page() {
        // Wykonaj podstawowe testy
        $tests_results = $this->run_diagnostics();
        
        // Sprawdź tabele bazy danych
        $database_status = $this->check_database_tables();
        
        // Sprawdź uprawnienia plików
        $files_permissions = $this->check_file_permissions();
        
        // Sprawdź API Claude
        $claude_api_status = $this->verify_claude_api_key();
        
        // Sprawdź API GitHub
        $github_api_status = $this->verify_github_token();
        
        include GCA_PLUGIN_DIR . 'admin/partials/diagnostics-display.php';
    }
    
    /**
     * Przeprowadza testy diagnostyczne.
     */
    private function run_diagnostics() {
        $results = [
            'system' => [
                'php_version' => phpversion(),
                'wordpress_version' => get_bloginfo('version'),
                'plugin_version' => GCA_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'post_max_size' => ini_get('post_max_size'),
                'upload_max_filesize' => ini_get('upload_max_filesize')
            ],
            'files' => [],
            'database' => [],
            'api' => []
        ];
        
        return $results;
    }
    
    /**
     * Sprawdza tabele bazy danych.
     */
    private function check_database_tables() {
        global $wpdb;
        $tables_to_check = [
            $wpdb->prefix . 'claude_api_keys',
            $wpdb->prefix . 'claude_logs',
            $wpdb->prefix . 'claude_processed_files'
        ];
        
        $results = [];
        
        foreach ($tables_to_check as $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
            
            if ($exists) {
                try {
                    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
                    $results[$table] = [
                        'exists' => true,
                        'records' => (int)$count,
                        'error' => null
                    ];
                } catch (Exception $e) {
                    $results[$table] = [
                        'exists' => true,
                        'records' => 0,
                        'error' => $e->getMessage()
                    ];
                }
            } else {
                $results[$table] = [
                    'exists' => false,
                    'records' => 0,
                    'error' => 'Tabela nie istnieje'
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Sprawdza uprawnienia plików.
     */
    private function check_file_permissions() {
        $files_to_check = [
            GCA_PLUGIN_DIR . 'includes/class-claude-api.php',
            GCA_PLUGIN_DIR . 'includes/class-github-api.php',
            GCA_PLUGIN_DIR . 'public/class-public.php',
            GCA_PLUGIN_DIR . 'public/js/public.js',
            GCA_PLUGIN_DIR . 'github-claude-analyzer.php',
            GCA_PLUGIN_DIR . 'admin/class-admin.php'
        ];
        
        $results = [];
        
        foreach ($files_to_check as $file) {
            if (file_exists($file)) {
                $perms = fileperms($file);
                $mode = substr(sprintf('%o', $perms), -4);
                $readable = is_readable($file);
                
                $results[basename($file)] = [
                    'exists' => true,
                    'permissions' => $mode,
                    'readable' => $readable,
                    'size' => filesize($file),
                    'modified' => date('Y-m-d H:i:s', filemtime($file))
                ];
            } else {
                $results[basename($file)] = [
                    'exists' => false,
                    'permissions' => 'N/A',
                    'readable' => false,
                    'size' => 0,
                    'modified' => 'N/A'
                ];
            }
        }
        
        return $results;
    }

    /**
     * Zapisuje klucz API Claude i token GitHub.
     */
    public function save_claude_api_key() {
        // Sprawdź nonce
        if (!isset($_POST['gca_nonce']) || !wp_verify_nonce($_POST['gca_nonce'], 'save_claude_api_key')) {
            wp_die('Weryfikacja zabezpieczeń nie powiodła się.');
        }
        
        // Sprawdź uprawnienia
        if (!current_user_can('manage_options')) {
            wp_die('Nie masz wystarczających uprawnień.');
        }
        
        // Pobierz i zwaliduj dane
        $api_key = isset($_POST['claude_api_key']) ? sanitize_text_field($_POST['claude_api_key']) : '';
        $github_token = isset($_POST['github_token']) ? sanitize_text_field($_POST['github_token']) : '';
        $model = isset($_POST['claude_model']) ? sanitize_text_field($_POST['claude_model']) : 'claude-3-7-sonnet-20250219';
        
        if (empty($api_key)) {
            add_settings_error('gca_settings', 'empty_api_key', 'Klucz API Claude jest wymagany.', 'error');
            set_transient('gca_settings_errors', get_settings_errors(), 30);
            wp_redirect(admin_url('admin.php?page=github-claude-analyzer-settings'));
            exit;
        }
        
        // Weryfikuj klucz API Claude
        $claude_api_valid = false;
        if (class_exists('GitHub_Claude_API')) {
            try {
                $claude_api = new GitHub_Claude_API($api_key);
                $test_response = $claude_api->test_connection();
                $claude_api_valid = $test_response['success'];
                
                if (!$claude_api_valid) {
                    add_settings_error('gca_settings', 'invalid_api_key', 'Klucz API Claude jest nieprawidłowy: ' . $test_response['message'], 'error');
                    set_transient('gca_settings_errors', get_settings_errors(), 30);
                    wp_redirect(admin_url('admin.php?page=github-claude-analyzer-settings'));
                    exit;
                }
            } catch (Exception $e) {
                add_settings_error('gca_settings', 'api_error', 'Błąd weryfikacji klucza API Claude: ' . $e->getMessage(), 'error');
                set_transient('gca_settings_errors', get_settings_errors(), 30);
                wp_redirect(admin_url('admin.php?page=github-claude-analyzer-settings'));
                exit;
            }
        }
        
        // Weryfikuj token GitHub jeśli podano
        if (!empty($github_token)) {
            if (class_exists('GitHub_API')) {
                try {
                    $github_api = new GitHub_API($github_token);
                    $token_verification = $github_api->verify_token($github_token);
                    
                    if (!$token_verification['valid']) {
                        add_settings_error('gca_settings', 'invalid_github_token', 'Token GitHub jest nieprawidłowy: ' . $token_verification['message'], 'error');
                        set_transient('gca_settings_errors', get_settings_errors(), 30);
                        wp_redirect(admin_url('admin.php?page=github-claude-analyzer-settings'));
                        exit;
                    }
                } catch (Exception $e) {
                    add_settings_error('gca_settings', 'github_error', 'Błąd weryfikacji tokenu GitHub: ' . $e->getMessage(), 'error');
                    set_transient('gca_settings_errors', get_settings_errors(), 30);
                    wp_redirect(admin_url('admin.php?page=github-claude-analyzer-settings'));
                    exit;
                }
            }
        }
        
        // Zapisz do bazy danych
        global $wpdb;
        $table_name = $wpdb->prefix . 'claude_api_keys';
        $user_id = get_current_user_id();
        
        // Sprawdź, czy istnieje już wpis dla tego użytkownika
        $existing = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM $table_name WHERE user_id = %d", $user_id)
        );
        
        if ($existing) {
            // Aktualizuj istniejący wpis
            $wpdb->update(
                $table_name,
                array(
                    'api_key' => $api_key,
                    'github_token' => $github_token,
                    'model' => $model,
                    'updated_at' => current_time('mysql')
                ),
                array('user_id' => $user_id),
                array('%s', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            // Dodaj nowy wpis
            $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'api_key' => $api_key,
                    'github_token' => $github_token,
                    'model' => $model,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s')
            );
        }
        
        // Wyczyść bufor modeli Claude
        delete_transient('gca_available_claude_models');
        
        // Dodaj komunikat sukcesu
        add_settings_error('gca_settings', 'save_success', 'Ustawienia zostały zapisane pomyślnie.', 'success');
        set_transient('gca_settings_errors', get_settings_errors(), 30);
        
        // Przekieruj z powrotem do strony ustawień
        wp_redirect(admin_url('admin.php?page=github-claude-analyzer-settings'));
        exit;
    }

    /**
     * Testuje połączenie z API Claude.
     */
    public function test_claude_connection() {
        // Sprawdź nonce
        if (!check_ajax_referer('gca_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Weryfikacja zabezpieczeń nie powiodła się.']);
            exit;
        }
        
        try {
            $claude_api = new GitHub_Claude_API();
            $result = $claude_api->test_connection();
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => 'Błąd: ' . $e->getMessage()
            ]);
        }
        
        exit;
    }
    
    /**
     * Odświeża listę dostępnych modeli Claude.
     */
    public function refresh_claude_models() {
        // Sprawdź nonce
        check_ajax_referer('gca_ajax_nonce', 'nonce');
        
        // Sprawdź uprawnienia
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => 'Brak wymaganych uprawnień.'
            ));
            exit;
        }
        
        // Pobierz klucz API
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        
        if (empty($api_key)) {
            wp_send_json_error(array(
                'message' => 'Klucz API jest wymagany.'
            ));
            exit;
        }
        
        try {
            // Wymusza ponowne pobranie modeli
            delete_transient('gca_available_claude_models');
            
            // Pobierz modele
            $claude_api = new GitHub_Claude_API($api_key);
            $available_models = $claude_api->get_available_models();
            
            if (empty($available_models)) {
                wp_send_json_error(array(
                    'message' => 'Nie udało się pobrać modeli. Sprawdź swój klucz API.'
                ));
                exit;
            }
            
            wp_send_json_success(array(
                'models' => $available_models
            ));
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Błąd: ' . $e->getMessage()
            ));
        }
        
        exit;
    }
    
    /**
     * Weryfikuje token GitHub poprzez AJAX.
     */
    public function ajax_verify_github_token() {
        // Sprawdź nonce
        check_ajax_referer('gca_ajax_nonce', 'nonce');
        
        // Sprawdź uprawnienia
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => 'Brak wymaganych uprawnień.'
            ));
            exit;
        }
        
        // Pobierz token z żądania
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        
        if (empty($token)) {
            wp_send_json_error(array(
                'message' => 'Token GitHub jest wymagany.'
            ));
            exit;
        }
        
        try {
            if (!class_exists('GitHub_API')) {
                require_once GCA_PLUGIN_DIR . 'includes/class-github-api.php';
            }
            
            $github_api = new GitHub_API();
            $verification = $github_api->verify_token($token);
            
            if ($verification['valid']) {
                wp_send_json_success(array(
                    'message' => $verification['message'],
                    'limit' => $verification['limit'],
                    'remaining' => $verification['remaining'],
                    'reset' => $verification['reset'],
                    'authenticated' => $verification['authenticated']
                ));
            } else {
                wp_send_json_error(array(
                    'message' => $verification['message']
                ));
            }
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Błąd: ' . $e->getMessage()
            ));
        }
        
        exit;
    }
    
    /**
     * Przeprowadza pełną weryfikację wtyczki.
     */
    public function verify_plugin() {
        // Sprawdź nonce
        check_ajax_referer('gca_ajax_nonce', 'nonce');
        
        // Sprawdź uprawnienia
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => 'Brak wymaganych uprawnień.'
            ));
            exit;
        }
        
        $results = [
            'db_status' => $this->check_database_tables(),
            'files_status' => $this->check_file_permissions(),
            'claude_api' => $this->verify_claude_api_key(),
            'github_api' => $this->verify_github_token(),
            'system_info' => [
                'php_version' => phpversion(),
                'wordpress_version' => get_bloginfo('version'),
                'plugin_version' => GCA_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time')
            ],
            'success' => true
        ];
        
        // Sprawdź, czy są błędy w bazie danych
        $db_errors = false;
        foreach ($results['db_status'] as $table => $status) {
            if (!$status['exists'] || $status['error']) {
                $db_errors = true;
                break;
            }
        }
        
        // Sprawdź, czy są błędy w plikach
        $file_errors = false;
        foreach ($results['files_status'] as $file => $status) {
            if (!$status['exists'] || !$status['readable']) {
                $file_errors = true;
                break;
            }
        }
        
        // Dodaj rekomendacje naprawy
        $results['recommendations'] = [];
        
        if ($db_errors) {
            $results['recommendations'][] = 'Wykryto problemy z tabelami bazy danych. Zalecane jest dezaktywowanie i ponowne aktywowanie wtyczki, aby zrekonstruować tabele.';
        }
        
        if ($file_errors) {
            $results['recommendations'][] = 'Niektóre pliki wtyczki są uszkodzone lub brakujące. Zalecane jest ponowne zainstalowanie wtyczki.';
        }
        
        if (!$results['claude_api']['valid']) {
            $results['recommendations'][] = 'Klucz API Claude jest nieprawidłowy. Zaktualizuj klucz API w ustawieniach.';
        }
        
        wp_send_json_success($results);
        exit;
    }

    /**
     * Naprawia tabele bazy danych.
     */
    public function repair_database() {
        // Sprawdź nonce
        if (!check_ajax_referer('gca_ajax_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Weryfikacja zabezpieczeń nie powiodła się.']);
            exit;
        }
        
        // Sprawdź uprawnienia
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Brak wymaganych uprawnień.']);
            exit;
        }
        
        // Wyłącz i włącz ponownie wtyczkę, aby odtworzyć tabele
        try {
            // Wykonaj ręcznie funkcję aktywacji
            require_once GCA_PLUGIN_DIR . 'includes/class-activator.php';
            GitHub_Claude_Analyzer_Activator::activate();
            
            // Sprawdź czy tabele zostały utworzone
            global $wpdb;
            $tables_to_check = [
                $wpdb->prefix . 'claude_api_keys',
                $wpdb->prefix . 'claude_logs',
                $wpdb->prefix . 'claude_processed_files'
            ];
            
            $all_tables_exist = true;
            foreach ($tables_to_check as $table) {
                if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                    $all_tables_exist = false;
                    break;
                }
            }
            
            if ($all_tables_exist) {
                wp_send_json_success(['message' => 'Tabele bazy danych zostały naprawione pomyślnie.']);
            } else {
                wp_send_json_error(['message' => 'Nie udało się naprawić wszystkich tabel bazy danych.']);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Błąd podczas naprawy tabel: ' . $e->getMessage()]);
        }
        
        exit;
    }
    
    /**
     * Weryfikuje ważność kluczy API.
     */
    public function verify_api_keys() {
        // Sprawdź klucz API Claude
        $claude_api_verification = $this->verify_claude_api_key();
        
        // Sprawdź token GitHub
        $github_token_verification = $this->verify_github_token();
        
        return [
            'claude_api' => $claude_api_verification,
            'github_token' => $github_token_verification
        ];
    }

    /**
     * Weryfikuje klucz API Claude.
     */
    private function verify_claude_api_key() {
        $result = [
            'valid' => false,
            'message' => 'Klucz API Claude nie jest skonfigurowany.',
            'details' => null
        ];
        
        if (!class_exists('GitHub_Claude_API')) {
            require_once GCA_PLUGIN_DIR . 'includes/class-claude-api.php';
        }
        
        $claude_api = new GitHub_Claude_API();
        $api_key = $claude_api->get_api_key();
        
        if (empty($api_key)) {
            return $result;
        }
        
        // Wykonaj test połączenia
        $test_response = $claude_api->test_connection();
        
        if ($test_response['success']) {
            $result['valid'] = true;
            $result['message'] = 'Klucz API Claude jest poprawny.';
            $result['details'] = $test_response['account_info'] ?? null;
        } else {
            $result['message'] = 'Klucz API Claude jest niepoprawny lub wystąpił problem: ' . $test_response['message'];
        }
        
        return $result;
    }

    /**
     * Weryfikuje token GitHub.
     */
    private function verify_github_token() {
        $result = [
            'valid' => false,
            'message' => 'Token GitHub nie jest skonfigurowany.',
            'details' => null
        ];
        
        if (!class_exists('GitHub_API')) {
            require_once GCA_PLUGIN_DIR . 'includes/class-github-api.php';
        }
        
        $github_api = new GitHub_API();
        $token = $github_api->get_github_token();
        
        if (empty($token)) {
            return $result;
        }
        
        // Testuj token GitHub poprzez sprawdzenie limitu API
        $verification = $github_api->verify_token($token);
        
        if ($verification['valid']) {
            $result['valid'] = true;
            $result['message'] = 'Token GitHub jest poprawny.';
            $result['details'] = [
                'limit' => $verification['limit'],
                'remaining' => $verification['remaining'],
                'reset' => $verification['reset'],
                'authenticated' => $verification['authenticated']
            ];
        } else {
            $result['message'] = $verification['message'];
        }
        
        return $result;
    }
}