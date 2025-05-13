<?php
/**
 * Klasa bazowa do obsługi różnych API repozytoriów.
 */

// Bezpieczeństwo: Zapobieganie bezpośredniemu dostępowi do pliku
if (!defined('ABSPATH')) {
    exit('Bezpośredni dostęp zabroniony.');
}

/**
 * Abstrakcyjna klasa bazowa dla API repozytoriów.
 */
abstract class Repository_API {
    /**
     * Token dostępu do repozytorium.
     */
    protected $token;

    /**
     * Typ repozytorium (github, gitlab, bitbucket).
     */
    protected $type;

    /**
     * Pobiera token dostępu.
     * 
     * @return string|null Token dostępu lub null
     */
    abstract public function get_token();

    /**
     * Pobiera pliki z repozytorium.
     * 
     * @param string $repo_url URL repozytorium
     * @param int $max_files Maksymalna liczba plików
     * @param array $file_extensions Filtry rozszerzeń plików
     * @return array Pliki z repozytorium
     */
    abstract public function get_repository_files($repo_url, $max_files = 30, $file_extensions = []);

    /**
     * Weryfikuje token dostępu.
     * 
     * @param string|null $token Token do weryfikacji (opcjonalnie)
     * @return array Status weryfikacji
     */
    abstract public function verify_token($token = null);

    /**
     * Pobiera zawartość pliku.
     * 
     * @param string $url URL do pobrania pliku
     * @return string Zawartość pliku
     */
    abstract public function fetch_file_content($url);

    /**
     * Pobiera zawartość katalogu.
     * 
     * @param string $url URL katalogu
     * @return array Zawartość katalogu
     */
    abstract public function fetch_directory_content($url);

    /**
     * Pobiera limity API.
     * 
     * @return array Informacje o limitach
     */
    abstract public function get_rate_limits();

    /**
     * Koduje nazwę pliku lub gałęzi dla bezpiecznego użycia w URL
     * 
     * @param string $name Nazwa do zakodowania
     * @return string Zakodowana nazwa
     */
    protected function url_encode_name($name) {
        return rawurlencode($name);
    }

    /**
     * Tworzy nowego klienta API repozytorium na podstawie URL
     * 
     * @param string $repo_url URL repozytorium
     * @return Repository_API|null Instancja odpowiedniego API lub null
     */
    public static function create_from_url($repo_url) {
        if (strpos($repo_url, 'github.com') !== false) {
            if (!class_exists('GitHub_API')) {
                require_once __DIR__ . '/class-github-api.php';
            }
            return new GitHub_API();
        } elseif (strpos($repo_url, 'gitlab.com') !== false) {
            if (!class_exists('GitLab_API')) {
                require_once __DIR__ . '/class-gitlab-api.php';
            }
            return new GitLab_API();
        } elseif (strpos($repo_url, 'bitbucket.org') !== false) {
            if (!class_exists('Bitbucket_API')) {
                require_once __DIR__ . '/class-bitbucket-api.php';
            }
            return new Bitbucket_API();
        }
        
        return null;
    }

    /**
     * Pobiera typ repozytorium z URL
     * 
     * @param string $repo_url URL repozytorium
     * @return string|null Typ repozytorium lub null
     */
    public static function get_repository_type($repo_url) {
        if (strpos($repo_url, 'github.com') !== false) {
            return 'github';
        } elseif (strpos($repo_url, 'gitlab.com') !== false) {
            return 'gitlab';
        } elseif (strpos($repo_url, 'bitbucket.org') !== false) {
            return 'bitbucket';
        }
        
        return null;
    }

    /**
     * Wspólna metoda do pobierania zawartości HTTP
     * 
     * @param string $url URL do pobrania
     * @param array $headers Dodatkowe nagłówki
     * @param int $timeout Limit czasu w sekundach
     * @return array|WP_Error Odpowiedź lub błąd
     */
    protected function fetch_http_content($url, $headers = [], $timeout = 30) {
        $args = [
            'timeout' => $timeout,
            'user-agent' => 'WordPress Repository Analyzer ' . GCA_VERSION,
            'sslverify' => true
        ];
        
        if (!empty($headers)) {
            $args['headers'] = $headers;
        }
        
        return wp_remote_get($url, $args);
    }

    /**
     * Obsługuje błędy HTTP
     * 
     * @param WP_Error|array $response Odpowiedź HTTP
     * @param string $url URL zapytania
     * @throws Exception Jeśli wystąpił błąd
     * @return array Odpowiedź jako tablica
     */
    protected function handle_http_response($response, $url) {
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('Repository API Error: ' . $error_message . ' (URL: ' . $url . ')');
            throw new Exception("Błąd połączenia z API repozytorium: " . $error_message);
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($code !== 200) {
            $error_message = "Błąd podczas pobierania zawartości z repozytorium (kod: $code)";
            
            switch ($code) {
                case 401:
                    $error_message = "Nieprawidłowy token dostępu lub brak uprawnień. Sprawdź ustawienia.";
                    break;
                case 403:
                    $headers = wp_remote_retrieve_headers($response);
                    if (isset($headers['x-ratelimit-remaining']) && $headers['x-ratelimit-remaining'] == 0) {
                        $reset_time = isset($headers['x-ratelimit-reset']) ? date('Y-m-d H:i:s', $headers['x-ratelimit-reset']) : 'nieznany';
                        $error_message = "Przekroczono limit żądań API. Limit zostanie zresetowany o: $reset_time";
                    } else {
                        $error_message = "Brak wymaganych uprawnień do tego zasobu. Sprawdź token dostępu.";
                    }
                    break;
                case 404:
                    $error_message = "Repozytorium lub zasób nie istnieje. Sprawdź poprawność URL.";
                    break;
                case 500:
                case 502:
                case 503:
                case 504:
                    $error_message = "Błąd serwera repozytorium (kod: $code). Spróbuj ponownie później.";
                    break;
            }
            
            error_log('Repository API Error (' . $code . '): ' . $error_message . ' (URL: ' . $url . ')');
            throw new Exception($error_message);
        }
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $json_error = json_last_error_msg();
            error_log('Repository API JSON Error: ' . $json_error . ' (URL: ' . $url . ')');
            throw new Exception("Błąd przetwarzania odpowiedzi z API repozytorium: $json_error");
        }
        
        return $data;
    }

    /**
     * Pobiera statystyki dla repozytorium
     * 
     * @param string $repo_url URL repozytorium
     * @return array Statystyki repozytorium
     */
    abstract public function get_repository_stats($repo_url);
}