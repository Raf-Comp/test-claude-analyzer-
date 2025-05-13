<?php
/**
 * Klasa do obsługi API GitLab.
 */

// Bezpieczeństwo: Zapobieganie bezpośredniemu dostępowi do pliku
if (!defined('ABSPATH')) {
    exit('Bezpośredni dostęp zabroniony.');
}

require_once __DIR__ . '/class-repository-api.php';

/**
 * Klasa do obsługi API GitLab.
 */
class GitLab_API extends Repository_API {

    /**
     * Konstruktor.
     */
    public function __construct($token = null) {
        $this->type = 'gitlab';
        $this->token = $token ?: $this->get_token();
    }

    /**
     * Pobiera token GitLab z bazy danych dla bieżącego użytkownika.
     */
    public function get_token() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'claude_api_keys';
        $user_id = get_current_user_id();

        $token = $wpdb->get_var(
            $wpdb->prepare("SELECT gitlab_token FROM $table_name WHERE user_id = %d", $user_id)
        );

        return $token;
    }

    /**
     * Pobiera pliki z repozytorium GitLab.
     */
    public function get_repository_files($repo_url, $max_files = 30, $file_extensions = []) {
        try {
            // Zapisz log
            error_log('GCA GitLab API: Rozpoczęcie pobierania plików z ' . $repo_url);
            
            // Walidacja URL repozytorium
            if (empty($repo_url)) {
                throw new Exception("URL repozytorium jest pusty");
            }
            
            // Wyodrębnij identyfikator projektu z URL
            $project_id = $this->get_project_id_from_url($repo_url);
            
            if (empty($project_id)) {
                throw new Exception("Nie można określić identyfikatora projektu z URL: $repo_url");
            }
            
            // Zakoduj identyfikator projektu dla bezpiecznego użycia w URL
            $encoded_project_id = $this->url_encode_name($project_id);
            
            // Utwórz URL do API GitLab
            $api_url = "https://gitlab.com/api/v4/projects/$encoded_project_id/repository/tree";
            
            // Log
            $log = "Analizowanie repozytorium: $project_id\n";
            $log .= "-------------------------------------------\n";
            $log .= "URL API: $api_url\n";
            $log .= "Token GitLab: " . (empty($this->token) ? "Brak" : "Użyto") . "\n";
            $log .= "Maksymalna liczba plików: $max_files\n";
            $log .= "Filtry rozszerzeń: " . (!empty($file_extensions) ? implode(', ', $file_extensions) : "brak") . "\n\n";
            
            // Pobierz zawartość repozytorium
            $files = [];
            
            try {
                $contents = $this->fetch_directory_content($api_url);
                $log .= "Pobrano zawartość głównego katalogu: " . count($contents) . " elementów\n";
            } catch (Exception $e) {
                $log .= "BŁĄD pobierania głównego katalogu: " . $e->getMessage() . "\n";
                throw $e;
            }
            
            // Przetwarzaj zawartość repozytorium
            $queue = $contents;
            
            // Oznacz niewielkie (mniej niż 5KB) i duże pliki, aby priorytetyzować mniejsze pliki
            $small_files = [];
            $large_files = [];
            
            while (!empty($queue) && (count($small_files) + count($large_files)) < $max_files) {
                $item = array_shift($queue);
                
                if ($item['type'] === 'tree') {
                    // Pobierz zawartość katalogu
                    try {
                        $dir_path = $this->url_encode_name($item['path']);
                        $dir_url = "https://gitlab.com/api/v4/projects/$encoded_project_id/repository/tree?path=$dir_path";
                        $dir_contents = $this->fetch_directory_content($dir_url);
                        
                        foreach ($dir_contents as $dir_item) {
                            $dir_item['path'] = $item['path'] . '/' . $dir_item['name'];
                            $queue[] = $dir_item;
                        }
                        
                        $log .= "Wczytano katalog: {$item['path']} (" . count($dir_contents) . " elementów)\n";
                    } catch (Exception $e) {
                        $log .= "Błąd wczytywania katalogu {$item['path']}: {$e->getMessage()}\n";
                    }
                } elseif ($item['type'] === 'blob') {
                    // Sprawdź rozszerzenie pliku
                    $file_extension = strtolower(pathinfo($item['path'], PATHINFO_EXTENSION));
                    
                    // Filtruj pliki według rozszerzenia, jeśli podano listę rozszerzeń
                    if (!empty($file_extensions) && !in_array($file_extension, $file_extensions)) {
                        $log .= "Pominięto plik {$item['path']} (rozszerzenie $file_extension nie pasuje do filtrów)\n";
                        continue;
                    }
                    
                    // Pobierz zawartość pliku, jeśli nie jest za duży (GitLab nie podaje rozmiaru pliku w API tree)
                    $file_url = "https://gitlab.com/api/v4/projects/$encoded_project_id/repository/files/" . $this->url_encode_name($item['path']) . "/raw?ref=master";
                    
                    // Wstępne pobranie, aby sprawdzić rozmiar
                    try {
                        $headers = $this->get_file_headers($file_url);
                        $file_size = isset($headers['content-length']) ? (int)$headers['content-length'] : 0;
                        
                        if ($file_size < 500000) { // 500 KB
                            // Wyodrębnij dane pliku
                            $file_data = [
                                'name' => $item['path'],
                                'size' => $file_size,
                                'download_url' => $file_url,
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
                            $log .= "Pominięto duży plik: {$item['path']} (" . round($file_size / 1024, 2) . " KB)\n";
                        }
                    } catch (Exception $e) {
                        $log .= "Błąd wczytywania informacji o pliku {$item['path']}: {$e->getMessage()}\n";
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
                
                // Dodaj krótkie opóźnienie, aby uniknąć limitu API GitLab
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
            error_log('GitLab API Error: ' . $e->getMessage());
            
            // Rzuć błąd ponownie
            throw $e;
        }
    }

    /**
     * Pobiera nagłówki pliku z GitLab.
     * 
     * @param string $url URL pliku
     * @return array Nagłówki
     */
    protected function get_file_headers($url) {
        $args = [
            'timeout' => 10,
            'method' => 'HEAD',
            'user-agent' => 'WordPress Repository Analyzer ' . GCA_VERSION
        ];
        
        if (!empty($this->token)) {
            $args['headers'] = [
                'PRIVATE-TOKEN' => $this->token
            ];
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            throw new Exception("Błąd podczas pobierania informacji o pliku: " . $response->get_error_message());
        }
        
        return wp_remote_retrieve_headers($response);
    }

    /**
     * Pobiera zawartość katalogu z GitLab API.
     */
    public function fetch_directory_content($url) {
        // Dodaj log diagnostyczny
        error_log('GCA GitLab API: Fetching URL: ' . $url);
        
        // Przygotuj argumenty żądania
        $args = [
            'timeout' => 60,
            'user-agent' => 'WordPress Repository Analyzer ' . GCA_VERSION,
            'sslverify' => true
        ];
        
        // Dodaj token, jeśli jest dostępny
        if (!empty($this->token)) {
            $args['headers'] = [
                'PRIVATE-TOKEN' => $this->token
            ];
        }

        // Dodaj opóźnienie, aby uniknąć limitów API
        usleep(500000); // 0.5 sekundy opóźnienia
        
        // Wykonaj żądanie
        $response = wp_remote_get($url, $args);
        
        return $this->handle_http_response($response, $url);
    }

    /**
     * Pobiera zawartość pliku z GitLab.
     */
    public function fetch_file_content($url) {
        // Przygotuj argumenty żądania
        $args = [
            'timeout' => 30,
            'user-agent' => 'WordPress Repository Analyzer ' . GCA_VERSION,
            'sslverify' => true
        ];
        
        // Dodaj token, jeśli jest dostępny
        if (!empty($this->token)) {
            $args['headers'] = [
                'PRIVATE-TOKEN' => $this->token
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
     * Weryfikuje token GitLab i zwraca informacje o limitach API.
     */
    public function verify_token($token = null) {
        $token_to_check = $token ?: $this->token;
        
        if (empty($token_to_check)) {
            return [
                'valid' => false,
                'message' => 'Brak tokenu GitLab'
            ];
        }
        
        $args = [
            'timeout' => 10,
            'headers' => [
                'PRIVATE-TOKEN' => $token_to_check
            ]
        ];
        
        $response = wp_remote_get('https://gitlab.com/api/v4/user', $args);
        
        if (is_wp_error($response)) {
            return [
                'valid' => false,
                'message' => 'Błąd weryfikacji tokenu: ' . $response->get_error_message()
            ];
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($code === 200 && isset($data['id'])) {
            return [
                'valid' => true,
                'message' => 'Token GitLab jest poprawny',
                'username' => $data['username'],
                'name' => $data['name'],
                'authenticated' => true
            ];
        } elseif ($code === 401) {
            return [
                'valid' => false,
                'message' => 'Token GitLab jest nieprawidłowy lub wygasł'
            ];
        } else {
            return [
                'valid' => false,
                'message' => 'Nieprawidłowa odpowiedź API GitLab (kod: ' . $code . ')'
            ];
        }
    }

    /**
     * Pobiera identyfikator projektu z URL.
     */
    private function get_project_id_from_url($repo_url) {
        $pattern = '/gitlab\.com\/([^\/]+\/[^\/\?\#]+)/';
        if (preg_match($pattern, $repo_url, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Pobiera limity API.
     */
    public function get_rate_limits() {
        if (empty($this->token)) {
            return [
                'limit' => 60,
                'remaining' => 30, // Wartość przykładowa
                'reset' => time() + 3600
            ];
        }
        
        $args = [
            'timeout' => 10,
            'headers' => [
                'PRIVATE-TOKEN' => $this->token
            ]
        ];
        
        $response = wp_remote_get('https://gitlab.com/api/v4/rate_limit', $args);
        
        if (is_wp_error($response)) {
            return [
                'limit' => 0,
                'remaining' => 0,
                'reset' => time() + 3600
            ];
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($code === 200 && isset($data['core'])) {
            return [
                'limit' => isset($data['core']['limit']) ? $data['core']['limit'] : 0,
                'remaining' => isset($data['core']['remaining']) ? $data['core']['remaining'] : 0,
                'reset' => isset($data['core']['reset']) ? $data['core']['reset'] : time() + 3600
            ];
        }
        
        return [
            'limit' => 0,
            'remaining' => 0,
            'reset' => time() + 3600
        ];
    }

    /**
     * Pobiera statystyki dla repozytorium
     */
    public function get_repository_stats($repo_url) {
        $project_id = $this->get_project_id_from_url($repo_url);
        
        if (empty($project_id)) {
            throw new Exception("Nie można określić identyfikatora projektu z URL: $repo_url");
        }
        
        // Zakoduj identyfikator projektu dla bezpiecznego użycia w URL
        $encoded_project_id = $this->url_encode_name($project_id);
        
        // Pobierz podstawowe informacje o projekcie
        $api_url = "https://gitlab.com/api/v4/projects/$encoded_project_id";
        $project_data = $this->fetch_directory_content($api_url);
        
        // Pobierz informacje o gałęziach
        $branches_url = "https://gitlab.com/api/v4/projects/$encoded_project_id/repository/branches";
        $branches_data = $this->fetch_directory_content($branches_url);
        
        // Pobierz informacje o commitach
        $commits_url = "https://gitlab.com/api/v4/projects/$encoded_project_id/repository/commits";
        $commits_data = $this->fetch_directory_content($commits_url);
        
        return [
            'name' => $project_data['name'],
            'full_name' => $project_data['path_with_namespace'],
            'description' => $project_data['description'],
            'default_branch' => $project_data['default_branch'],
            'stars' => $project_data['star_count'],
            'forks' => $project_data['forks_count'],
            'open_issues' => isset($project_data['open_issues_count']) ? $project_data['open_issues_count'] : 0,
            'watchers' => isset($project_data['watchers_count']) ? $project_data['watchers_count'] : 0,
            'branches' => count($branches_data),
            'commits' => count($commits_data),
            'last_commit' => isset($commits_data[0]) ? [
                'message' => $commits_data[0]['message'],
                'author' => $commits_data[0]['author_name'],
                'date' => $commits_data[0]['created_at']
            ] : null,
            'private' => !$project_data['public'],
            'created_at' => $project_data['created_at'],
            'updated_at' => $project_data['last_activity_at']
        ];
    }
}