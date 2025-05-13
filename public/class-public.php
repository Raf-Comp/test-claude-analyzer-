<?php
/**
 * Klasa obsługująca publiczną część wtyczki.
 */
class GitHub_Claude_Analyzer_Public {

    /**
     * Konstruktor.
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Uruchamia funkcjonalność publiczną.
     */
    public function run() {
        // Rejestracja shortcode'a
        add_shortcode('github_claude_analyzer', array($this, 'render_analyzer_form'));
        
        // Obsługa asynchronicznej analizy
        add_action('wp_ajax_start_github_analysis', array($this, 'start_github_analysis'));
        add_action('wp_ajax_check_analysis_progress', array($this, 'check_analysis_progress'));
        add_action('wp_ajax_clear_processed_files_history', array($this, 'clear_processed_files_history'));
        add_action('wp_ajax_nopriv_analyze_github_repository', array($this, 'handle_unauthorized_request'));
        
        // Obsługa przerwania analizy
        add_action('wp_ajax_gca_cancel_analysis', array($this, 'cancel_analysis'));
        
        // Obsługa błędów JavaScript
        add_action('wp_ajax_gca_log_js_error', array($this, 'log_js_error'));
        add_action('wp_ajax_nopriv_gca_log_js_error', array($this, 'log_js_error'));
        
        // Obsługa diagnostyki
        add_action('wp_ajax_gca_diagnostic_ping', array($this, 'diagnostic_ping'));
        add_action('wp_ajax_gca_diagnostic_file_permissions', array($this, 'diagnostic_file_permissions'));
        add_action('wp_ajax_gca_diagnostic_php_limits', array($this, 'diagnostic_php_limits'));
        add_action('wp_ajax_gca_diagnostic_db_check', array($this, 'diagnostic_db_check'));
        
        // Obsługa weryfikacji repozytorium
        add_action('wp_ajax_gca_verify_repository', array($this, 'verify_repository'));
        add_action('wp_ajax_nopriv_gca_verify_repository', array($this, 'handle_unauthorized_request'));
        
        // Nowa akcja - wyszukiwanie repozytoriów
        add_action('wp_ajax_gca_search_repositories', array($this, 'search_repositories'));
        add_action('wp_ajax_nopriv_gca_search_repositories', array($this, 'handle_unauthorized_request'));
    }

    /**
     * Dołącza style publiczne.
     */
    public function enqueue_styles() {
        // Wczytaj podstawowe style
        wp_enqueue_style('gca-public', GCA_PLUGIN_URL . 'public/css/public.css', array(), GCA_VERSION);
        
        // Wczytaj style kolorowania składni na stronach z shortcodem
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'github_claude_analyzer')) {
            // Dodaj style dla kolorowania składni
            wp_enqueue_style('gca-prism', GCA_PLUGIN_URL . 'public/css/prism.css', array(), GCA_VERSION);
        }
        
        // Dodaj również style inline
        wp_add_inline_style('gca-public', '
            .gca-form {margin: 0;}
            .gca-form-group {margin-bottom: 20px;}
            .gca-form-control {width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;}
            .gca-btn {padding: 10px 15px; border-radius: 4px; cursor: pointer; display: inline-flex; align-items: center;}
            .gca-btn-primary {background: #6366f1; color: white; border: none;}
            .gca-btn-secondary {background: #f3f4f6; color: #374151; border: 1px solid #d1d5db;}
            
            /* Style dla przycisku anulowania */
            .gca-cancel-analysis-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 8px 16px;
                background-color: #ef4444;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                margin-top: 15px;
                transition: background-color 0.2s;
            }
            
            .gca-cancel-analysis-btn:hover {
                background-color: #dc2626;
            }
            
            .gca-cancel-analysis-btn .dashicons {
                margin-right: 5px;
            }
            
            /* Style dla podświetlania kodu */
            .gca-code-toolbar {
                position: relative;
                margin: 1em 0;
            }
            
            .gca-code-toolbar pre {
                background-color: #2d2d2d;
                color: #ccc;
                border-radius: 5px;
                padding: 1em;
                margin: 0;
                overflow: auto;
                max-height: 500px;
            }
            
            .gca-code-toolbar .toolbar {
                position: absolute;
                top: 0.3em;
                right: 0.2em;
                transition: opacity 0.3s ease-in-out;
                opacity: 0;
            }
            
            .gca-code-toolbar:hover .toolbar {
                opacity: 1;
            }
            
            .gca-code-toolbar .toolbar-item {
                display: inline-block;
                margin-left: 5px;
            }
            
            .gca-code-toolbar button {
                background-color: #6366f1;
                color: white;
                border: none;
                border-radius: 4px;
                padding: 5px 10px;
                font-size: 12px;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
            }
            
            .gca-code-toolbar button:hover {
                background-color: #4f46e5;
            }
            
            .gca-code-toolbar button .dashicons {
                font-size: 14px;
                margin-right: 5px;
            }
            
            /* Style dla linii numeracji kodu */
            .line-numbers-rows {
                position: absolute;
                pointer-events: none;
                top: 0;
                font-size: 100%;
                left: -3.8em;
                width: 3em;
                letter-spacing: -1px;
                border-right: 1px solid #999;
                user-select: none;
            }
            
            .line-numbers-rows > span {
                display: block;
                counter-increment: linenumber;
            }
            
            .line-numbers-rows > span:before {
                content: counter(linenumber);
                color: #999;
                display: block;
                padding-right: 0.8em;
                text-align: right;
            }
            
            /* Style dla opcji typu repozytorium */
            .repo-type-selector {
                display: flex;
                gap: 16px;
                margin-bottom: 20px;
            }
            
            .repo-type-option {
                display: flex;
                align-items: center;
                cursor: pointer;
            }
            
            .repo-type-option img {
                width: 24px;
                height: 24px;
                margin-right: 8px;
            }
        ');
    }

    /**
     * Dołącza skrypty publiczne.
     */
    public function enqueue_scripts() {
        // Wczytaj podstawowe skrypty
        wp_enqueue_script('gca-public', GCA_PLUGIN_URL . 'public/js/public.js', array('jquery'), GCA_VERSION . '.' . time(), true);
        
        // Wczytaj skrypty kolorowania składni na stronach z shortcodem
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'github_claude_analyzer')) {
            // Dodaj skrypty kolorowania składni
            wp_enqueue_script('gca-prism', GCA_PLUGIN_URL . 'public/js/prism.js', array(), GCA_VERSION, true);
            wp_enqueue_script('gca-code-highlighter', GCA_PLUGIN_URL . 'public/js/code-highlighter.js', array('jquery', 'gca-prism'), GCA_VERSION, true);
            
            // Dodaj obsługę wyboru modelu
            wp_enqueue_script('gca-model-selector', GCA_PLUGIN_URL . 'public/js/model-selector.js', array('jquery'), GCA_VERSION, true);
            
            // Dodaj skrypt weryfikacji repozytorium
            wp_enqueue_script('gca-verify-repo', GCA_PLUGIN_URL . 'public/js/verify-repo.js', array('jquery'), GCA_VERSION, true);
        }
        
        // Zawsze załącz dane dla skryptów
        wp_localize_script('gca-public', 'gca_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gca_analyzer_nonce'),
            'version' => GCA_VERSION,
            'debug_mode' => defined('WP_DEBUG') && WP_DEBUG,
            'plugin_url' => GCA_PLUGIN_URL,
            'admin_url' => admin_url(),
            'site_url' => site_url(),
            'language' => get_locale()
        ));
    }

    /**
     * Renderuje formularz analizatora.
     */
    public function render_analyzer_form() {
        // Sprawdź, czy użytkownik ma skonfigurowane klucze API
        $has_api_keys = false;
        $show_settings_notice = false;
        
        if (is_user_logged_in()) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'claude_api_keys';
            $user_id = get_current_user_id();
            
            $api_key = $wpdb->get_var(
                $wpdb->prepare("SELECT api_key FROM $table_name WHERE user_id = %d", $user_id)
            );
            
            if (!empty($api_key)) {
                $has_api_keys = true;
            } else {
                $show_settings_notice = current_user_can('manage_options');
            }
        }
        
        // Pobierz ustawienia
        $settings = get_option('gca_settings', array());
        $default_extensions = $settings['default_extensions'] ?? array('php', 'js', 'css', 'html');
        
        ob_start();
        include GCA_PLUGIN_DIR . 'public/partials/form-display.php';
        return ob_get_clean();
    }

    /**
     * Obsługuje nieautoryzowane żądania.
     */
    public function handle_unauthorized_request() {
        wp_send_json_error(array(
            'message' => 'Musisz być zalogowany, aby wykonać tę akcję.'
        ));
    }

    /**
     * Rozpoczyna asynchroniczną analizę repozytorium.
     */
    public function start_github_analysis() {
        // Zapisz dane wejściowe dla debugowania
        error_log('GCA Start Analysis: ' . json_encode($_POST));
        
        // Sprawdź nonce w bardziej elastyczny sposób
        if (!check_ajax_referer('gca_analyzer_nonce', 'nonce', false)) {
            error_log('GCA: Błąd weryfikacji nonce');
            wp_send_json_error(['message' => 'Weryfikacja bezpieczeństwa nie powiodła się. Odśwież stronę i spróbuj ponownie.']);
            exit;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Musisz być zalogowany, aby wykonać tę akcję.']);
            exit;
        }
        
        // Pobierz dane formularza
        $repo_url = isset($_POST['repo_url']) ? sanitize_text_field($_POST['repo_url']) : '';
        $prompt = isset($_POST['prompt']) ? sanitize_textarea_field($_POST['prompt']) : '';
        $max_files = isset($_POST['max_files']) ? intval($_POST['max_files']) : 30;
        $force_reanalysis = isset($_POST['force_reanalysis']) ? (bool)$_POST['force_reanalysis'] : false;
        $repo_type = isset($_POST['repo_type']) ? sanitize_text_field($_POST['repo_type']) : 'github';
        $directory_path = isset($_POST['directory_path']) ? sanitize_text_field($_POST['directory_path']) : '';
        $analysis_depth = isset($_POST['analysis_depth']) ? sanitize_text_field($_POST['analysis_depth']) : 'standard';
        $ignore_tests = isset($_POST['ignore_tests']) ? (bool)$_POST['ignore_tests'] : false;
        $include_dependencies = isset($_POST['include_dependencies']) ? (bool)$_POST['include_dependencies'] : false;
        
        // Walidacja danych wejściowych
        if (empty($repo_url)) {
            wp_send_json_error(['message' => 'URL repozytorium jest wymagany.']);
            exit;
        }
        
        // Sprawdź format URL w zależności od typu repozytorium
        $url_valid = false;
        
        switch ($repo_type) {
            case 'github':
                $url_valid = preg_match('/github\.com\/([^\/]+)\/([^\/\?\#]+)/i', $repo_url);
                break;
            case 'gitlab':
                $url_valid = preg_match('/gitlab\.com\/([^\/]+)\/([^\/\?\#]+)/i', $repo_url);
                break;
            case 'bitbucket':
                $url_valid = preg_match('/bitbucket\.org\/([^\/]+)\/([^\/\?\#]+)/i', $repo_url);
                break;
        }
        
        if (!$url_valid) {
            wp_send_json_error(['message' => 'Nieprawidłowy format URL repozytorium ' . ucfirst($repo_type) . '.']);
            exit;
        }
        
        // Ogranicz długość prompt
        if (strlen($prompt) > 1000) {
            $prompt = substr($prompt, 0, 1000) . '...';
        }
        
        // Zapewnij odpowiednie limity dla max_files
        if ($max_files < 1) {
            $max_files = 1;
        } elseif ($max_files > 150) {
            $max_files = 150;
        }
        
        // Pobierz filtry plików
        $file_filters = [];
        if (isset($_POST['file_filters']) && is_array($_POST['file_filters'])) {
            foreach ($_POST['file_filters'] as $filter) {
                $file_filters[] = sanitize_text_field($filter);
            }
        }
        
        // Dodaj rozszerzenia z innych_rozszerzeń
        if (isset($_POST['other_extensions']) && !empty($_POST['other_extensions'])) {
            $other_extensions = explode(',', sanitize_text_field($_POST['other_extensions']));
            foreach ($other_extensions as $ext) {
                $ext = trim($ext);
                if (!empty($ext) && !in_array($ext, $file_filters)) {
                    $file_filters[] = $ext;
                }
            }
        }
        
        // Generuj unikalny identyfikator zadania
        $task_id = uniqid('gca_task_');
        
        // Zapisz informacje o zadaniu w opcjach
        $task_data = [
            'id' => $task_id,
            'status' => 'initialized',
            'progress' => 0,
            'repo_url' => $repo_url,
            'repo_type' => $repo_type,
            'prompt' => $prompt,
            'max_files' => $max_files,
            'file_filters' => $file_filters,
            'directory_path' => $directory_path,
            'analysis_depth' => $analysis_depth,
            'ignore_tests' => $ignore_tests,
            'include_dependencies' => $include_dependencies,
            'force_reanalysis' => $force_reanalysis,
            'user_id' => get_current_user_id(),
            'created_at' => current_time('mysql'),
            'message' => 'Zadanie zostało zainicjowane',
            'processed_files' => 0,
            'total_files' => 0,
            'current_file' => '',
            'results' => null,
            'completed' => false,
            'error' => null
        ];
        
        update_option('gca_task_' . $task_id, $task_data);
        
        // Zaplanuj zadanie do wykonania w tle
        if (!wp_next_scheduled('gca_process_analysis_task', [$task_id])) {
            wp_schedule_single_event(time(), 'gca_process_analysis_task', [$task_id]);
        }
        
        // Log informacyjny
        error_log('GCA: Zadanie analizy zaplanowane. Task ID: ' . $task_id);
        
        // Zwróć odpowiedź
        wp_send_json_success(['task_id' => $task_id]);
        exit;
    }

    /**
     * Sprawdza postęp analizy.
     */
    public function check_analysis_progress() {
        // Sprawdź nonce w bardziej elastyczny sposób
        if (!check_ajax_referer('gca_analyzer_nonce', 'nonce', false)) {
            error_log('GCA: Błąd weryfikacji nonce w check_analysis_progress');
            wp_send_json_error(['message' => 'Weryfikacja bezpieczeństwa nie powiodła się. Odśwież stronę i spróbuj ponownie.']);
            exit;
        }
        
        $task_id = isset($_POST['task_id']) ? sanitize_text_field($_POST['task_id']) : '';
        
        if (empty($task_id)) {
            error_log('GCA: Brak task_id w zapytaniu check_analysis_progress');
            wp_send_json_error(['message' => 'Brak identyfikatora zadania.']);
            exit;
        }
        
        // Pobierz dane zadania
        $task_data = get_option('gca_task_' . $task_id);
        
        if (!$task_data) {
            error_log('GCA: Nie znaleziono zadania o ID: ' . $task_id);
            wp_send_json_error(['message' => 'Nie znaleziono zadania o podanym identyfikatorze.']);
            exit;
        }
        
        // Sprawdź czy zadanie zostało rozpoczęte
        if (!wp_next_scheduled('gca_process_analysis_task', [$task_id]) && $task_data['status'] === 'initialized') {
            // Zadanie nie zostało jeszcze uruchomione, spróbuj ponownie zaplanować
            error_log('GCA: Zadanie nie zostało uruchomione, planujemy ponownie. Task ID: ' . $task_id);
            wp_schedule_single_event(time(), 'gca_process_analysis_task', [$task_id]);
        }
        
        // Jeśli status to "error", upewnij się, że komunikat błędu jest ustawiony
        if ($task_data['status'] === 'error' && empty($task_data['message'])) {
            $task_data['message'] = 'Wystąpił nieznany błąd podczas analizy.';
        }
        
        // Zwróć informacje o postępie
        wp_send_json_success([
            'progress' => $task_data['progress'],
            'status' => $task_data['status'],
            'message' => $task_data['message'],
            'current_file' => $task_data['current_file'],
            'processed_files' => $task_data['processed_files'],
            'total_files' => $task_data['total_files'],
            'completed' => $task_data['completed'],
            'results' => $task_data['results'],
            'error' => $task_data['error']
        ]);
        exit;
    }

    /**
     * Anuluje trwającą analizę.
     */
    public function cancel_analysis() {
        // Sprawdź nonce
        if (!check_ajax_referer('gca_analyzer_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Weryfikacja bezpieczeństwa nie powiodła się.']);
            exit;
        }
        
        $task_id = isset($_POST['task_id']) ? sanitize_text_field($_POST['task_id']) : '';
        
        if (empty($task_id)) {
            wp_send_json_error(['message' => 'Identyfikator zadania jest wymagany.']);
            exit;
        }
        
        // Pobierz dane zadania
        $task_data = get_option('gca_task_' . $task_id);
        
        if (!$task_data) {
            wp_send_json_error(['message' => 'Nie znaleziono zadania o podanym identyfikatorze.']);
            exit;
        }
        
        // Usuń zaplanowane zadanie
        wp_clear_scheduled_hook('gca_process_analysis_task', [$task_id]);
        
        // Zaktualizuj status zadania
        $task_data['status'] = 'cancelled';
        $task_data['message'] = 'Analiza została przerwana przez użytkownika.';
        $task_data['completed'] = true;
        
        update_option('gca_task_' . $task_id, $task_data);
        
        wp_send_json_success(['message' => 'Analiza została przerwana.']);
        exit;
    }

    /**
     * Czyści historię przetworzonych plików dla danego repozytorium.
     */
    public function clear_processed_files_history() {
        // Sprawdź nonce
        if (!check_ajax_referer('gca_analyzer_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Weryfikacja bezpieczeństwa nie powiodła się.']);
            exit;
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Musisz być zalogowany, aby wykonać tę akcję.']);
            exit;
        }
        
        $repository_url = isset($_POST['repository_url']) ? sanitize_text_field($_POST['repository_url']) : '';
        
        if (empty($repository_url)) {
            wp_send_json_error(['message' => 'URL repozytorium jest wymagany.']);
            exit;
        }
        
        // Załaduj wymagane klasy
        if (!class_exists('GitHub_Claude_API')) {
            require_once GCA_PLUGIN_DIR . 'includes/class-claude-api.php';
        }
        
        $claude_api = new GitHub_Claude_API();
        $result = $claude_api->clear_processed_files_history($repository_url);
        
        if ($result === false) {
            wp_send_json_error(['message' => 'Wystąpił błąd podczas czyszczenia historii.']);
            exit;
        }
        
        wp_send_json_success([
            'message' => 'Historia przetworzonych plików została wyczyszczona.',
            'count' => $result
        ]);
        exit;
    }

    /**
     * Loguje błąd JavaScript z front-endu.
     */
    public function log_js_error() {
        // Sprawdź nonce
        if (!check_ajax_referer('gca_analyzer_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Weryfikacja bezpieczeństwa nie powiodła się.']);
            exit;
        }
        
        $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : 'Nieznany błąd JS';
        $details = isset($_POST['details']) ? $_POST['details'] : '{}';
        $url = isset($_POST['url']) ? sanitize_text_field($_POST['url']) : '';
        $user_agent = isset($_POST['user_agent']) ? sanitize_text_field($_POST['user_agent']) : '';
        
        // Zapisz błąd do logu
        error_log("GCA JS Error: $message | URL: $url | UA: $user_agent | Details: $details");
        
        // Zapisz błąd w bazie danych
        $js_errors = get_option('gca_js_errors', []);
        $js_errors[] = [
            'time' => current_time('mysql'),
            'message' => $message,
            'details' => $details,
            'url' => $url,
            'user_agent' => $user_agent,
            'user_id' => get_current_user_id()
        ];
        
        // Ogranicz liczbę zapisanych błędów
        if (count($js_errors) > 50) {
            array_shift($js_errors);
        }
        
        update_option('gca_js_errors', $js_errors);
        
        // Zwróć sukces (chociaż to może zostać zignorowane przez sendBeacon)
        wp_send_json_success();
        exit;
    }

    /**
     * Weryfikuje dostępność repozytorium.
     */
    public function verify_repository() {
        // Sprawdź nonce
        if (!check_ajax_referer('gca_analyzer_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Weryfikacja bezpieczeństwa nie powiodła się.']);
            exit;
        }
        
        $repo_url = isset($_POST['repo_url']) ? sanitize_text_field($_POST['repo_url']) : '';
        $repo_type = isset($_POST['repo_type']) ? sanitize_text_field($_POST['repo_type']) : 'github';
        
        if (empty($repo_url)) {
            wp_send_json_error(['message' => 'URL repozytorium jest wymagany.']);
            exit;
        }
        
        // Sprawdź czy URL ma poprawny format w zależności od typu repozytorium
        $matches = array();
        $valid_url = false;
        
        switch ($repo_type) {
            case 'github':
                $valid_url = preg_match('/github\.com\/([^\/]+)\/([^\/\?\#]+)/i', $repo_url, $matches);
                break;
            case 'gitlab':
                $valid_url = preg_match('/gitlab\.com\/([^\/]+)\/([^\/\?\#]+)/i', $repo_url, $matches);
                break;
            case 'bitbucket':
                $valid_url = preg_match('/bitbucket\.org\/([^\/]+)\/([^\/\?\#]+)/i', $repo_url, $matches);
                break;
        }
        
        if (!$valid_url) {
            wp_send_json_error(['message' => 'Nieprawidłowy format URL repozytorium ' . ucfirst($repo_type) . '.']);
            exit;
        }
        
        $username = $matches[1];
        $repo = $matches[2];
        
        // Załaduj odpowiednie API w zależności od typu repozytorium
        switch ($repo_type) {
            case 'gitlab':
                if (!class_exists('GitLab_API')) {
                    require_once GCA_PLUGIN_DIR . 'includes/class-gitlab-api.php';
                }
                $api = new GitLab_API();
                break;
            case 'bitbucket':
                if (!class_exists('Bitbucket_API')) {
                    require_once GCA_PLUGIN_DIR . 'includes/class-bitbucket-api.php';
                }
                $api = new Bitbucket_API();
                break;
            case 'github':
            default:
                if (!class_exists('GitHub_API')) {
                    require_once GCA_PLUGIN_DIR . 'includes/class-github-api.php';
                }
                $api = new GitHub_API();
                break;
        }
        
        try {
            // Pobierz informacje o repozytorium
            $repo_info = $api->get_repository_stats($repo_url);
            
            // Udało się zweryfikować repozytorium
            wp_send_json_success([
                'message' => 'Repozytorium jest dostępne.',
                'repo_info' => [
                    'name' => $repo_info['name'],
                    'full_name' => $repo_info['full_name'],
                    'owner' => [
                        'login' => isset($repo_info['owner']) ? $repo_info['owner'] : $username,
                        'avatar_url' => isset($repo_info['owner_avatar']) ? $repo_info['owner_avatar'] : ''
                    ],
                    'stars' => isset($repo_info['stars']) ? $repo_info['stars'] : 0,
                    'forks' => isset($repo_info['forks']) ? $repo_info['forks'] : 0,
                    'is_private' => isset($repo_info['private']) ? $repo_info['private'] : false,
                    'default_branch' => isset($repo_info['default_branch']) ? $repo_info['default_branch'] : 'master'
                ]
            ]);
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => 'Błąd podczas weryfikacji repozytorium: ' . $e->getMessage()
            ]);
        }
        
        exit;
    }

    /**
     * Wyszukuje repozytoria na wybranej platformie.
     */
    public function search_repositories() {
        // Sprawdź nonce
        if (!check_ajax_referer('gca_analyzer_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Weryfikacja bezpieczeństwa nie powiodła się.']);
            exit;
        }
        
        $search_query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        $repo_type = isset($_POST['repo_type']) ? sanitize_text_field($_POST['repo_type']) : 'github';
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        
        if (empty($search_query)) {
            wp_send_json_error(['message' => 'Podaj frazę wyszukiwania.']);
            exit;
        }
        
        // Załaduj odpowiednie API w zależności od typu repozytorium
        switch ($repo_type) {
            case 'gitlab':
                if (!class_exists('GitLab_API')) {
                    require_once GCA_PLUGIN_DIR . 'includes/class-gitlab-api.php';
                }
                $api = new GitLab_API();
                break;
            case 'bitbucket':
                if (!class_exists('Bitbucket_API')) {
                    require_once GCA_PLUGIN_DIR . 'includes/class-bitbucket-api.php';
                }
                $api = new Bitbucket_API();
                break;
            case 'github':
            default:
                if (!class_exists('GitHub_API')) {
                    require_once GCA_PLUGIN_DIR . 'includes/class-github-api.php';
                }
                $api = new GitHub_API();
                break;
        }
        
        try {
            // Wykonaj wyszukiwanie repozytoriów
            $search_results = $api->search_repositories($search_query, $page);
            
            wp_send_json_success([
                'repositories' => $search_results['items'],
                'total_count' => $search_results['total_count'],
                'current_page' => $page,
                'has_next_page' => $search_results['has_next_page'],
                'has_prev_page' => $page > 1
            ]);
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => 'Błąd podczas wyszukiwania repozytoriów: ' . $e->getMessage()
            ]);
        }
        
        exit;
    }

    /**
     * Test diagnostyczny - ping
     */
    public function diagnostic_ping() {
        // Wyłącz buforowanie wyjścia
        if (ob_get_level()) ob_end_clean();
        
        // Ustaw nagłówki
        header('Content-Type: application/json');
        
        // Sprawdź nonce w bardziej elastyczny sposób
        if (!check_ajax_referer('gca_analyzer_nonce', 'nonce', false)) {
            echo json_encode([
                'success' => false,
                'data' => ['message' => 'Weryfikacja bezpieczeństwa nie powiodła się.']
            ]);
            exit;
        }
        
        // Prosta odpowiedź AJAX bez używania wp_send_json_*
        echo json_encode([
            'success' => true,
            'data' => ['message' => 'Połączenie AJAX działa poprawnie.']
        ]);
        exit;
    }

    /**
     * Test diagnostyczny - uprawnienia plików
     */
    public function diagnostic_file_permissions() {
        // Wyłącz buforowanie wyjścia
        if (ob_get_level()) ob_end_clean();
        
        // Ustaw nagłówki
        header('Content-Type: application/json');
        
        // Sprawdź nonce w bardziej elastyczny sposób
        if (!check_ajax_referer('gca_analyzer_nonce', 'nonce', false)) {
            echo json_encode([
                'success' => false,
                'data' => ['message' => 'Weryfikacja bezpieczeństwa nie powiodła się.']
            ]);
            exit;
        }
        
        // Lista plików do sprawdzenia
        $files_to_check = [
            GCA_PLUGIN_DIR . 'includes/class-claude-api.php',
            GCA_PLUGIN_DIR . 'includes/class-github-api.php',
            GCA_PLUGIN_DIR . 'includes/class-gitlab-api.php',
            GCA_PLUGIN_DIR . 'includes/class-bitbucket-api.php',
            GCA_PLUGIN_DIR . 'public/class-public.php',
            GCA_PLUGIN_DIR . 'public/js/public.js',
            GCA_PLUGIN_DIR . 'github-claude-analyzer.php'
        ];
        
        $issues = [];
        
        foreach ($files_to_check as $file) {
            if (!file_exists($file)) {
                $issues[] = "Plik nie istnieje: " . basename($file);
                continue;
            }
            
            $perms = fileperms($file);
            $mode = substr(sprintf('%o', $perms), -4);
            
            if (($perms & 0444) !== 0444) {
                $issues[] = "Plik nie ma uprawnień do odczytu: " . basename($file) . " (mode: $mode)";
            }
        }
        
        if (empty($issues)) {
            echo json_encode([
                'success' => true,
                'data' => ['message' => 'Wszystkie pliki mają poprawne uprawnienia.']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'data' => ['message' => implode(', ', $issues)]
            ]);
        }
        exit;
    }
     /**
     * Test diagnostyczny - limity PHP
     */
    public function diagnostic_php_limits() {
        // Wyłącz buforowanie wyjścia
        if (ob_get_level()) ob_end_clean();
        
        // Ustaw nagłówki
        header('Content-Type: application/json');
        
        // Sprawdź nonce
        if (!check_ajax_referer('gca_analyzer_nonce', 'nonce', false)) {
            echo json_encode([
                'success' => false,
                'data' => ['message' => 'Weryfikacja bezpieczeństwa nie powiodła się.']
            ]);
            exit;
        }
        
        $limits = [
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'post_max_size' => ini_get('post_max_size'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'max_input_time' => ini_get('max_input_time'),
            'wp_memory_limit' => defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : 'nie zdefiniowano',
            'php_version' => phpversion()
        ];
        
        $issues = [];
        
        // Sprawdzenie limitu czasu wykonania
        if ($limits['max_execution_time'] < 60) {
            $issues[] = "Czas wykonania PHP jest zbyt niski: {$limits['max_execution_time']}s (powinno być min. 60s)";
        }
        
        // Sprawdzenie limitu pamięci
        if (preg_match('/^(\d+)M$/', $limits['memory_limit'], $matches)) {
            $memory_mb = (int)$matches[1];
            if ($memory_mb < 128) {
                $issues[] = "Limit pamięci PHP jest zbyt niski: {$limits['memory_limit']} (powinno być min. 128M)";
            }
        }
        
        if (empty($issues)) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'message' => "PHP {$limits['php_version']}, pamięć: {$limits['memory_limit']}, czas: {$limits['max_execution_time']}s",
                    'limits' => $limits
                ]
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'data' => [
                    'message' => implode(', ', $issues),
                    'limits' => $limits
                ]
            ]);
        }
        exit;
    }

    /**
     * Test diagnostyczny - sprawdzenie bazy danych
     */
    public function diagnostic_db_check() {
        // Wyłącz buforowanie wyjścia
        if (ob_get_level()) ob_end_clean();
        
        // Ustaw nagłówki
        header('Content-Type: application/json');
        
        // Sprawdź nonce
        if (!check_ajax_referer('gca_analyzer_nonce', 'nonce', false)) {
            echo json_encode([
                'success' => false,
                'data' => ['message' => 'Weryfikacja bezpieczeństwa nie powiodła się.']
            ]);
            exit;
        }
        
        global $wpdb;
        $tables_to_check = [
            $wpdb->prefix . 'claude_api_keys',
            $wpdb->prefix . 'claude_logs',
            $wpdb->prefix . 'claude_processed_files'
        ];
        
        $issues = [];
        
        foreach ($tables_to_check as $table) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
            
            if (!$table_exists) {
                $issues[] = "Tabela $table nie istnieje";
                continue;
            }
            
            // Sprawdź, czy tabela odpowiada na zapytania
            try {
                $wpdb->query("SELECT 1 FROM $table LIMIT 1");
                
                if ($wpdb->last_error) {
                    $issues[] = "Błąd odczytu z tabeli $table: " . $wpdb->last_error;
                }
            } catch (Exception $e) {
                $issues[] = "Wyjątek podczas dostępu do tabeli $table: " . $e->getMessage();
            }
        }
        
        if (empty($issues)) {
            echo json_encode([
                'success' => true,
                'data' => ['message' => 'Wszystkie tabele są poprawnie skonfigurowane.']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'data' => ['message' => implode(', ', $issues)]
            ]);
        }
        exit;
    }
    
    /**
     * Przetwarza zadanie analizy w tle.
     */
    public function process_analysis_task($task_id) {
        // Pobierz dane zadania
        $task_data = get_option('gca_task_' . $task_id);
        
        if (!$task_data) {
            error_log('GCA: Nie znaleziono zadania o ID: ' . $task_id);
            return;
        }
        
        // Ustaw limity czasu i pamięci
        @ini_set('max_execution_time', 600);
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');
        
        try {
            // Aktualizuj status
            $task_data['status'] = 'preparing';
            $task_data['message'] = 'Przygotowywanie analizy...';
            $task_data['progress'] = 5;
            update_option('gca_task_' . $task_id, $task_data);
            
            // Załaduj wymagane klasy
            if (!class_exists('Repository_API')) {
                require_once GCA_PLUGIN_DIR . 'includes/class-repository-api.php';
            }
            
            if (!class_exists('GitHub_API')) {
                require_once GCA_PLUGIN_DIR . 'includes/class-github-api.php';
            }
            
            if (!class_exists('GitLab_API')) {
                require_once GCA_PLUGIN_DIR . 'includes/class-gitlab-api.php';
            }
            
            if (!class_exists('Bitbucket_API')) {
                require_once GCA_PLUGIN_DIR . 'includes/class-bitbucket-api.php';
            }
            
            if (!class_exists('GitHub_Claude_API')) {
                require_once GCA_PLUGIN_DIR . 'includes/class-claude-api.php';
            }
            
            // Załaduj klasę optymalizatora
            if (!class_exists('GitHub_Claude_Optimizer')) {
                require_once GCA_PLUGIN_DIR . 'includes/class-optimizer.php';
            }
            
            // Wybierz odpowiednie API na podstawie typu repozytorium
            switch ($task_data['repo_type']) {
                case 'gitlab':
                    $repo_api = new GitLab_API();
                    break;
                case 'bitbucket':
                    $repo_api = new Bitbucket_API();
                    break;
                case 'github':
                default:
                    $repo_api = new GitHub_API();
                    break;
            }
            
            $claude_api = new GitHub_Claude_API();
            
            // Sprawdź limity API i ewentualnie wstrzymaj zadanie
            if (GitHub_Claude_Optimizer::handle_api_rate_limits($repo_api, $task_id, $task_data)) {
                // Zadanie zostało wstrzymane, zakończ przetwarzanie
                return;
            }
            
            // Jeśli wymuszono ponowną analizę, wyczyść historię przetworzonych plików
            if ($task_data['force_reanalysis']) {
                $claude_api->clear_processed_files_history($task_data['repo_url']);
            }
            
            // Przygotuj dodatkowe parametry analizy
            $analysis_params = [];
            
            // Dodaj ścieżkę katalogu, jeśli podano
            if (!empty($task_data['directory_path'])) {
                $analysis_params['directory_path'] = $task_data['directory_path'];
            }
            
            // Dodaj opcje ignorowania plików testów
            if ($task_data['ignore_tests']) {
                $analysis_params['exclude_patterns'] = ['test*', 'spec*', '*_test.*', '*_spec.*'];
            }
            
            // Dodaj opcje uwzględnienia plików zależności
            if ($task_data['include_dependencies']) {
                $analysis_params['include_dependencies'] = true;
            }
            
            // Ustaw głębokość analizy
            $analysis_params['analysis_depth'] = $task_data['analysis_depth'];
            
            // Pobierz pliki z repozytorium
            $task_data['status'] = 'fetching_files';
            $task_data['message'] = 'Pobieranie plików z repozytorium...';
            $task_data['progress'] = 10;
            update_option('gca_task_' . $task_id, $task_data);
            
            try {
                $repo_data = $repo_api->get_repository_files(
                    $task_data['repo_url'], 
                    $task_data['max_files'], 
                    $task_data['file_filters'], 
                    $analysis_params
                );
                
                if (empty($repo_data['files'])) {
                    throw new Exception('Nie znaleziono plików w repozytorium lub wystąpił błąd podczas pobierania.');
                }
            } catch (Exception $e) {
                $task_data['status'] = 'error';
                $error_message = $e->getMessage();
                
                // Popraw komunikat dla 404, aby był bardziej pomocny
                if (strpos($error_message, 'Błąd 404') !== false || strpos($error_message, '404 Not Found') !== false) {
                    $error_message = 'Nie można znaleźć repozytorium. Sprawdź czy URL jest poprawny i czy repozytorium istnieje. ' . 
                                 'Jeśli repozytorium jest prywatne, upewnij się, że token ma odpowiednie uprawnienia.';
                }
                
                $task_data['message'] = 'Błąd: ' . $error_message;
                $task_data['error'] = [
                    'message' => $error_message,
                    'trace' => $e->getTraceAsString()
                ];
                $task_data['completed'] = true;
                
                update_option('gca_task_' . $task_id, $task_data);
                error_log('GitHub Claude Analyzer Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
                return;
            }
            
            // Aktualizuj informacje o plikach
            $task_data['total_files'] = count($repo_data['files']);
            $task_data['status'] = 'analyzing';
            $task_data['message'] = 'Analizowanie ' . $task_data['total_files'] . ' plików...';
            $task_data['progress'] = 20;
            update_option('gca_task_' . $task_id, $task_data);
            
            // Dostosuj wiadomość do Claude w zależności od głębokości analizy
            $depth_prompt = "";
            switch ($task_data['analysis_depth']) {
                case 'basic':
                    $depth_prompt = "\n\nProszę o szybką analizę ogólną tych plików, skupiając się na najważniejszych elementach i ogólnej strukturze.";
                    break;
                case 'deep':
                    $depth_prompt = "\n\nProszę o bardzo szczegółową, dogłębną analizę tych plików. Zwróć uwagę na zaawansowane wzorce projektowe, potencjalne problemy z wydajnością, bezpieczeństwem oraz możliwe usprawnienia.";
                    break;
                case 'standard':
                default:
                    $depth_prompt = "\n\nProszę o zbalansowaną analizę tych plików, obejmującą główną funkcjonalność, strukturę oraz najważniejsze aspekty.";
                    break;
            }
            
            // Dodaj informację o repozytorium do wiadomości
            $repo_info_prompt = "\n\nTyp repozytorium: " . ucfirst($task_data['repo_type']);
            
            if (!empty($task_data['directory_path'])) {
                $repo_info_prompt .= "\nAnalizowany katalog: " . $task_data['directory_path'];
            }
            
            $adjusted_prompt = $task_data['prompt'] . $depth_prompt . $repo_info_prompt;
            
            // Podziel pliki na mniejsze partie - maksymalnie 3 plików na partię dla lepszej wydajności
            $batch_size = 3; // Zmniejszona z 5 na 3
            $batches = array_chunk($repo_data['files'], $batch_size);
            
            $all_responses = [];
            
            // Analizuj partie plików
            foreach ($batches as $index => $batch) {
                $batch_number = $index + 1;
                $batch_prompt = $adjusted_prompt . "\n\nTo jest partia " . $batch_number . " z " . count($batches) . ".";
                
                // Aktualizuj informacje o postępie
                $progress_percent = 20 + (70 * ($index / count($batches)));
                $task_data['status'] = 'analyzing_batch';
                $task_data['message'] = 'Analizowanie partii ' . $batch_number . ' z ' . count($batches) . '...';
                $task_data['progress'] = min(90, round($progress_percent));
                $task_data['processed_files'] = $index * $batch_size;
                
                // Aktualizuj aktualnie przetwarzany plik
                if (!empty($batch[0]['name'])) {
                    $task_data['current_file'] = $batch[0]['name'];
                }
                
                update_option('gca_task_' . $task_id, $task_data);
                
                // Sprawdź, czy zadanie nie zostało anulowane
                $updated_task_data = get_option('gca_task_' . $task_id);
                if ($updated_task_data['status'] === 'cancelled') {
                    // Zadanie zostało anulowane, przerwij przetwarzanie
                    error_log('GCA: Zadanie zostało anulowane przez użytkownika. Task ID: ' . $task_id);
                    return;
                }
                
                try {
                    // Zastosuj optymalizację dla wyświetlania dużych plików
                    foreach ($batch as &$file) {
                        if (strlen($file['content']) > 30000) {
                            $file['content'] = GitHub_Claude_Optimizer::limit_code_display($file['content']);
                        }
                    }
                    
                    // Analizuj partię z uwzględnieniem historii przetworzonych plików
                    $response = $claude_api->analyze_files($batch_prompt, $batch, $task_data['repo_url']);
                    
                    // Sprawdź, czy odpowiedź zawiera informację o przetworzonych plikach
                    $has_processed_files = strpos($response, 'Wcześniej przeanalizowane pliki') !== false;
                    
                    $all_responses[] = [
                        'batch_number' => $batch_number,
                        'response' => $response,
                        'files' => array_map(function($file) {
                            return $file['name'];
                        }, $batch),
                        'has_processed_files' => $has_processed_files
                    ];
                    
                    // Dodaj opóźnienie między zapytaniami, aby uniknąć limitów API
                    if ($index < count($batches) - 1) {
                        sleep(2); // Zwiększ opóźnienie między zapytaniami
                    }
                } catch (Exception $e) {
                    // Zapisz informacje o błędzie, ale kontynuuj przetwarzanie
                    $all_responses[] = [
                        'batch_number' => $batch_number,
                        'response' => 'Błąd podczas analizy tej partii: ' . $e->getMessage(),
                        'files' => array_map(function($file) {
                            return $file['name'];
                        }, $batch),
                        'error' => true
                    ];
                    
                    // Zapisz szczegóły błędu
                    error_log('GCA Analysis Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
                    
                    // Dodaj dłuższe opóźnienie po błędzie, aby ustabilizować system
                    sleep(5); // Zwiększ opóźnienie po błędzie
                }
                
                // Sprawdź, czy zadanie nie zostało anulowane po przetworzeniu partii
                $updated_task_data = get_option('gca_task_' . $task_id);
                if ($updated_task_data['status'] === 'cancelled') {
                    error_log('GCA: Zadanie zostało anulowane przez użytkownika po przetworzeniu partii. Task ID: ' . $task_id);
                    return;
                }
            }
            
            // Sprawdź, czy mamy jakiekolwiek udane odpowiedzi
            $successful_responses = array_filter($all_responses, function($response) {
                return !isset($response['error']) || !$response['error'];
            });
            
            if (empty($successful_responses)) {
                throw new Exception('Nie udało się przeanalizować żadnej partii plików. Spróbuj zmniejszyć liczbę plików lub użyć bardziej szczegółowych filtrów.');
            }
            
            // Zapisz wyniki analizy do bazy danych
            global $wpdb;
            $table_logs = $wpdb->prefix . 'claude_logs';
            
            $wpdb->insert(
                $table_logs,
                [
                    'user_id' => $task_data['user_id'],
                    'repository_url' => $task_data['repo_url'],
                    'prompt' => $adjusted_prompt,
                    'file_filters' => maybe_serialize($task_data['file_filters']),
                    'max_files' => $task_data['max_files'],
                    'total_files' => count($repo_data['files']),
                    'status' => 'completed',
                    'response' => maybe_serialize($all_responses),
                    'created_at' => current_time('mysql')
                ],
                ['%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s']
            );
            
            $log_id = $wpdb->insert_id;
            
            // Pobierz informacje o przetworzonych plikach
            $processed_files_count = $claude_api->get_processed_files_count();
            $processed_files_history = $claude_api->get_processed_files_history($task_data['repo_url']);
            
            // Przygotuj wyniki
            $results = [
                'log_id' => $log_id,
                'repository_url' => $task_data['repo_url'],
                'repository_type' => $task_data['repo_type'],
                'total_files' => count($repo_data['files']),
                'filtered_extensions' => $task_data['file_filters'],
                'analyses' => $all_responses,
                'log' => $repo_data['log'],
                'processed_files_count' => $processed_files_count,
                'processed_files_history' => $processed_files_history,
                'analysis_depth' => $task_data['analysis_depth'],
                'directory_path' => $task_data['directory_path']
            ];
            
            // Oznacz zadanie jako zakończone
            $task_data['status'] = 'completed';
            $task_data['message'] = 'Analiza zakończona pomyślnie';
            $task_data['progress'] = 100;
            $task_data['processed_files'] = count($repo_data['files']);
            $task_data['results'] = $results;
            $task_data['completed'] = true;
            
            update_option('gca_task_' . $task_id, $task_data);
            
        } catch (Exception $e) {
            // Zapisz informacje o błędzie
            $task_data['status'] = 'error';
            $task_data['message'] = 'Błąd: ' . $e->getMessage();
            $task_data['error'] = [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
            $task_data['completed'] = true;
            
            update_option('gca_task_' . $task_id, $task_data);
            
            error_log('GitHub Claude Analyzer Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }
}