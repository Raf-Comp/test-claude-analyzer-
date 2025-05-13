<?php
/**
* Klasa do obsługi API Bitbucket.
*/

// Bezpieczeństwo: Zapobieganie bezpośredniemu dostępowi do pliku
if (!defined('ABSPATH')) {
    exit('Bezpośredni dostęp zabroniony.');
 }
 
 require_once __DIR__ . '/class-repository-api.php';
 
 /**
 * Klasa do obsługi API Bitbucket.
 */
 class Bitbucket_API extends Repository_API {
 
    /**
     * Nazwa użytkownika Bitbucket.
     */
    private $username;
 
    /**
     * Hasło aplikacji Bitbucket.
     */
    private $app_password;
 
    /**
     * Konstruktor.
     */
    public function __construct($token = null) {
        $this->type = 'bitbucket';
        $this->token = $token ?: $this->get_token();
        
        // Podziel token na nazwę użytkownika i hasło aplikacji
        if (!empty($this->token) && strpos($this->token, ':') !== false) {
            list($this->username, $this->app_password) = explode(':', $this->token, 2);
        }
    }
 
    /**
     * Pobiera token Bitbucket z bazy danych dla bieżącego użytkownika.
     */
    public function get_token() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'claude_api_keys';
        $user_id = get_current_user_id();
 
        $token = $wpdb->get_var(
            $wpdb->prepare("SELECT bitbucket_token FROM $table_name WHERE user_id = %d", $user_id)
        );
 
        return $token;
    }
 
    /**
     * Pobiera pliki z repozytorium Bitbucket.
     */
    public function get_repository_files($repo_url, $max_files = 30, $file_extensions = []) {
        try {
            // Zapisz log
            error_log('GCA Bitbucket API: Rozpoczęcie pobierania plików z ' . $repo_url);
            
            // Walidacja URL repozytorium
            if (empty($repo_url)) {
                throw new Exception("URL repozytorium jest pusty");
            }
            
            // Wyodrębnij dane repozytorium z URL
            $repo_info = $this->get_repo_info_from_url($repo_url);
            
            if (empty($repo_info['workspace']) || empty($repo_info['repo_slug'])) {
                throw new Exception("Nie można określić informacji o repozytorium z URL: $repo_url");
            }
            
            // Utwórz URL do API Bitbucket
            $api_url = "https://api.bitbucket.org/2.0/repositories/{$repo_info['workspace']}/{$repo_info['repo_slug']}/src";
            
            // Log
            $log = "Analizowanie repozytorium: {$repo_info['workspace']}/{$repo_info['repo_slug']}\n";
            $log .= "-------------------------------------------\n";
            $log .= "URL API: $api_url\n";
            $log .= "Token Bitbucket: " . (empty($this->token) ? "Brak" : "Użyto") . "\n";
            $log .= "Maksymalna liczba plików: $max_files\n";
            $log .= "Filtry rozszerzeń: " . (!empty($file_extensions) ? implode(', ', $file_extensions) : "brak") . "\n\n";
            
            // Pobierz domyślną gałąź repozytorium
            $repo_data = $this->fetch_directory_content("https://api.bitbucket.org/2.0/repositories/{$repo_info['workspace']}/{$repo_info['repo_slug']}");
            $default_branch = isset($repo_data['mainbranch']['name']) ? $repo_data['mainbranch']['name'] : 'master';
            
            // Pobierz zawartość repozytorium dla domyślnej gałęzi
            $api_url .= "/$default_branch";
            
            // Pobierz zawartość repozytorium
            $files = [];
            
            try {
                $contents = $this->fetch_directory_content($api_url);
                $log .= "Pobrano zawartość głównego katalogu: " . count($contents['values']) . " elementów\n";
            } catch (Exception $e) {
                $log .= "BŁĄD pobierania głównego katalogu: " . $e->getMessage() . "\n";
                throw $e;
            }
            
            // Przetwarzaj zawartość repozytorium
            $queue = [];
            
            // Dodaj elementy do kolejki
            foreach ($contents['values'] as $item) {
                $queue[] = $item;
            }
            
            // Obsługa paginacji
            while (isset($contents['next']) && !empty($contents['next']) && count($queue) < $max_files * 2) {
                try {
                    $contents = $this->fetch_directory_content($contents['next']);
                    foreach ($contents['values'] as $item) {
                        $queue[] = $item;
                    }
                } catch (Exception $e) {
                    $log .= "BŁĄD pobierania następnej strony: " . $e->getMessage() . "\n";
                    break;
                }
            }
            
            // Oznacz niewielkie (mniej niż 5KB) i duże pliki, aby priorytetyzować mniejsze pliki
            $small_files = [];
            $large_files = [];
            
            while (!empty($queue) && (count($small_files) + count($large_files)) < $max_files) {
                $item = array_shift($queue);
                
                if ($item['type'] === 'commit_directory') {
                    // Pobierz zawartość katalogu
                    try {
                        if (isset($item['links']['self']['href'])) {
                            $dir_contents = $this->fetch_directory_content($item['links']['self']['href']);
                            
                            if (isset($dir_contents['values'])) {
                                foreach ($dir_contents['values'] as $dir_item) {
                                    $queue[] = $dir_item;
                                }
                                
                                $log .= "Wczytano katalog: {$item['path']} (" . count($dir_contents['values']) . " elementów)\n";
                                
                                // Obsługa paginacji dla katalogu
                                while (isset($dir_contents['next']) && !empty($dir_contents['next']) && count($queue) < $max_files * 2) {
                                    $dir_contents = $this->fetch_directory_content($dir_contents['next']);
                                    foreach ($dir_contents['values'] as $dir_item) {
                                        $queue[] = $dir_item;
                                    }
                                }
                            }
                        } else {
                            $log .= "Pominięto katalog {$item['path']} - brak URL\n";
                        }
                    } catch (Exception $e) {
                        $log .= "Błąd wczytywania katalogu {$item['path']}: {$e->getMessage()}\n";
                    }
                } elseif ($item['type'] === 'commit_file') {
                    // Sprawdź rozszerzenie pliku
                    $file_extension = strtolower(pathinfo($item['path'], PATHINFO_EXTENSION));
                    
                    // Filtruj pliki według rozszerzenia, jeśli podano listę rozszerzeń
                    if (!empty($file_extensions) && !in_array($file_extension, $file_extensions)) {
                        $log .= "Pominięto plik {$item['path']} (rozszerzenie $file_extension nie pasuje do filtrów)\n";
                        continue;
                    }
                    
                    // Sprawdź rozmiar pliku
                    $file_size = isset($item['size']) ? $item['size'] : 0;
                    
                    if ($file_size < 500000) { // 500 KB
                        // Wyodrębnij dane pliku
                        $download_url = isset($item['links']['self']['href']) ? $item['links']['self']['href'] : '';
                        
                        if (!empty($download_url)) {
                            $file_data = [
                                'name' => $item['path'],
                                'size' => $file_size,
                                'download_url' => $download_url,
                                'extension' => $file_extension
                            ];
                            
                            // Priorytetyzuj niewielkie pliki
                            if ($file_size < 5000) { // 5 KB
                                $small_files[] = $file_data;
                                $log .= "Dodano mały plik do kolejki: {$item['path']} (" . round($file_size / 1024, 2) . " KB)\n";
                            } else {
                                $large_files[] = $file_data;
                                $log .= "Dodano duży plik do kolejki: {$item['path']} (" . round($file_size / 1024, 2) . " KB)\n";
                            }
                        } else {
                            $log .= "Pominięto plik {$item['path']} - brak URL\n";
                        }
                    } else {
                        $log .= "Pominięto duży plik: {$item['path']} (" . round($file_size / 1024, 2) . " KB)\n";
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
                    $file_content = $this->fetch_file_content($file_data['download_url']);
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
                
                // Dodaj krótkie opóźnienie, aby uniknąć limitu API Bitbucket
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
            error_log('Bitbucket API Error: ' . $e->getMessage());
            
            // Rzuć błąd ponownie
            throw $e;
        }
    }
 
    /**
     * Pobiera zawartość katalogu z Bitbucket API.
     */
    public function fetch_directory_content($url) {
        // Dodaj log diagnostyczny
        error_log('GCA Bitbucket API: Fetching URL: ' . $url);
        
        // Przygotuj argumenty żądania
        $args = [
            'timeout' => 60,
            'user-agent' => 'WordPress Repository Analyzer ' . GCA_VERSION,
            'sslverify' => true
        ];
        
        // Dodaj autoryzację, jeśli jest dostępna
        if (!empty($this->username) && !empty($this->app_password)) {
            $args['headers'] = [
                'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->app_password)
            ];
        }
 
        // Dodaj opóźnienie, aby uniknąć limitów API
        usleep(500000); // 0.5 sekundy opóźnienia
        
        // Wykonaj żądanie
        $response = wp_remote_get($url, $args);
        
        return $this->handle_http_response($response, $url);
    }
 
    /**
     * Pobiera zawartość pliku z Bitbucket.
     */
    public function fetch_file_content($url) {
        // Przygotuj argumenty żądania
        $args = [
            'timeout' => 30,
            'user-agent' => 'WordPress Repository Analyzer ' . GCA_VERSION,
            'sslverify' => true
        ];
        
        // Dodaj autoryzację, jeśli jest dostępna
        if (!empty($this->username) && !empty($this->app_password)) {
            $args['headers'] = [
                'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->app_password)
            ];
        }
        
        // Dodaj opóźnienie
        usleep(300000); // 0.3 sekundy
        
        // Wykonaj żądanie
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            throw new Exception("Błąd podczas pobierania zawartości pliku: " . $response->get_error_message());
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            throw new Exception("Błąd podczas pobierania zawartości pliku (kod: $code)");
        }
        
        return wp_remote_retrieve_body($response);
    }
 
    /**
     * Weryfikuje token Bitbucket i zwraca informacje o limitach API.
     */
    public function verify_token($token = null) {
        $token_to_check = $token ?: $this->token;
        
        if (empty($token_to_check)) {
            return [
                'valid' => false,
                'message' => 'Brak tokenu Bitbucket'
            ];
        }
        
        // Podziel token na nazwę użytkownika i hasło aplikacji
        $username = '';
        $app_password = '';
        
        if (strpos($token_to_check, ':') !== false) {
            list($username, $app_password) = explode(':', $token_to_check, 2);
        } else {
            return [
                'valid' => false,
                'message' => 'Nieprawidłowy format tokenu Bitbucket. Wymagany format: username:app_password'
            ];
        }
        
        $args = [
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $app_password)
            ]
        ];
        
        $response = wp_remote_get('https://api.bitbucket.org/2.0/user', $args);
        
        if (is_wp_error($response)) {
            return [
                'valid' => false,
                'message' => 'Błąd weryfikacji tokenu: ' . $response->get_error_message()
            ];
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($code === 200 && isset($data['username'])) {
            return [
                'valid' => true,
                'message' => 'Token Bitbucket jest poprawny',
                'username' => $data['username'],
                'display_name' => $data['display_name'],
                'authenticated' => true
            ];
        } elseif ($code === 401) {
            return [
                'valid' => false,
                'message' => 'Token Bitbucket jest nieprawidłowy lub wygasł'
            ];
        } else {
            return [
                'valid' => false,
                'message' => 'Nieprawidłowa odpowiedź API Bitbucket (kod: ' . $code . ')'
            ];
        }
    }
 
    /**
     * Pobiera informacje o repozytorium z URL.
     */
    private function get_repo_info_from_url($repo_url) {
        $pattern = '/bitbucket\.org\/([^\/]+)\/([^\/\?\#]+)/';
        if (preg_match($pattern, $repo_url, $matches)) {
            return [
                'workspace' => $matches[1],
                'repo_slug' => $matches[2]
            ];
        }
        
        return [
            'workspace' => null,
            'repo_slug' => null
        ];
    }
 
    /**
     * Pobiera limity API.
     */
    public function get_rate_limits() {
        // Bitbucket nie ma bezpośredniego endpointu dla limitów API, używa mechanizmu ratelimit w nagłówkach
        // Wykonaj proste zapytanie, aby pobrać nagłówki limitów
        
        $args = [
            'timeout' => 10,
            'method' => 'HEAD'
        ];
        
        if (!empty($this->username) && !empty($this->app_password)) {
            $args['headers'] = [
                'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->app_password)
            ];
        }
        
        $response = wp_remote_request('https://api.bitbucket.org/2.0/repositories', $args);
        
        if (is_wp_error($response)) {
            return [
                'limit' => 60,
                'remaining' => 30, // Wartość przykładowa
                'reset' => time() + 3600
            ];
        }
        
        $headers = wp_remote_retrieve_headers($response);
        
        $limit = isset($headers['x-ratelimit-limit']) ? (int)$headers['x-ratelimit-limit'] : 60;
        $remaining = isset($headers['x-ratelimit-remaining']) ? (int)$headers['x-ratelimit-remaining'] : 30;
        
        // Bitbucket używa "x-rate-limit-reset" zamiast "x-ratelimit-reset"
        $reset = isset($headers['x-rate-limit-reset']) 
            ? strtotime($headers['x-rate-limit-reset']) 
            : (time() + 3600);
        
        return [
            'limit' => $limit,
            'remaining' => $remaining,
            'reset' => $reset
        ];
    }
 
    /**
     * Pobiera statystyki dla repozytorium
     */
    public function get_repository_stats($repo_url) {
        $repo_info = $this->get_repo_info_from_url($repo_url);
        
        if (empty($repo_info['workspace']) || empty($repo_info['repo_slug'])) {
            throw new Exception("Nie można określić informacji o repozytorium z URL: $repo_url");
        }
        
        // Pobierz podstawowe informacje o repozytorium
        $api_url = "https://api.bitbucket.org/2.0/repositories/{$repo_info['workspace']}/{$repo_info['repo_slug']}";
        $repo_data = $this->fetch_directory_content($api_url);
        
        // Pobierz informacje o gałęziach
        $branches_url = "$api_url/refs/branches";
        $branches_data = $this->fetch_directory_content($branches_url);
        
        // Pobierz informacje o commitach
        $commits_url = "$api_url/commits";
        $commits_data = $this->fetch_directory_content($commits_url);
        
        return [
            'name' => $repo_data['name'],
            'full_name' => $repo_data['full_name'],
            'description' => isset($repo_data['description']) ? $repo_data['description'] : '',
            'default_branch' => isset($repo_data['mainbranch']['name']) ? $repo_data['mainbranch']['name'] : 'master',
            'stars' => 0, // Bitbucket nie ma gwiazdek, używa obserwujących
            'forks' => isset($repo_data['forks_count']) ? $repo_data['forks_count'] : 0,
            'open_issues' => 0, // Bitbucket nie ma bezpośredniego licznika otwartych issues w głównych danych repo
            'watchers' => isset($repo_data['watchers_count']) ? $repo_data['watchers_count'] : 0,
            'branches' => count($branches_data['values']),
            'commits' => count($commits_data['values']),
            'last_commit' => isset($commits_data['values'][0]) ? [
                'message' => $commits_data['values'][0]['message'],
                'author' => $commits_data['values'][0]['author']['raw'],
                'date' => $commits_data['values'][0]['date']
            ] : null,
            'private' => isset($repo_data['is_private']) ? $repo_data['is_private'] : false,
            'created_at' => $repo_data['created_on'],
            'updated_at' => $repo_data['updated_on']
        ];
    }
 }