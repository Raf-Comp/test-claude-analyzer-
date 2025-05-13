<?php
/**
 * Klasa do obsługi API GitHub.
 */
class GitHub_API {

    /**
     * Token dostępu do GitHub.
     */
    private $github_token;

    /**
     * Konstruktor.
     */
    public function __construct($github_token = null) {
        $this->github_token = $github_token ?: $this->get_github_token();
    }

    /**
     * Pobiera token GitHub z bazy danych dla bieżącego użytkownika.
     */
    public function get_github_token() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'claude_api_keys';
        $user_id = get_current_user_id();

        $github_token = $wpdb->get_var(
            $wpdb->prepare("SELECT github_token FROM $table_name WHERE user_id = %d", $user_id)
        );

        return $github_token;
    }

   /**
    * Pobiera pliki z repozytorium GitHub.
    */
    public function get_repository_files($repo_url, $max_files = 30, $file_extensions = []) {
        try {
            // Na początek sprawdźmy i zalogujmy informacje o tokenie
            if (!empty($this->github_token)) {
                error_log('GCA Debug: Token GitHub jest ustawiony, długość: ' . strlen($this->github_token));
                
                // Sprawdź czy token jest poprawny za pomocą API rate_limit
                $test_args = [
                    'headers' => [
                        'Authorization' => 'token ' . $this->github_token,
                        'User-Agent' => 'WordPress GitHub Claude Analyzer/' . GCA_VERSION
                    ],
                    'timeout' => 10
                ];
                
                $rate_response = wp_remote_get('https://api.github.com/rate_limit', $test_args);
                
                if (!is_wp_error($rate_response)) {
                    $rate_code = wp_remote_retrieve_response_code($rate_response);
                    $rate_body = wp_remote_retrieve_body($rate_response);
                    $rate_data = json_decode($rate_body, true);
                    
                    if ($rate_code === 200 && isset($rate_data['resources']['core']['limit'])) {
                        error_log('GCA Debug: Weryfikacja tokenu GitHub: OK - Limit: ' . $rate_data['resources']['core']['limit'] . 
                                  ', Pozostało: ' . $rate_data['resources']['core']['remaining']);
                    } else {
                        error_log('GCA Debug: Weryfikacja tokenu GitHub: BŁĄD - Kod: ' . $rate_code);
                    }
                } else {
                    error_log('GCA Debug: Weryfikacja tokenu GitHub: BŁĄD - ' . $rate_response->get_error_message());
                }
            } else {
                error_log('GCA Debug: Token GitHub nie jest ustawiony');
            }
            
            // Walidacja URL repozytorium
            if (empty($repo_url)) {
                throw new Exception("URL repozytorium jest pusty");
            }
            
            // Wyodrębnij nazwę użytkownika i repozytorium z URL
            $pattern = '/github\.com\/([^\/]+)\/([^\/\?\#]+)/';
            if (!preg_match($pattern, $repo_url, $matches)) {
                throw new Exception("Nieprawidłowy format URL repozytorium GitHub. Poprawny format: https://github.com/użytkownik/repozytorium");
            }
            
            // Więcej szczegółowych informacji o wykrytych parametrach
            $username = $matches[1];
            $repo = rtrim($matches[2], '.git');
            error_log("GCA Debug: Wykryte parametry repozytorium - Użytkownik: '$username', Repozytorium: '$repo'");
            
            // Walidacja username i repo
            if (empty($username) || empty($repo)) {
                throw new Exception("Nie można określić nazwy użytkownika lub repozytorium z URL: $repo_url");
            }
            
            // Utwórz URL do API GitHub
            $api_url = "https://api.github.com/repos/$username/$repo/contents";
            
            // Log do debugowania
            $log = "Analizowanie repozytorium: $username/$repo\n";
            $log .= "-------------------------------------------\n";
            $log .= "URL API: $api_url\n";
            $log .= "Tokena GitHub: " . (empty($this->github_token) ? "Brak" : "Użyto") . "\n";
            $log .= "Maksymalna liczba plików: $max_files\n";
            $log .= "Filtry rozszerzeń: " . (!empty($file_extensions) ? implode(', ', $file_extensions) : "brak") . "\n\n";
            
            // Pobierz zawartość repozytorium
            $files = [];
            
            try {
                $contents = $this->fetch_github_contents($api_url);
                $log .= "Pobrano zawartość głównego katalogu: " . count($contents) . " elementów\n";
            } catch (Exception $e) {
                $log .= "BŁĄD pobierania głównego katalogu: " . $e->getMessage() . "\n";
                throw $e; // Przekaż błąd dalej
            }
            
            // Przetwarzaj zawartość repozytorium
            $queue = $contents;
            
            // Oznacz niewielkie (mniej niż 5KB) i duże pliki, aby priorytetyzować mniejsze pliki
            $small_files = [];
            $large_files = [];
            
            while (!empty($queue) && (count($small_files) + count($large_files)) < $max_files) {
                $item = array_shift($queue);
                
                if ($item['type'] === 'dir') {
                    // Pobierz zawartość katalogu
                    try {
                        $dir_contents = $this->fetch_github_contents($item['url']);
                        foreach ($dir_contents as $dir_item) {
                            $queue[] = $dir_item;
                        }
                        $log .= "Wczytano katalog: {$item['path']} (" . count($dir_contents) . " elementów)\n";
                    } catch (Exception $e) {
                        $log .= "Błąd wczytywania katalogu {$item['path']}: {$e->getMessage()}\n";
                    }
                } elseif ($item['type'] === 'file') {
                    // Sprawdź rozszerzenie pliku
                    $file_extension = strtolower(pathinfo($item['path'], PATHINFO_EXTENSION));
                    
                    // Filtruj pliki według rozszerzenia, jeśli podano listę rozszerzeń
                    if (!empty($file_extensions) && !in_array($file_extension, $file_extensions)) {
                        $log .= "Pominięto plik {$item['path']} (rozszerzenie $file_extension nie pasuje do filtrów)\n";
                        continue;
                    }
                    
                    // Pobierz zawartość pliku, jeśli nie jest za duży
                    if (isset($item['size'])) {
                        if ($item['size'] < 500000) { // 500 KB
                            // Wyodrębnij dane pliku
                            $file_data = [
                                'name' => $item['path'],
                                'size' => $item['size'],
                                'download_url' => $item['download_url'],
                                'extension' => $file_extension
                            ];
                            
                            // Priorytetyzuj niewielkie pliki
                            if ($item['size'] < 5000) { // 5 KB
                                $small_files[] = $file_data;
                                $log .= "Dodano mały plik do kolejki: {$item['path']} (" . round($item['size'] / 1024, 2) . " KB)\n";
                            } else {
                                $large_files[] = $file_data;
                                $log .= "Dodano duży plik do kolejki: {$item['path']} (" . round($item['size'] / 1024, 2) . " KB)\n";
                            }
                        } else {
                            $log .= "Pominięto duży plik: {$item['path']} (" . round($item['size'] / 1024, 2) . " KB)\n";
                        }
                    } else {
                        $log .= "Pominięto plik {$item['path']} (brak informacji o rozmiarze)\n";
                    }
                }
            }
            
            // Łączymy małe i duże pliki, priorytetyzując małe
            $all_files = array_merge($small_files, $large_files);
            // Obcinamy do limitu max_files
            $all_files = array_slice($all_files, 0, $max_files);
            
            $log .= "\nWybrano " . count($all_files) . " plików do analizy\n";
            
            if (empty($all_files)) {
                throw new Exception("Nie znaleziono pasujących plików w repozytorium. Sprawdź filtry rozszerzeń i upewnij się, że repozytorium zawiera pliki.");
            }
            
            // Teraz pobieramy zawartość plików
            foreach ($all_files as $key => $file_data) {
                try {
                    $file_content = $this->fetch_github_file_content($file_data['download_url']);
                    if ($file_content !== false) {
                        $files[] = [
                            'name' => $file_data['name'],
                            'content' => $file_content,
                            'extension' => $file_data['extension'],
                            'size' => $file_data['size']
                        ];
                        $log .= "Pobrano zawartość pliku: {$file_data['name']} (" . round($file_data['size'] / 1024, 2) . " KB)\n";
                    } else {
                        $log .= "Nie udało się pobrać zawartości pliku {$file_data['name']}\n";
                    }
                } catch (Exception $e) {
                    $log .= "Błąd wczytywania pliku {$file_data['name']}: {$e->getMessage()}\n";
                }
                
                // Dodaj krótkie opóźnienie, aby uniknąć limitu API GitHub
                if ($key % 5 === 0 && $key > 0) {
                    usleep(1000000); // 1 sekunda opóźnienia co 5 plików
                }
            }
            
            $log .= "-------------------------------------------\n";
            $log .= "Pobrano zawartość łącznie " . count($files) . " plików z " . count($all_files) . " wybranych\n";
            
            if (empty($files)) {
                throw new Exception("Nie udało się pobrać zawartości żadnego pliku z repozytorium. Sprawdź, czy masz odpowiednie uprawnienia.");
            }
            
            return [
                'files' => $files,
                'log' => $log,
                'total' => count($files)
            ];
        } catch (Exception $e) {
            // Zapisz do logu
            error_log('GitHub API Error: ' . $e->getMessage());
            
            // Rzuć błąd ponownie
            throw $e;
        }
    }

    /**
     * Pobiera zawartość katalogu z GitHub API.
     */
    private function fetch_github_contents($url) {
        // Dodaj log diagnostyczny
        error_log('GCA GitHub API: Fetching URL: ' . $url);
        
        // Przygotuj argumenty żądania
        $args = [
            'timeout' => 60, // Zwiększony timeout z 30 do 60
            'user-agent' => 'WordPress GitHub Claude Analyzer ' . GCA_VERSION,
            'sslverify' => true
        ];
        
        // Dodaj nagłówek autoryzacji, jeśli token jest dostępny
        if (!empty($this->github_token)) {
            error_log('GCA GitHub API: Using GitHub token');
            $args['headers'] = [
                'Authorization' => 'token ' . $this->github_token
            ];
        } else {
            error_log('GCA GitHub API: No GitHub token provided - consider adding one for higher rate limits');
        }

        // Dodaj opóźnienie, aby uniknąć limitów API GitHub
        usleep(500000); // 0.5 sekundy opóźnienia
        
        // Wykonaj żądanie
        $response = wp_remote_get($url, $args);
        
        // Szczegółowe zapisywanie informacji o błędach
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('GCA GitHub API Error: ' . $error_message);
            throw new Exception("Błąd połączenia z GitHub API: " . $error_message);
        }
        
        // Pobierz kod odpowiedzi
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Zapisz odpowiedź dla debugowania
        error_log('GCA GitHub API: Response code: ' . $code);
        
        // Zapisz pełne nagłówki odpowiedzi do debugowania
        $headers = wp_remote_retrieve_headers($response);
        $rate_limit = isset($headers['x-ratelimit-limit']) ? $headers['x-ratelimit-limit'] : 'unknown';
        $rate_remaining = isset($headers['x-ratelimit-remaining']) ? $headers['x-ratelimit-remaining'] : 'unknown';
        error_log("GCA GitHub API: Rate limits - Limit: $rate_limit, Remaining: $rate_remaining");
        
        // Obsłuż kody błędów
        if ($code !== 200) {
            // Sprawdź, czy to problem limitów API
            if ($code === 403 && isset($headers['x-ratelimit-remaining']) && $headers['x-ratelimit-remaining'] == 0) {
                $reset_time = isset($headers['x-ratelimit-reset']) ? date('Y-m-d H:i:s', $headers['x-ratelimit-reset']) : 'unknown';
                $error_message = "Przekroczono limit żądań API GitHub. Limit zostanie zresetowany o: $reset_time. ";
                if (empty($this->github_token)) {
                    $error_message .= "Zalecane jest dodanie tokenu GitHub w ustawieniach, aby zwiększyć limit zapytań.";
                }
                throw new Exception($error_message);
            }
            
            $error_message = "Błąd podczas pobierania zawartości z GitHub (kod: $code)";
            
            if ($code === 404) {
                // Zmodyfikowany komunikat dla 404 - niezależnie od tokenu
                $error_message .= ". Repozytorium nie istnieje lub podany URL jest nieprawidłowy. Sprawdź dokładnie wpisany adres.";
                error_log('GCA GitHub API: 404 Not Found. URL: ' . $url);
            } elseif ($code === 401) {
                $error_message .= ". Nieprawidłowy token GitHub lub brak uprawnień.";
                error_log('GCA GitHub API: 401 Unauthorized. Token may be invalid or expired.');
            } elseif ($code === 403) {
                $rate_limit = isset($headers['x-ratelimit-limit']) ? $headers['x-ratelimit-limit'] : 'nieznany';
                $rate_remaining = isset($headers['x-ratelimit-remaining']) ? $headers['x-ratelimit-remaining'] : 'nieznany';
                $reset_time = isset($headers['x-ratelimit-reset']) ? date('Y-m-d H:i:s', $headers['x-ratelimit-reset']) : 'nieznany';
                
                $error_message .= ". Przekroczono limit żądań API GitHub (Limit: $rate_limit, Pozostało: $rate_remaining, Reset: $reset_time). ";
                $error_message .= "Spróbuj ponownie później lub użyj tokenu GitHub z wyższymi limitami.";
                
                error_log("GCA GitHub API: 403 Rate limit exceeded. Limit: $rate_limit, Remaining: $rate_remaining, Reset: $reset_time");
            } else {
                $error_message .= ". Sprawdź poprawność URL i tokenu GitHub.";
            }
            
            error_log('GCA GitHub API: Error body: ' . $body);
            throw new Exception($error_message);
        }
        
        // Parsuj odpowiedź JSON
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $json_error = json_last_error_msg();
            error_log('GCA GitHub API: JSON parsing error: ' . $json_error);
            throw new Exception("Błąd przetwarzania odpowiedzi z GitHub API: $json_error");
        }
        
        // Dodatkowe sprawdzenie - czasami odpowiedź jest poprawna, ale ma nieodpowiedni format
        if (!is_array($data)) {
            error_log('GCA GitHub API: Unexpected response format - not an array: ' . substr($body, 0, 255));
            throw new Exception("Nieoczekiwany format odpowiedzi z GitHub API. Spodziewano się tablicy, otrzymano: " . gettype($data));
        }
        
        return $data;
    }

    /**
     * Pobiera zawartość pliku z GitHub.
     */
    private function fetch_github_file_content($url) {
        // Dodaj log diagnostyczny
        error_log('GCA GitHub API: Fetching file: ' . $url);
        
        $args = [
            'timeout' => 30,
            'user-agent' => 'WordPress GitHub Claude Analyzer ' . GCA_VERSION,
            'sslverify' => true
        ];
        
        // Dodaj nagłówek autoryzacji, jeśli token jest dostępny
        if (!empty($this->github_token)) {
            $args['headers'] = [
                'Authorization' => 'token ' . $this->github_token
            ];
        }
        
        // Dodaj małe opóźnienie
        usleep(300000); // 0.3 sekundy
        
        // Wykonaj żądanie
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('GCA GitHub API File Error: ' . $error_message);
            // Zamiast zwrócenia false, wyrzuć wyjątek z pełnymi informacjami
            throw new Exception("Błąd podczas pobierania zawartości pliku: " . $error_message);
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $body = wp_remote_retrieve_body($response);
            error_log('GCA GitHub API File Error: HTTP code ' . $code . ', body: ' . substr($body, 0, 255));
            
            // Bardziej szczegółowy komunikat błędu
            $error_message = "Błąd podczas pobierania zawartości pliku (kod: $code)";
            
            if ($code === 403) {
                // Sprawdź czy to problem limitu API
                $headers = wp_remote_retrieve_headers($response);
                $rate_remaining = isset($headers['x-ratelimit-remaining']) ? $headers['x-ratelimit-remaining'] : null;
                
                if ($rate_remaining !== null && $rate_remaining == 0) {
                    $reset_time = isset($headers['x-ratelimit-reset']) ? date('Y-m-d H:i:s', $headers['x-ratelimit-reset']) : 'unknown';
                    $error_message .= ". Przekroczono limit żądań API GitHub. Reset o: $reset_time.";
                }
            }
            
            throw new Exception($error_message);
        }
        
        return wp_remote_retrieve_body($response);
    }
    
    /**
     * Weryfikuje token GitHub i zwraca informacje o limitach API.
     */
    public function verify_token($token = null) {
        $token_to_check = $token ?: $this->github_token;
        
        if (empty($token_to_check)) {
            return [
                'valid' => false,
                'message' => 'Brak tokenu GitHub'
            ];
        }
        
        $args = [
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'token ' . $token_to_check,
                'User-Agent' => 'WordPress GitHub Claude Analyzer ' . GCA_VERSION
            ]
        ];
        
        $response = wp_remote_get('https://api.github.com/rate_limit', $args);
        
        if (is_wp_error($response)) {
            return [
                'valid' => false,
                'message' => 'Błąd weryfikacji tokenu: ' . $response->get_error_message()
            ];
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($code === 200 && isset($data['resources']['core']['limit'])) {
            return [
                'valid' => true,
                'message' => 'Token GitHub jest poprawny',
                'limit' => $data['resources']['core']['limit'],
                'remaining' => $data['resources']['core']['remaining'],
                'reset' => date('Y-m-d H:i:s', $data['resources']['core']['reset']),
                'authenticated' => $data['resources']['core']['limit'] > 60
            ];
        } elseif ($code === 401) {
            return [
                'valid' => false,
                'message' => 'Token GitHub jest nieprawidłowy lub wygasł'
            ];
        } else {
            return [
                'valid' => false,
                'message' => 'Nieprawidłowa odpowiedź API GitHub (kod: ' . $code . ')'
            ];
        }
    }
}