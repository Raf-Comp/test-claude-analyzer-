<?php
/**
 * Klasa odpowiedzialna za optymalizację wydajności wtyczki
 */
class GitHub_Claude_Optimizer {

    /**
     * Konstruktor.
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
    }

    /**
     * Inicjalizacja.
     */
    public function init() {
        // Optymalizacja ładowania zasobów
        add_action('wp_enqueue_scripts', array($this, 'optimize_resources'), 999);
        
        // Optymalizacja wywołań AJAX
        add_filter('gca_ajax_data', array($this, 'optimize_ajax_data'));
        
        // Obsługa przerwania długich analiz
        add_action('wp_ajax_gca_cancel_analysis', array($this, 'cancel_analysis'));
    }

    /**
     * Optymalizuje ładowanie zasobów
     */
    public function optimize_resources() {
        global $post;
        
        // Sprawdź, czy strona zawiera shortcode, aby załadować zasoby tylko gdy są potrzebne
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'github_claude_analyzer')) {
            // Zasoby są już ładowane przez główną klasę
        } else {
            // Leniwe ładowanie zasobów na innych stronach
            add_action('wp_footer', array($this, 'lazy_load_resources'));
        }
    }

    /**
     * Leniwe ładowanie zasobów
     */
    public function lazy_load_resources() {
        ?>
        <script>
        (function() {
            // Funkcja do sprawdzania, czy element jest w widoku
            function isInViewport(el) {
                const rect = el.getBoundingClientRect();
                return (
                    rect.top >= 0 &&
                    rect.left >= 0 &&
                    rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                    rect.right <= (window.innerWidth || document.documentElement.clientWidth)
                );
            }
            
            // Funkcja do ładowania zasobów, gdy są potrzebne
            function loadResourcesWhenNeeded() {
                const chatButton = document.querySelector('#gca-chat-button');
                
                if (chatButton && isInViewport(chatButton) && !window.gcaResourcesLoaded) {
                    window.gcaResourcesLoaded = true;
                    
                    // Załaduj skrypty
                    const scripts = [
                        '<?php echo GCA_PLUGIN_URL; ?>public/js/claude-chat.js',
                        '<?php echo GCA_PLUGIN_URL; ?>public/js/code-highlighter.js'
                    ];
                    
                    scripts.forEach(function(src) {
                        const script = document.createElement('script');
                        script.src = src;
                        script.async = true;
                        document.body.appendChild(script);
                    });
                    
                    // Załaduj style
                    const link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = '<?php echo GCA_PLUGIN_URL; ?>public/css/prism.css';
                    document.head.appendChild(link);
                    
                    // Usuń nasłuchiwanie zdarzeń
                    window.removeEventListener('scroll', loadResourcesWhenNeeded);
                    window.removeEventListener('resize', loadResourcesWhenNeeded);
                }
            }
            
            // Dodaj nasłuchiwanie zdarzeń
            window.addEventListener('scroll', loadResourcesWhenNeeded);
            window.addEventListener('resize', loadResourcesWhenNeeded);
            
            // Sprawdź raz przy ładowaniu strony
            document.addEventListener('DOMContentLoaded', loadResourcesWhenNeeded);
        })();
        </script>
        <?php
    }

    /**
     * Optymalizuje dane wysyłane do AJAX
     */
    public function optimize_ajax_data($data) {
        // Ogranicz rozmiar plików w żądaniach AJAX
        if (isset($data['files']) && is_array($data['files'])) {
            foreach ($data['files'] as $key => $file) {
                // Jeśli plik jest większy niż 500KB, skróć go
                if (isset($file['content']) && strlen($file['content']) > 500000) {
                    $data['files'][$key]['content'] = substr($file['content'], 0, 500000) . 
                        "\n\n... [Plik skrócony ze względu na rozmiar. Pełna zawartość dostępna w repozytorium.] ...";
                }
            }
        }
        
        return $data;
    }

    /**
     * Obsługuje żądanie przerwania analizy
     */
    public function cancel_analysis() {
        // Sprawdź nonce
        if (!check_ajax_referer('gca_analyzer_nonce', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => 'Weryfikacja bezpieczeństwa nie powiodła się.'
            ));
            exit;
        }
        
        $task_id = isset($_POST['task_id']) ? sanitize_text_field($_POST['task_id']) : '';
        
        if (empty($task_id)) {
            wp_send_json_error(array(
                'message' => 'Identyfikator zadania jest wymagany.'
            ));
            exit;
        }
        
        // Pobierz dane zadania
        $task_data = get_option('gca_task_' . $task_id);
        
        if (!$task_data) {
            wp_send_json_error(array(
                'message' => 'Nie znaleziono zadania o podanym identyfikatorze.'
            ));
            exit;
        }
        
        // Usuń zaplanowane zadanie
        wp_clear_scheduled_hook('gca_process_analysis_task', array($task_id));
        
        // Zaktualizuj status zadania
        $task_data['status'] = 'cancelled';
        $task_data['message'] = 'Analiza została przerwana przez użytkownika.';
        $task_data['completed'] = true;
        
        update_option('gca_task_' . $task_id, $task_data);
        
        wp_send_json_success(array(
            'message' => 'Analiza została przerwana.'
        ));
        exit;
    }
    
    /**
     * Inteligentnie ogranicza rozmiar wyświetlanych bloków kodu
     * 
     * @param string $code Kod do ograniczenia
     * @param int $max_lines Maksymalna liczba linii
     * @return string Ograniczony kod
     */
    public static function limit_code_display($code, $max_lines = 300) {
        $lines = explode("\n", $code);
        
        if (count($lines) <= $max_lines) {
            return $code;
        }
        
        // Pokaż początek i koniec kodu
        $head_lines = floor($max_lines * 0.7);
        $tail_lines = $max_lines - $head_lines;
        
        $head = array_slice($lines, 0, $head_lines);
        $tail = array_slice($lines, -$tail_lines);
        
        $skipped_lines = count($lines) - $max_lines;
        
        return implode("\n", $head) . 
               "\n\n... [Pominięto " . $skipped_lines . " linii kodu. Kliknij 'Pobierz', aby zobaczyć pełną zawartość.] ...\n\n" . 
               implode("\n", $tail);
    }
    
    /**
     * Optymalizuje przetwarzanie dużych repozytoriów
     */
    public static function process_repository_in_batches($repo_url, $file_filters, $max_files) {
        // Inicjalizuj API GitHub
        if (!class_exists('GitHub_API')) {
            require_once GCA_PLUGIN_DIR . 'includes/class-github-api.php';
        }
        
        $github_api = new GitHub_API();
        
        // Pobierz listę plików z repozytorium
        $repo_files = $github_api->get_repository_files_list($repo_url, $file_filters);
        
        if (empty($repo_files) || !isset($repo_files['files'])) {
            throw new Exception('Nie znaleziono plików w repozytorium lub wystąpił błąd podczas pobierania.');
        }
        
        // Ogranicz liczbę plików
        $files = array_slice($repo_files['files'], 0, $max_files);
        
        // Podziel pliki na partie
        $batch_size = 5; // Liczba plików w jednej partii
        $batches = array_chunk($files, $batch_size);
        
      // Przygotuj strukturę wynikową
      $result = array(
        'repository_url' => $repo_url,
        'total_files' => count($files),
        'batches' => array(),
        'batches_count' => count($batches)
    );
    
    // Pobierz zawartość plików dla każdej partii
    foreach ($batches as $batch_index => $batch_files) {
        $batch_result = array(
            'index' => $batch_index,
            'files' => array()
        );
        
        foreach ($batch_files as $file) {
            try {
                // Pobierz zawartość pliku
                $content = $github_api->fetch_github_file_content($file['download_url']);
                
                // Ogranicz rozmiar pliku, jeśli jest zbyt duży
                if (strlen($content) > 500000) {
                    $content = substr($content, 0, 500000) . 
                        "\n\n... [Plik skrócony ze względu na rozmiar. Pełna zawartość dostępna w repozytorium.] ...";
                }
                
                $batch_result['files'][] = array(
                    'name' => $file['name'],
                    'content' => $content,
                    'extension' => $file['extension'],
                    'size' => $file['size']
                );
            } catch (Exception $e) {
                // Zapisz informację o błędzie
                $batch_result['files'][] = array(
                    'name' => $file['name'],
                    'error' => $e->getMessage(),
                    'extension' => $file['extension'],
                    'size' => $file['size']
                );
            }
            
            // Dodaj krótkie opóźnienie, aby uniknąć limitów API GitHub
            usleep(500000); // 0.5 sekundy
        }
        
        $result['batches'][] = $batch_result;
    }
    
    return $result;
}

/**
 * Inteligentne kontynuowanie analizy przy przekroczeniu limitów API
 */
public static function handle_api_rate_limits($github_api, $task_id, $task_data) {
    // Sprawdź aktualny status limitów API
    $limits = $github_api->get_rate_limits();
    
    if ($limits['remaining'] < 10) {
        // Mało zapytań pozostało, zaplanuj wznowienie zadania po resecie limitu
        $reset_time = $limits['reset'];
        $current_time = time();
        
        if ($reset_time > $current_time) {
            $wait_time = $reset_time - $current_time + 60; // Dodaj 1 minutę zapasu
            
            // Zaktualizuj status zadania
            $task_data['status'] = 'waiting_for_rate_limit';
            $task_data['message'] = 'Oczekiwanie na reset limitu zapytań API GitHub. Zostanie wznowione za ' . 
                                    self::format_time_interval($wait_time) . '.';
            $task_data['rate_limit_reset'] = $reset_time;
            
            update_option('gca_task_' . $task_id, $task_data);
            
            // Zaplanuj wznowienie zadania po resecie limitu
            wp_schedule_single_event($reset_time + 60, 'gca_process_analysis_task', array($task_id));
            
            return true; // Zadanie zostało wstrzymane
        }
    }
    
    return false; // Można kontynuować zadanie
}

/**
 * Formatuje interwał czasowy
 */
private static function format_time_interval($seconds) {
    $minutes = floor($seconds / 60);
    $seconds = $seconds % 60;
    
    if ($minutes > 0) {
        return $minutes . ' min ' . $seconds . ' s';
    }
    
    return $seconds . ' s';
}
}