<?php
/**
 * Klasa do obsługi API Claude.
 */

// Bezpieczeństwo: Zapobieganie bezpośredniemu dostępowi do pliku
if (!defined('ABSPATH')) {
    exit('Bezpośredni dostęp zabroniony.');
}

/**
 * Klasa do obsługi API Claude.
 */
class GitHub_Claude_API {

    /**
     * Klucz API Claude.
     */
    private $api_key;

    /**
     * Wybrany model Claude.
     */
    private $model;

    /**
     * Domyślne modele Claude w kolejności preferencji.
     */
    private $preferred_models = [
        'claude-3-7-sonnet-20250219', // Najnowszy model, preferowany
        'claude-3-5-sonnet-20240620', 
        'claude-3-opus-20240229',
        'claude-3-sonnet-20240229',
        'claude-3-haiku-20240307'
    ];

    /**
     * Maksymalna liczba tokenów do wygenerowania.
     */
    private $max_tokens;

    /**
     * Konstruktor.
     */
    public function __construct($api_key = null, $model = null) {
        $settings = get_option('gca_settings', array());

        $this->api_key = $api_key ?: $this->get_api_key();
        
        // Pobierz dostępne modele
        $available_models = $this->get_available_models();
        
        // Ustaw model według priorytetów
        if ($model && in_array($model, $available_models)) {
            // Użyj podanego modelu, jeśli jest dostępny
            $this->model = $model;
        } elseif (isset($settings['claude_model']) && in_array($settings['claude_model'], $available_models)) {
            // Użyj zapisanego modelu, jeśli jest dostępny
            $this->model = $settings['claude_model'];
        } else {
            // Wybierz pierwszy dostępny model z listy preferowanych
            $this->model = $this->get_best_available_model($available_models);
        }
        
        // Upewnij się, że max_tokens jest prawidłową liczbą całkowitą
        $max_tokens = isset($settings['max_tokens']) ? (int)$settings['max_tokens'] : 4000;
        if ($max_tokens < 1 || $max_tokens > 100000) {
            $max_tokens = 4000;
        }
        $this->max_tokens = $max_tokens;
    }

    /**
     * Wybiera najlepszy dostępny model Claude na podstawie listy preferencji.
     */
    private function get_best_available_model($available_models) {
        if (empty($available_models)) {
            return $this->preferred_models[0]; // Domyślny model, nawet jeśli nie jest dostępny
        }
        
        // Przeglądaj listę preferowanych modeli w kolejności
        foreach ($this->preferred_models as $preferred_model) {
            if (in_array($preferred_model, $available_models)) {
                return $preferred_model;
            }
        }
        
        // Jeśli żaden z preferowanych modeli nie jest dostępny, użyj pierwszego dostępnego
        return $available_models[0];
    }

    /**
     * Pobiera klucz API z bazy danych dla bieżącego użytkownika.
     * 
     * @return string|null Klucz API lub null, jeśli nie znaleziono.
     */
    public function get_api_key() {
        global $wpdb;
        
        try {
            // Nazwa tabeli z użyciem prawidłowego prefixu
            $table_name = $wpdb->prefix . 'claude_api_keys';
            
            // Sprawdź, czy tabela istnieje
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $table_name
            ));
            
            if (!$table_exists) {
                error_log('GCA API Key Error: Tabela ' . $table_name . ' nie istnieje w bazie danych.');
                return null;
            }
            
            // Pobierz ID bieżącego użytkownika
            $user_id = get_current_user_id();
            
            if ($user_id > 0) {
                // Pobierz klucz API dla bieżącego użytkownika
                $api_key = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT api_key FROM {$table_name} WHERE user_id = %d ORDER BY updated_at DESC LIMIT 1",
                        $user_id
                    )
                );
                
                if (!empty($api_key)) {
                    return $api_key;
                }
            }
            
            // Jeśli nie znaleziono klucza dla bieżącego użytkownika lub użytkownik nie jest zalogowany,
            // pobierz najnowszy klucz z bazy danych (wg id DESC)
            $api_key = $wpdb->get_var(
                "SELECT api_key FROM {$table_name} ORDER BY id DESC LIMIT 1"
            );
            
            if (empty($api_key)) {
                error_log('GCA API Key Warning: Nie znaleziono klucza API Claude w bazie danych.');
            }
            
            return $api_key;
            
        } catch (Exception $e) {
            error_log('GCA API Key Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return null;
        }
    }

    /**
     * Pobiera model dla bieżącego użytkownika.
     */
    public function get_model() {
        global $wpdb;
        
        try {
            $table_name = $wpdb->prefix . 'claude_api_keys';
            $user_id = get_current_user_id();
            
            // Sprawdź, czy tabela istnieje przed próbą zapytania
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $table_name
            ));
            
            if (!$table_exists) {
                error_log('GCA Model Error: Tabela ' . $table_name . ' nie istnieje w bazie danych.');
                return $this->model; // Zwróć domyślny model
            }
            
            // Pobierz model dla bieżącego użytkownika
            $model = $wpdb->get_var(
                $wpdb->prepare("SELECT model FROM {$table_name} WHERE user_id = %d", $user_id)
            );
            
            return $model ?: $this->model;
            
        } catch (Exception $e) {
            error_log('GCA Model Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return $this->model; // W przypadku błędu zwróć domyślny model
        }
    }

    /**
     * Pobiera listę dostępnych modeli Claude z dostępnych źródeł.
     * Zmniejszono czas cache z 24h do 1h.
     */
    public function get_available_models() {
        // Sprawdź, czy mamy buforowane modele
        $cached_models = get_transient('gca_available_claude_models');
        if ($cached_models !== false) {
            return $cached_models;
        }
        
        // Lista znanych modeli Claude
        $models = array(
            'claude-3-7-sonnet-20250219',  // Najnowszy model
            'claude-3-5-sonnet-20240620',
            'claude-3-opus-20240229',
            'claude-3-sonnet-20240229',
            'claude-3-haiku-20240307',
            'claude-2.1',
            'claude-2.0',
            'claude-instant-1.2'
        );
        
        // Próbuje zweryfikować które modele są dostępne (opcjonalne)
        if (!empty($this->api_key)) {
            $verified_models = [];
            
            // Przetestuj najważniejsze modele
            $test_models = ['claude-3-7-sonnet-20250219', 'claude-3-5-sonnet-20240620', 'claude-3-opus-20240229'];
            
            foreach ($test_models as $model) {
                $data = [
                    'model' => $model,
                    'max_tokens' => 10,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => 'Say "test" and nothing else.'
                        ]
                    ]
                ];
                
                $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'x-api-key' => $this->api_key,
                        'anthropic-version' => '2023-06-01'
                    ],
                    'body' => json_encode($data),
                    'timeout' => 5 // Krótki timeout, tylko dla testów
                ]);
                
                if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                    $verified_models[] = $model;
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('GCA Model Check: Confirmed working model: ' . $model);
                    }
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        $code = is_wp_error($response) ? 'WP_Error' : wp_remote_retrieve_response_code($response);
                        error_log('GCA Model Check: Model not available: ' . $model . ' (code: ' . $code . ')');
                    }
                }
            }
            
            // Jeśli znaleziono działające modele, użyj tylko ich
            if (!empty($verified_models)) {
                $models = array_merge($verified_models, array_diff($models, $test_models));
            }
        }
        
        // Zapisz listę w buforze na 1 godzinę zamiast 24 godzin
        set_transient('gca_available_claude_models', $models, 1 * HOUR_IN_SECONDS);
        
        return $models;
    }

    /**
     * Zwraca opis modelu Claude na podstawie jego nazwy
     */
    public function get_model_description($model_name) {
        $descriptions = [
            'claude-3-7-sonnet-20250219' => [
                'name' => 'Claude 3.7 Sonnet',
                'description' => 'Najnowszy model Claude, oferujący zrównoważone podejście do analizy kodu.',
                'strengths' => 'Rozumienie kontekstu, analiza kodu, znajdowanie błędów',
                'recommended_for' => 'Większości przypadków użycia, analizy średniej wielkości repozytoriów',
                'speed' => 'Szybki',
                'size' => 'Średni',
                'is_premium' => true
            ],
            'claude-3-5-sonnet-20240620' => [
                'name' => 'Claude 3.5 Sonnet',
                'description' => 'Zrównoważony model, dobry do większości zadań analizy kodu.',
                'strengths' => 'Dobra wydajność, zrównoważone odpowiedzi',
                'recommended_for' => 'Standardowej analizy kodu i zadań programistycznych',
                'speed' => 'Średni',
                'size' => 'Średni',
                'is_premium' => false
            ],
            'claude-3-opus-20240229' => [
                'name' => 'Claude 3 Opus',
                'description' => 'Najbardziej zaawansowany model o największych możliwościach analizy kodu.',
                'strengths' => 'Głęboka analiza, rozpoznawanie złożonych wzorców',
                'recommended_for' => 'Złożonych projektów, dogłębnej analizy architektonicznej',
                'speed' => 'Wolny',
                'size' => 'Duży',
                'is_premium' => true
            ],
            'claude-3-sonnet-20240229' => [
                'name' => 'Claude 3 Sonnet',
                'description' => 'Wydajny model oferujący dobry balans między jakością a szybkością.',
                'strengths' => 'Ogólna analiza kodu, zrównoważone podejście',
                'recommended_for' => 'Standardowych zadań analizy kodu',
                'speed' => 'Szybki',
                'size' => 'Średni',
                'is_premium' => false
            ],
            'claude-3-haiku-20240307' => [
                'name' => 'Claude 3 Haiku',
                'description' => 'Najszybszy model, idealny do szybkiej analizy.',
                'strengths' => 'Szybkość, efektywność',
                'recommended_for' => 'Małych repozytoriów, szybkich odpowiedzi',
                'speed' => 'Bardzo szybki',
                'size' => 'Mały',
                'is_premium' => false
            ]
        ];
        
        if (isset($descriptions[$model_name])) {
            return $descriptions[$model_name];
        }
        
        // Domyślny opis dla nieznanych modeli
        return [
            'name' => ucfirst(str_replace('-', ' ', $model_name)),
            'description' => 'Model Claude AI',
            'strengths' => 'Brak szczegółowych informacji',
            'recommended_for' => 'Ogólnych zadań analizy',
            'speed' => 'Nieznany',
            'size' => 'Nieznany',
            'is_premium' => false
        ];
    }

    /**
     * Pobiera informacje o stanie konta Claude.
     */
    public function get_account_info() {
        // Używamy hardcodowanej listy modeli
        $available_models = $this->get_available_models();
        
        if (empty($this->api_key)) {
            return ['error' => 'Klucz API nie jest ustawiony.'];
        }
        
        // Uproszczona odpowiedź zawierająca podstawowe informacje
        return [
            'status' => 'Aktywne',
            'models' => $available_models,
            'current_model' => $this->model,
            'model_available' => in_array($this->model, $available_models),
            'max_tokens' => $this->max_tokens,
            'api_working' => true
        ];
    }

    /**
     * Sprawdza, czy plik był już wcześniej przetworzony.
     * 
     * @param string $repository_url URL repozytorium
     * @param string $file_path Ścieżka do pliku
     * @param string $file_content Zawartość pliku
     * @return array Informacje o przetworzonym pliku lub null
     */
    public function get_processed_file($repository_url, $file_path, $file_content) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'claude_processed_files';
        $user_id = get_current_user_id();
        
        try {
            // Sprawdź czy tabela istnieje
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $table_name
            ));
            
            if (!$table_exists) {
                $this->create_processed_files_table();
            }
            
            // Oblicz hash zawartości pliku
            $content_hash = md5($file_content);
            
            // Sprawdź, czy plik był już wcześniej przetworzony
            $result = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table_name} 
                     WHERE user_id = %d 
                     AND repository_url = %s 
                     AND file_path = %s
                     LIMIT 1",
                    $user_id, $repository_url, $file_path
                ),
                ARRAY_A
            );
            
            if ($result) {
                // Jeśli plik był już przetworzony, sprawdź czy zawartość się zmieniła
                if ($result['content_hash'] === $content_hash) {
                    // Zawartość się nie zmieniła, zwróć informacje o przetworzonym pliku
                    return $result;
                }
                // Zawartość się zmieniła, zaktualizuj hash i zwróć null, aby ponownie przetworzyć plik
                $wpdb->update(
                    $table_name,
                    ['content_hash' => $content_hash, 'processed_at' => current_time('mysql')],
                    ['id' => $result['id']],
                    ['%s', '%s'],
                    ['%d']
                );
                return null;
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log('GCA Processed File Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return null;
        }
    }

    /**
     * Zapisuje informacje o przetworzonym pliku.
     * 
     * @param string $repository_url URL repozytorium
     * @param string $file_path Ścieżka do pliku
     * @param string $file_content Zawartość pliku
     * @param string $analysis Analiza pliku
     * @return bool Czy operacja się powiodła
     */
    public function save_processed_file($repository_url, $file_path, $file_content, $analysis) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'claude_processed_files';
        $user_id = get_current_user_id();
        
        try {
            // Sprawdź czy tabela istnieje
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $table_name
            ));
            
            if (!$table_exists) {
                $this->create_processed_files_table();
            }
            
            // Oblicz hash zawartości pliku
            $content_hash = md5($file_content);
            
            // Sprawdź, czy plik był już wcześniej przetworzony
            $existing_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$table_name} 
                     WHERE user_id = %d 
                     AND repository_url = %s 
                     AND file_path = %s
                     LIMIT 1",
                    $user_id, $repository_url, $file_path
                )
            );
            
            if ($existing_id) {
                // Aktualizuj istniejący wpis
                return $wpdb->update(
                    $table_name,
                    [
                        'content_hash' => $content_hash,
                        'analysis' => $analysis,
                        'processed_at' => current_time('mysql')
                    ],
                    ['id' => $existing_id],
                    ['%s', '%s', '%s'],
                    ['%d']
                );
            } else {
                // Dodaj nowy wpis
                return $wpdb->insert(
                    $table_name,
                    [
                        'user_id' => $user_id,
                        'repository_url' => $repository_url,
                        'file_path' => $file_path,
                        'content_hash' => $content_hash,
                        'analysis' => $analysis,
                        'processed_at' => current_time('mysql')
                    ],
                    ['%d', '%s', '%s', '%s', '%s', '%s']
                );
            }
        } catch (Exception $e) {
            error_log('GCA Save Processed File Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Tworzy tabelę przetworzonych plików, jeśli nie istnieje.
     */
    private function create_processed_files_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'claude_processed_files';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            repository_url varchar(255) NOT NULL,
            file_path varchar(255) NOT NULL,
            content_hash varchar(32) NOT NULL,
            analysis longtext NOT NULL,
            processed_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY user_repo (user_id, repository_url),
            KEY file_path (file_path)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Pobiera historię przetworzonych plików dla bieżącego użytkownika.
     * 
     * @param string $repository_url Opcjonalny URL repozytorium do filtrowania
     * @return array Lista przetworzonych plików
     */
    public function get_processed_files_history($repository_url = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'claude_processed_files';
        $user_id = get_current_user_id();
        
        try {
            // Sprawdź czy tabela istnieje
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $table_name
            ));
            
            if (!$table_exists) {
                return [];
            }
            
            // Przygotuj zapytanie
            $query = "SELECT repository_url, file_path, processed_at 
                      FROM {$table_name} 
                      WHERE user_id = %d";
            $params = [$user_id];
            
            // Dodaj filtrowanie według repozytorium, jeśli podano
            if ($repository_url) {
                $query .= " AND repository_url = %s";
                $params[] = $repository_url;
            }
            
            // Dodaj sortowanie i limit
            $query .= " ORDER BY processed_at DESC LIMIT 100";
            
            // Wykonaj zapytanie
            $results = $wpdb->get_results(
                $wpdb->prepare($query, $params),
                ARRAY_A
            );
            
            return $results ?: [];
            
        } catch (Exception $e) {
            error_log('GCA Get Processed Files History Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return [];
        }
    }

    /**
     * Usuwa historię przetworzonych plików dla podanego repozytorium.
     * 
     * @param string $repository_url URL repozytorium
     * @return int Liczba usuniętych wpisów
     */
    public function clear_processed_files_history($repository_url) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'claude_processed_files';
        $user_id = get_current_user_id();
        
        try {
            // Sprawdź czy tabela istnieje
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $table_name
            ));
            
            if (!$table_exists) {
                return 0;
            }
            
            // Usuń wpisy dla podanego repozytorium
            return $wpdb->delete(
                $table_name,
                [
                    'user_id' => $user_id,
                    'repository_url' => $repository_url
                ],
                ['%d', '%s']
            );
            
        } catch (Exception $e) {
            error_log('GCA Clear Processed Files History Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return 0;
        }
    }

    /**
     * Wysyła zapytanie do Claude API.
     */
    public function analyze_files($prompt, $files, $repository_url = '') {
        // Zwiększ limity czasu i pamięci
        @ini_set('max_execution_time', 600);
        @set_time_limit(600);
        @ini_set('memory_limit', '512M');
        
        try {
            // Przygotuj wiadomość z plikami
            $message_text = $prompt . "\n\n";
            
            // Oblicz szacowaną długość wiadomości
            $total_chars = strlen($message_text);
            $files_to_include = [];
            $max_message_length = 50000; // Zmniejszona maksymalna długość wiadomości dla lepszej wydajności
            
            // Sprawdź, które pliki były już wcześniej przetworzone, a które potrzebują analizy
            $new_files = [];
            $previously_processed_files = [];
            
            foreach ($files as $file) {
                // Sprawdź, czy plik był już wcześniej przetworzony
                $processed = $this->get_processed_file($repository_url, $file['name'], $file['content']);
                
                if ($processed) {
                    // Plik był już wcześniej przetworzony i nie zmienił się
                    $previously_processed_files[] = [
                        'name' => $file['name'],
                        'analysis' => $processed['analysis'],
                        'processed_at' => $processed['processed_at']
                    ];
                } else {
                    // Plik jest nowy lub zmienił się, dodaj do analizy
                    $new_files[] = $file;
                }
            }
            
            // Jeśli wszystkie pliki były już wcześniej przetworzone, zwróć ich analizy
            if (empty($new_files) && !empty($previously_processed_files)) {
                $combined_analysis = "# Analiza repozytorium\n\n";
                $combined_analysis .= "Wszystkie pliki zostały już wcześniej przeanalizowane:\n\n";
                
                foreach ($previously_processed_files as $processed) {
                    $combined_analysis .= "## PLIK: " . $processed['name'] . "\n";
                    $combined_analysis .= $processed['analysis'] . "\n\n";
                }
                
                return $combined_analysis;
            }
            
            // Sortuj pliki według rozmiaru - najpierw mniejsze
            usort($new_files, function($a, $b) {
                return strlen($a['content']) - strlen($b['content']);
            });
            
            // Dodaj pliki do wiadomości, priorytetyzując mniejsze pliki
            foreach ($new_files as $file) {
                // Ograniczanie długości zawartości pliku jeśli przekracza limit
                if (strlen($file['content']) > 100000) {
                    $file['content'] = substr($file['content'], 0, 50000) . 
                        "\n\n... [Plik został skrócony ze względu na duży rozmiar. Ta część została pominięta.] ...\n\n" . 
                        substr($file['content'], -50000);
                }
                
                // Szacuj długość zawartości pliku
                $file_content_length = strlen("## PLIK: " . $file['name'] . "\n```\n" . $file['content'] . "\n```\n\n");
                
                // Sprawdź, czy dodanie tego pliku nie przekroczy limitu
                if ($total_chars + $file_content_length > $max_message_length) {
                    $this->log_error('Pomiń plik ' . $file['name'] . ' z powodu ograniczenia długości wiadomości', 
                        ['message_length' => $total_chars, 'file_length' => $file_content_length]);
                    continue;
                }
                
                // Dodaj plik do wiadomości
                $message_text .= "## PLIK: " . $file['name'] . "\n```\n" . $file['content'] . "\n```\n\n";
                $total_chars += $file_content_length;
                $files_to_include[] = $file;
            }
            
            // Dodaj podsumowanie włączonych plików
            $message_text .= "\n## Przeanalizowane pliki:\n";
            foreach ($files_to_include as $file) {
                $message_text .= "- " . $file['name'] . "\n";
            }
            
            // Dodaj informację o wcześniej przetworzonych plikach
            if (!empty($previously_processed_files)) {
                $message_text .= "\n## Wcześniej przeanalizowane pliki (nie uwzględnione w tej analizie):\n";
                foreach ($previously_processed_files as $processed) {
                    $message_text .= "- " . $processed['name'] . " (przeanalizowany " . $processed['processed_at'] . ")\n";
                }
            }
            
            // Zapisz log
            $this->log_error('Wysyłanie analizy', [
                'files_count' => count($files_to_include), 
                'processed_files' => count($previously_processed_files), 
                'message_length' => $total_chars
            ]);
            
           // Jeśli nie ma nowych plików do analizy, zwróć informację
           if (empty($files_to_include)) {
            return "Brak nowych plików do analizy. Wszystkie pliki zostały już wcześniej przeanalizowane.";
        }
        
        // Upewnij się, że max_tokens jest prawidłową liczbą całkowitą
        $max_tokens = isset($this->max_tokens) ? (int)$this->max_tokens : 4000;
        
        // Upewnij się, że max_tokens mieści się w dozwolonym zakresie (np. 1-100000)
        if ($max_tokens < 1 || $max_tokens > 100000) {
            $max_tokens = 4000; // Domyślna wartość, jeśli poza zakresem
        }
        
        // Wyświetl informacje debugowania
        $this->log_error('Debug GCA', [
            'max_tokens' => $max_tokens, 
            'token_type' => gettype($max_tokens),
            'model' => $this->model
        ]);
        
        // Przygotuj dane do wysłania
        $data = [
            'model' => $this->model,
            'max_tokens' => $max_tokens,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $message_text
                ]
            ]
        ];
        
        // Dodaj informację o rozpoczęciu żądania
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GCA API: Sending request to Claude API, message length: ' . strlen($message_text) . ' bytes');
        }
        
        // Wyślij zapytanie do API
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => $this->api_key,
                'anthropic-version' => '2023-06-01'
            ],
            'body' => json_encode($data),
            'timeout' => 120, // Zwiększ timeout dla większych żądań
            'httpversion' => '1.1',
            'sslverify' => true
        ]);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->log_error('API Claude Error: ' . $error_message, ['data' => [
                'model' => $data['model'],
                'max_tokens' => $data['max_tokens'],
                'message_length' => strlen($message_text)
            ]]);
            throw new Exception($error_message);
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($code !== 200) {
            $error_message = "Błąd API Claude (kod: $code): " . $body;
            $error_data = json_decode($body, true);
            $this->log_error('API Error Response', ['code' => $code, 'body' => $body, 'error_data' => $error_data]);
            
            // Jeśli jest to błąd modelu, spróbuj z innym modelem
            if ($code === 404 && isset($error_data['error']['message']) && strpos($error_data['error']['message'], 'model') !== false) {
                $this->log_error('Błąd modelu, próbuję z alternatywnym modelem', ['current_model' => $this->model]);
                
                // Pobierz dostępne modele
                $available_models = $this->get_available_models();
                
                // Wybierz alternatywny model
                $alternative_model = null;
                
               // Usuń bieżący model z listy dostępnych modeli
               $other_models = array_diff($available_models, [$this->model]);
                
               // Wybierz najlepszy model z pozostałych dostępnych
               foreach ($this->preferred_models as $preferred_model) {
                   if (in_array($preferred_model, $other_models)) {
                       $alternative_model = $preferred_model;
                       break;
                   }
               }
               
               // Jeśli nie znaleziono preferowanego modelu, użyj pierwszego dostępnego
               if (!$alternative_model && !empty($other_models)) {
                   $alternative_model = reset($other_models);
               }
               
               if ($alternative_model) {
                   $this->log_error('Próbuję z modelem: ' . $alternative_model);
                   
                   // Zaktualizuj dane żądania
                   $data['model'] = $alternative_model;
                   
                   // Ponów żądanie
                   $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
                       'headers' => [
                           'Content-Type' => 'application/json',
                           'x-api-key' => $this->api_key,
                           'anthropic-version' => '2023-06-01'
                       ],
                       'body' => json_encode($data),
                       'timeout' => 120,
                       'httpversion' => '1.1',
                       'sslverify' => true
                   ]);
                   
                   if (is_wp_error($response)) {
                       $error_message = $response->get_error_message();
                       $this->log_error('Alternative API Claude Error: ' . $error_message);
                       throw new Exception($error_message);
                   }
                   
                   $code = wp_remote_retrieve_response_code($response);
                   $body = wp_remote_retrieve_body($response);
                   
                   if ($code === 200) {
                       // Sukces z alternatywnym modelem
                       $this->update_user_model($alternative_model);
                       
                       $response_data = json_decode($body, true);
                       $analysis_text = $response_data['content'][0]['text'];
                       
                       // Dodaj informację o zmianie modelu do wyniku
                       $analysis_text = "**Uwaga:** Analiza została wykonana z użyciem modelu ${alternative_model}, ponieważ wybrany model był niedostępny.\n\n" . $analysis_text;
                       
                       // Zapisz analizę dla każdego nowego pliku
                       foreach ($files_to_include as $file) {
                           $this->save_processed_file($repository_url, $file['name'], $file['content'], $analysis_text);
                       }
                       
                       return $analysis_text;
                   } else {
                       $this->log_error('Alternatywny model również nie działa', ['code' => $code, 'body' => $body]);
                   }
               }
           }
           
           // Obsługuj konkretne kody błędów
           if ($code === 401) {
               $error_message = "Nieprawidłowy klucz API Claude. Sprawdź swoje ustawienia.";
           } elseif ($code === 403) {
               $error_message = "Brak uprawnień do API Claude. Sprawdź swój klucz API.";
           } elseif ($code === 429) {
               $error_message = "Przekroczono limit żądań API Claude. Spróbuj ponownie później.";
           } elseif ($code === 529) {
               $error_message = "Serwery Claude są przeciążone. Spróbuj ponownie później.";
           }
           
           throw new Exception($error_message);
       }
       
       $response_data = json_decode($body, true);
       
       if (!isset($response_data['content']) || !isset($response_data['content'][0]['text'])) {
           $this->log_error('Nieprawidłowa odpowiedź API', ['response' => $response_data]);
           throw new Exception('Nieprawidłowa odpowiedź z API Claude. Brak zawartości w odpowiedzi.');
       }
       
       $analysis_text = $response_data['content'][0]['text'];
       
       // Zapisz analizę dla każdego nowego pliku
       foreach ($files_to_include as $file) {
           $this->save_processed_file($repository_url, $file['name'], $file['content'], $analysis_text);
       }
       
       // Połącz analizę nowych plików z wcześniej przetworzonymi
       if (!empty($previously_processed_files)) {
           $combined_analysis = $analysis_text . "\n\n";
           $combined_analysis .= "# Wcześniej przeanalizowane pliki\n\n";
           
           foreach ($previously_processed_files as $processed) {
               $combined_analysis .= "## PLIK: " . $processed['name'] . "\n";
               $combined_analysis .= $processed['analysis'] . "\n\n";
           }
           
           return $combined_analysis;
       }
       
       return $analysis_text;
   } catch (Exception $e) {
       $this->log_error('Exception w analyze_files: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
       throw $e;
   }
}

/**
* Aktualizuje model dla bieżącego użytkownika.
*/
private function update_user_model($new_model) {
   global $wpdb;
   
   try {
       $table_name = $wpdb->prefix . 'claude_api_keys';
       $user_id = get_current_user_id();
       
       // Sprawdź czy tabela istnieje
       $table_exists = $wpdb->get_var($wpdb->prepare(
           "SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
           DB_NAME,
           $table_name
       ));
       
       if (!$table_exists) {
           error_log('GCA Update Model Error: Tabela ' . $table_name . ' nie istnieje.');
           return false;
       }
       
       $result = $wpdb->update(
           $table_name,
           array('model' => $new_model),
           array('user_id' => $user_id),
           array('%s'),
           array('%d')
       );
       
       if ($result === false) {
           error_log('GCA Update Model Error: Błąd aktualizacji modelu: ' . $wpdb->last_error);
       } else {
           error_log('GCA: Zaktualizowano model użytkownika na: ' . $new_model);
       }
       
       return $result !== false;
   } catch (Exception $e) {
       error_log('GCA Update Model Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
       return false;
   }
}

/**
* Testuje połączenie z API Claude.
*/
public function test_connection() {
   if (empty($this->api_key)) {
       return [
           'success' => false,
           'message' => 'Klucz API Claude nie jest ustawiony. Wprowadź klucz API w ustawieniach.'
       ];
   }

   try {
       // Wykonaj proste zapytanie testowe
       $data = [
           'model' => $this->model,
           'max_tokens' => 10,
           'messages' => [
               [
                   'role' => 'user',
                   'content' => 'Say "test" and nothing else.'
               ]
           ]
       ];
       
       // Log przed wykonaniem zapytania
       if (defined('WP_DEBUG') && WP_DEBUG) {
           error_log('GCA Test Connection: Sending test request with model: ' . $this->model);
       }
       
       $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
           'headers' => [
               'Content-Type' => 'application/json',
               'x-api-key' => $this->api_key,
               'anthropic-version' => '2023-06-01'
           ],
           'body' => json_encode($data),
           'timeout' => 30
       ]);
       
       if (is_wp_error($response)) {
           $error_message = $response->get_error_message();
           error_log('GCA API Error: ' . $error_message);
           return [
               'success' => false,
               'message' => 'Błąd połączenia: ' . $error_message
           ];
       }
       
       $code = wp_remote_retrieve_response_code($response);
       $body = wp_remote_retrieve_body($response);
       
       // Log odpowiedzi dla debugowania
       if (defined('WP_DEBUG') && WP_DEBUG) {
           error_log('GCA Test Response: HTTP ' . $code . ', Body: ' . substr($body, 0, 200) . '...');
       }
       
       if ($code !== 200) {
           $error_data = json_decode($body, true);
           $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : "Błąd API (kod: $code)";
           
           // Jeśli jest to błąd modelu, spróbuj z alternatywnym
           if ($code === 404 && strpos($body, 'model') !== false) {
               // Pobierz dostępne modele
               $available_models = $this->get_available_models();
               
               // Usuń bieżący model z listy dostępnych modeli
               $other_models = array_diff($available_models, [$this->model]);
               
               // Wyczyść bufor modeli, aby następnym razem pobrać aktualną listę
               delete_transient('gca_available_claude_models');
               
               if (!empty($other_models)) {
                   // Wybierz najlepszy model z pozostałych dostępnych
                   $alternative_model = $this->get_best_available_model($other_models);
                   
                   if ($alternative_model) {
                       $data['model'] = $alternative_model;
                       
                       // Log próby alternatywnej
                       if (defined('WP_DEBUG') && WP_DEBUG) {
                           error_log('GCA Test: Próba z alternatywnym modelem: ' . $alternative_model);
                       }
                       
                       $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
                           'headers' => [
                               'Content-Type' => 'application/json',
                               'x-api-key' => $this->api_key,
                               'anthropic-version' => '2023-06-01'
                           ],
                           'body' => json_encode($data),
                           'timeout' => 30
                       ]);
                       
                       if (is_wp_error($response)) {
                           return [
                               'success' => false,
                               'message' => 'Błąd połączenia z alternatywnym modelem: ' . $response->get_error_message()
                           ];
                       }
                       
                       $code = wp_remote_retrieve_response_code($response);
                       
                       if ($code === 200) {
                           // Znaleziono działający model, zaktualizuj preferencje użytkownika
                           $this->update_user_model($alternative_model);
                           
                           return [
                               'success' => true,
                               'message' => 'Połączenie z API Claude działa, ale wybrany model był niedostępny. Automatycznie zaktualizowano na model ' . $alternative_model . '.',
                               'account_info' => [
                                   'status' => 'Aktywne',
                                   'models' => $this->get_available_models(),
                                   'current_model' => $alternative_model,
                                   'model_available' => true,
                                   'max_tokens' => $this->max_tokens,
                                   'processed_files_count' => $this->get_processed_files_count()
                               ]
                           ];
                       } else {
                           $error_data = json_decode(wp_remote_retrieve_body($response), true);
                           $alt_error = isset($error_data['error']['message']) ? $error_data['error']['message'] : "Błąd alternatywnego modelu (kod: $code)";
                           
                           if (defined('WP_DEBUG') && WP_DEBUG) {
                               error_log('GCA Test: Alternatywny model również nie działa: ' . $alt_error);
                           }
                           
                           return [
                               'success' => false,
                               'message' => 'Wybrany model jest niedostępny, próba alternatywnego modelu również nie powiodła się. Błąd: ' . $alt_error
                           ];
                       }
                   }
               }
           }
           
           // Obsługuj konkretne kody błędów
           if ($code === 401) {
               $error_message = "Nieprawidłowy klucz API Claude. Sprawdź swoje ustawienia.";
           } elseif ($code === 403) {
               $error_message = "Brak uprawnień do API Claude. Sprawdź swój klucz API.";
           } elseif ($code === 429) {
               $error_message = "Przekroczono limit żądań API Claude. Spróbuj ponownie później.";
           } elseif ($code === 529) {
               $error_message = "Serwery Claude są przeciążone. Spróbuj ponownie później.";
           }
           
           return [
               'success' => false,
               'message' => "Błąd API Claude: $error_message"
           ];
       }
       
       // Pobierz listę dostępnych modeli
       $available_models = $this->get_available_models();
       
       return [
           'success' => true,
           'message' => 'Połączenie z API Claude działa poprawnie.',
           'account_info' => [
               'status' => 'Aktywne',
               'models' => $available_models,
               'current_model' => $this->model,
               'model_available' => in_array($this->model, $available_models),
               'max_tokens' => $this->max_tokens,
               'processed_files_count' => $this->get_processed_files_count()
           ]
       ];
   } catch (Exception $e) {
       error_log('GCA Connection Test Exception: ' . $e->getMessage());
       return [
           'success' => false,
           'message' => 'Błąd podczas testowania połączenia: ' . $e->getMessage()
       ];
   }
}

/**
* Pobiera liczbę przetworzonych plików.
*/
public function get_processed_files_count() {
   global $wpdb;
   
   try {
       $table_name = $wpdb->prefix . 'claude_processed_files';
       $user_id = get_current_user_id();
       
       // Sprawdź czy tabela istnieje
       $table_exists = $wpdb->get_var($wpdb->prepare(
           "SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
           DB_NAME,
           $table_name
       ));
       
       if (!$table_exists) {
           return 0;
       }
       
       return (int) $wpdb->get_var(
           $wpdb->prepare(
               "SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d",
               $user_id
           )
       );
   } catch (Exception $e) {
       error_log('GCA Get Processed Files Count Error: ' . $e->getMessage());
       return 0;
   }
}

/**
* Loguje szczegóły błędu z dodatkowymi informacjami.
*/
private function log_error($message, $context = []) {
   $error_log = [
       'time' => current_time('mysql'),
       'message' => $message,
       'context' => $context
   ];
   
   if (defined('WP_DEBUG') && WP_DEBUG) {
       $contextStr = is_string($context) ? $context : json_encode($context);
       error_log('GCA Error: ' . $message . ' | Kontekst: ' . $contextStr);
   }
   
   try {
       // Zapisz do bazy danych ostatnie błędy
       $recent_errors = get_option('gca_recent_errors', []);
       $recent_errors[] = $error_log;
       
       // Ogranicz liczbę zapisanych błędów
       if (count($recent_errors) > 10) {
           array_shift($recent_errors);
       }
       
       update_option('gca_recent_errors', $recent_errors);
   } catch (Exception $e) {
       error_log('GCA Error przy zapisywaniu błędu: ' . $e->getMessage());
   }
}

/**
* Metoda do wysyłania wiadomości czatu do Claude API
* 
* @param array $messages Poprzednie wiadomości (kontekst)
* @param string $new_message Nowa wiadomość
* @return array Odpowiedź z API
*/
public function send_chat_message($messages, $new_message) {
   // Zwiększ limity czasu i pamięci
   @ini_set('max_execution_time', 120);
   @set_time_limit(120);
   @ini_set('memory_limit', '256M');
   
   try {
       // Przygotuj tablicę wiadomości do API
       $message_array = [];
       
       // Dodaj kontekst (poprzednie wiadomości)
       foreach ($messages as $msg) {
           $message_array[] = [
               'role' => $msg['role'],
               'content' => $msg['content']
           ];
       }
       
       // Dodaj nową wiadomość użytkownika
       $message_array[] = [
           'role' => 'user',
           'content' => $new_message
       ];
       
       // Przygotuj dane do wysłania
       $data = [
           'model' => $this->model,
           'max_tokens' => $this->max_tokens,
           'messages' => $message_array
       ];
       
       // Wyślij zapytanie do API
       $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
           'headers' => [
               'Content-Type' => 'application/json',
               'x-api-key' => $this->api_key,
               'anthropic-version' => '2023-06-01'
           ],
           'body' => json_encode($data),
           'timeout' => 60, // Zwiększ timeout dla odpowiedzi
           'httpversion' => '1.1',
           'sslverify' => true
       ]);
       
       if (is_wp_error($response)) {
           $error_message = $response->get_error_message();
           $this->log_error('API Claude Error: ' . $error_message, ['data' => [
               'model' => $data['model'],
               'max_tokens' => $data['max_tokens'],
               'message_count' => count($message_array)
           ]]);
           throw new Exception($error_message);
       }
       
       $code = wp_remote_retrieve_response_code($response);
       $body = wp_remote_retrieve_body($response);
       
       if ($code !== 200) {
           $error_message = "Błąd API Claude (kod: $code): " . $body;
           $error_data = json_decode($body, true);
           $this->log_error('API Error Response', ['code' => $code, 'body' => $body, 'error_data' => $error_data]);
           
           throw new Exception($error_message);
       }
       
       $response_data = json_decode($body, true);
       
       if (!isset($response_data['content']) || !isset($response_data['content'][0]['text'])) {
           $this->log_error('Nieprawidłowa odpowiedź API', ['response' => $response_data]);
           throw new Exception('Nieprawidłowa odpowiedź z API Claude. Brak zawartości w odpowiedzi.');
       }
       
       return $response_data;
       
   } catch (Exception $e) {
       $this->log_error('Exception w send_chat_message: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
       throw $e;
   }
}
}