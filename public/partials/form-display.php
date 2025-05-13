<?php
// Jeśli ten plik jest wywołany bezpośrednio, przerwij
if (!defined('WPINC')) {
    die;
}
?>

<div class="gca-analyzer-container" style="max-width: 1000px; margin: 0 auto; padding: 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #333;">
    <?php if (!$has_api_keys): ?>
        <div style="padding: 12px 16px; border-radius: 4px; margin-bottom: 24px; background-color: #fff3cd; border: 1px solid #ffeeba; color: #856404;">
            <p>
                <?php if ($show_settings_notice): ?>
                    Nie skonfigurowano kluczy API. <a href="<?php echo admin_url('admin.php?page=github-claude-analyzer-settings'); ?>">Przejdź do ustawień</a> i dodaj klucz API Claude.
                <?php else: ?>
                    Zaloguj się, aby używać analizatora repozytoriów GitHub.
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <div <?php echo !$has_api_keys ? 'style="display:none;"' : 'style="display:block;"'; ?>>
        <h2 style="font-size: 24px; margin-bottom: 20px; font-weight: 600;">Analiza repozytorium kodu</h2>
        
        <div style="background-color: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); margin-bottom: 24px;">
            <div style="display: flex; align-items: center; padding: 15px 20px; border-bottom: 1px solid #e5e7eb; background-color: #f9fafb;">
                <span style="display: inline-flex; align-items: center; font-size: 16px; font-weight: 600; color: #111827;">
                    <i class="dashicons dashicons-admin-site" style="margin-right: 8px;"></i> Analiza Repozytorium
                </span>
            </div>
            
            <div style="padding: 20px;">
                <div style="background-color: #f0f7ff; border-left: 4px solid #3b82f6; padding: 16px; margin-bottom: 24px; border-radius: 0 4px 4px 0;">
                    <h3 style="margin-top: 0; margin-bottom: 8px; font-size: 16px; color: #1e40af;">
                        <i class="dashicons dashicons-info" style="margin-right: 8px;"></i> Informacja
                    </h3>
                    <p style="margin-top: 0; margin-bottom: 10px;">To narzędzie używa Claude AI do analizy repozytoriów kodu. Podaj URL repozytorium, aby rozpocząć analizę.</p>
                    <p style="margin-top: 0; margin-bottom: 0;"><strong>Uwaga:</strong> Dla prywatnych repozytoriów konieczne jest skonfigurowanie odpowiedniego tokenu w ustawieniach.</p>
                </div>
                
                <form id="gca-analyze-form">
                    <?php wp_nonce_field('gca_analyzer_nonce', 'gca_nonce'); ?>
                    
                    <!-- Dodajemy wybór typu repozytorium -->
                    <div style="margin-bottom: 20px;">
                        <label for="repo_type" style="display: block; margin-bottom: 8px; font-weight: 500;">Typ repozytorium:</label>
                        <div style="display: flex; gap: 15px;">
                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="radio" name="repo_type" value="github" checked style="margin-right: 5px;">
                                <span style="display: inline-flex; align-items: center;">
                                    <img src="<?php echo GCA_PLUGIN_URL; ?>public/img/github-logo.svg" alt="GitHub" style="width: 20px; height: 20px; margin-right: 5px;">
                                    GitHub
                                </span>
                            </label>
                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="radio" name="repo_type" value="gitlab" style="margin-right: 5px;">
                                <span style="display: inline-flex; align-items: center;">
                                    <img src="<?php echo GCA_PLUGIN_URL; ?>public/img/gitlab-logo.svg" alt="GitLab" style="width: 20px; height: 20px; margin-right: 5px;">
                                    GitLab
                                </span>
                            </label>
                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="radio" name="repo_type" value="bitbucket" style="margin-right: 5px;">
                                <span style="display: inline-flex; align-items: center;">
                                    <img src="<?php echo GCA_PLUGIN_URL; ?>public/img/bitbucket-logo.svg" alt="Bitbucket" style="width: 20px; height: 20px; margin-right: 5px;">
                                    Bitbucket
                                </span>
                            </label>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label for="repo_url" style="display: block; margin-bottom: 8px; font-weight: 500;">URL Repozytorium:</label>
                        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                            <div style="flex: 1;">
                                <input type="text" id="repo_url" name="repo_url" required 
                                      placeholder="https://github.com/username/repository"
                                      style="flex: 1; padding: 10px 12px; border-radius: 4px 0 0 4px; border: 1px solid #d1d5db; font-size: 16px; box-sizing: border-box;">
                                <button type="button" id="verify-repo-btn" 
                                        style="display: inline-flex; align-items: center; justify-content: center; padding: 8px 12px; border-radius: 0 4px 4px 0; font-weight: 500; cursor: pointer; border: 1px solid #d1d5db; background-color: #f3f4f6; color: #374151; border-left: none; font-size: 14px;">
                                    <i class="dashicons dashicons-search" style="margin-right: 3px; font-size: 16px;"></i> Weryfikuj
                                </button>
                            </div>
                            <button type="button" id="search-repo-btn" 
                                    style="display: inline-flex; align-items: center; justify-content: center; padding: 10px 16px; border-radius: 4px; font-weight: 500; cursor: pointer; border: none; background-color: #6366f1; color: white;">
                                <i class="dashicons dashicons-search" style="margin-right: 5px;"></i> Znajdź repozytorium
                            </button>
                        </div>
                        <div id="repo-verification-result" style="margin-top: 10px; display: none;"></div>
                        <p class="repo-url-info github-info" style="font-size: 14px; color: #6b7280; margin-top: 4px; margin-bottom: 0;">Format GitHub: https://github.com/username/repository</p>
                        <p class="repo-url-info gitlab-info" style="font-size: 14px; color: #6b7280; margin-top: 4px; margin-bottom: 0; display: none;">Format GitLab: https://gitlab.com/username/repository</p>
                        <p class="repo-url-info bitbucket-info" style="font-size: 14px; color: #6b7280; margin-top: 4px; margin-bottom: 0; display: none;">Format Bitbucket: https://bitbucket.org/username/repository</p>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 500;">Filtruj pliki:</label>
                        <div style="display: flex; flex-wrap: wrap; gap: 16px; margin-top: 12px;">
                            <?php foreach ($default_extensions as $ext): ?>
                                <div style="display: flex; align-items: center;">
                                    <input type="checkbox" id="filter_<?php echo esc_attr($ext); ?>" 
                                        name="file_filters[]" value="<?php echo esc_attr($ext); ?>" checked
                                        style="margin-right: 8px;">
                                    <label for="filter_<?php echo esc_attr($ext); ?>" style="font-size: 14px;"><?php echo strtoupper(esc_html($ext)); ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label for="other_extensions" style="display: block; margin-bottom: 8px; font-weight: 500;">Dodatkowe rozszerzenia (oddzielone przecinkami):</label>
                        <input type="text" id="other_extensions" name="other_extensions" 
                            placeholder="md,json,txt"
                            style="width: 100%; padding: 10px 12px; border-radius: 4px; border: 1px solid #d1d5db; font-size: 16px; box-sizing: border-box;">
                        <p style="font-size: 14px; color: #6b7280; margin-top: 4px; margin-bottom: 0;">Podaj inne rozszerzenia plików, które chcesz uwzględnić w analizie.</p>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label for="max_files" style="display: block; margin-bottom: 8px; font-weight: 500;">Maksymalna liczba plików:</label>
                        <input type="number" id="max_files" name="max_files" 
                            value="<?php echo esc_attr($settings['max_files_per_analysis'] ?? 30); ?>" min="1" max="150"
                            style="width: 100%; padding: 10px 12px; border-radius: 4px; border: 1px solid #d1d5db; font-size: 16px; box-sizing: border-box;">
                        <p style="font-size: 14px; color: #6b7280; margin-top: 4px; margin-bottom: 0;">Maksymalna liczba plików do pobrania z repozytorium (do 150).</p>
                    </div>
                    
                    <!-- Dodajemy nową opcję ograniczenia katalogów -->
                    <div style="margin-bottom: 20px;">
                        <label for="directory_path" style="display: block; margin-bottom: 8px; font-weight: 500;">Ograniczenie do katalogu (opcjonalne):</label>
                        <input type="text" id="directory_path" name="directory_path" 
                            placeholder="src/main lub scieżka/do/katalogu"
                            style="width: 100%; padding: 10px 12px; border-radius: 4px; border: 1px solid #d1d5db; font-size: 16px; box-sizing: border-box;">
                        <p style="font-size: 14px; color: #6b7280; margin-top: 4px; margin-bottom: 0;">Ogranicz analizę do określonego katalogu w repozytorium.</p>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label for="prompt" style="display: block; margin-bottom: 8px; font-weight: 500;">Zapytanie do Claude AI:</label>
                        <textarea id="prompt" name="prompt" 
                                placeholder="Przeanalizuj te pliki i opisz co robią. Skup się na głównej funkcjonalności i architekturze."
                                style="width: 100%; min-height: 120px; padding: 10px 12px; border-radius: 4px; border: 1px solid #d1d5db; font-size: 16px; box-sizing: border-box; resize: vertical;">Przeanalizuj te pliki i opisz co robią. Skup się na głównej funkcjonalności i architekturze. Opisz strukturę projektu, użyte technologie i wzorce projektowe.</textarea>
                    </div>
                    
                    <!-- Dodajemy nową sekcję zaawansowanych opcji analizy -->
                    <div style="margin-bottom: 20px;">
                        <details>
                            <summary style="cursor: pointer; font-weight: 500; color: #374151; padding: 5px 0;">
                                <i class="dashicons dashicons-admin-generic" style="margin-right: 5px;"></i> Zaawansowane opcje analizy
                            </summary>
                            <div style="margin-top: 10px; padding: 15px; background-color: #f9fafb; border-radius: 4px;">
                                <div style="margin-bottom: 15px;">
                                    <label for="analysis_depth" style="display: block; margin-bottom: 5px; font-weight: 500;">Głębokość analizy:</label>
                                    <select id="analysis_depth" name="analysis_depth" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #d1d5db;">
                                        <option value="basic">Podstawowa - szybka analiza ogólna</option>
                                        <option value="standard" selected>Standardowa - zbalansowana analiza</option>
                                        <option value="deep">Dogłębna - szczegółowa analiza (wolniejsza)</option>
                                    </select>
                                </div>
                                
                                <div style="margin-bottom: 15px;">
                                    <label style="display: flex; align-items: center;">
                                        <input type="checkbox" id="ignore_tests" name="ignore_tests" style="margin-right: 8px;">
                                        <span>Ignoruj pliki testów (test*, spec*, *_test.*, *_spec.*)</span>
                                    </label>
                                </div>
                                
                                <div style="margin-bottom: 0;">
                                    <label style="display: flex; align-items: center;">
                                        <input type="checkbox" id="include_dependencies" name="include_dependencies" style="margin-right: 8px;">
                                        <span>Uwzględnij pliki konfiguracyjne zależności (package.json, composer.json, itp.)</span>
                                    </label>
                                </div>
                            </div>
                        </details>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: flex; align-items: center;">
                            <input type="checkbox" id="force_reanalysis" name="force_reanalysis" style="margin-right: 8px;">
                            <span style="font-size: 14px;">Wymuś ponowną analizę (ignoruj historię przetworzonych plików)</span>
                        </label>
                        <p style="font-size: 14px; color: #6b7280; margin-top: 4px; margin-bottom: 0;">Zaznacz tę opcję, jeśli chcesz przeanalizować wszystkie pliki na nowo, ignorując poprzednie analizy.</p>
                    </div>
                    
                    <div style="display: flex; justify-content: flex-end; margin-top: 24px;">
                        <button type="submit" style="display: inline-flex; align-items: center; justify-content: center; padding: 10px 16px; border-radius: 4px; font-weight: 500; cursor: pointer; border: none; font-size: 15px; background-color: #6366f1; color: white;">
                            <i class="dashicons dashicons-controls-play" style="margin-right: 8px;"></i>
                            Analizuj repozytorium
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div id="gca-loader" style="display:none; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); margin-bottom: 24px; padding: 32px; text-align: center;">
            <h3 style="font-size: 18px; margin-top: 0; margin-bottom: 20px;">Analizowanie repozytorium...</h3>
            <div style="width: 100%; height: 10px; background-color: #e5e7eb; border-radius: 5px; overflow: hidden; margin: 15px 0;">
                <div id="gca-progress-bar" style="height: 100%; background-color: #6366f1; border-radius: 5px; width: 0%; transition: width 0.3s ease;"></div>
            </div>
            <p class="gca-batch-progress" style="font-style: italic; color: #666; margin-top: 10px;">Inicjowanie analizy...</p>
            <div style="width: 50px; height: 50px; margin: 20px auto; border: 5px solid rgba(99, 102, 241, 0.2); border-top-color: #6366f1; border-radius: 50%; animation: gca-spin 1s linear infinite;"></div>
            <p style="margin-top: 10px;">To może potrwać kilka minut, w zależności od rozmiaru repozytorium i złożoności analizy.</p>
            <div style="background-color: #f9fafb; border-radius: 4px; padding: 15px; margin-top: 20px; text-align: left;">
                <div style="margin-bottom: 8px;">Aktualnie przetwarzany plik: <span id="current-file">Oczekiwanie...</span></div>
                <div>Przetworzono: <span id="processed-count">0</span> / <span id="total-count">0</span> plików</div>
            </div>
            
            <!-- Dodany przycisk anulowania -->
            <button id="cancel-analysis-btn" class="gca-cancel-analysis-btn" style="margin-top: 20px; padding: 10px 15px; background-color: #ef4444; color: white; border: none; border-radius: 4px; cursor: pointer; display: inline-flex; align-items: center;">
                <i class="dashicons dashicons-no-alt" style="margin-right: 5px;"></i> Anuluj analizę
            </button>
        </div>
        
        <div id="gca-results" style="display:none;"></div>
    </div>
</div>

<style>
@keyframes gca-spin {
    to {transform: rotate(360deg);}
}

/* Okno modalne wyszukiwania */
#repo-search-modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.4);
    padding-top: 50px;
}

#repo-search-modal .modal-content {
    background-color: #fefefe;
    margin: auto;
    padding: 20px;
    border-radius: 8px;
    width: 80%;
    max-width: 800px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

/* Dodatkowe style resetujące, aby zapobiec konfliktom z motywem */
#gca-analyze-form input[type="text"],
#gca-analyze-form input[type="number"],
#gca-analyze-form textarea,
#gca-analyze-form select {
    width: 100% !important;
    padding: 10px 12px !important;
    border-radius: 4px !important;
    border: 1px solid #d1d5db !important;
    font-size: 16px !important;
    box-sizing: border-box !important;
    margin: 0 !important;
}

#gca-analyze-form button {
    border-radius: 4px !important;
    font-weight: 500 !important;
    cursor: pointer !important;
    line-height: normal !important;
    height: auto !important;
}

/* Style dla detailsów */
details summary::-webkit-details-marker {
    display: none;
}

details summary::before {
    content: '►';
    display: inline-block;
    margin-right: 5px;
    transition: transform 0.3s;
}

details[open] summary::before {
    transform: rotate(90deg);
}
</style>

<!-- Modal do wyszukiwania repozytoriów -->
<div id="repo-search-modal">
    <div class="modal-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; font-size: 18px;">Wyszukaj repozytorium</h3>
            <button id="close-modal-btn" style="background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
        </div>
        
        <div style="margin-bottom: 20px;">
            <div style="display: flex;">
                <input type="text" id="repo-search-input" placeholder="Wpisz nazwę repozytorium lub użytkownika" 
                       style="flex: 1; padding: 10px 12px; border-radius: 4px 0 0 4px; border: 1px solid #d1d5db; font-size: 16px;">
                <button id="repo-search-btn" 
                        style="display: inline-flex; align-items: center; justify-content: center; padding: 10px 16px; border-radius: 0 4px 4px 0; font-weight: 500; cursor: pointer; border: none; background-color: #6366f1; color: white; border-left: none;">
                    <i class="dashicons dashicons-search" style="margin-right: 5px;"></i> Szukaj
                </button>
            </div>
            <p style="font-size: 14px; color: #6b7280; margin-top: 8px;">
                Możesz szukać według nazwy repozytorium, użytkownika lub organizacji.
            </p>
        </div>
        
        <div id="repo-search-results" style="margin-top: 20px; max-height: 400px; overflow-y: auto;">
            <p>Wyniki wyszukiwania pojawią się tutaj.</p>
        </div>
        
        <div id="repo-search-loading" style="display: none; text-align: center; padding: 20px;">
            <div style="display: inline-block; width: 40px; height: 40px; border: 4px solid rgba(99, 102, 241, 0.2); border-radius: 50%; border-top-color: #6366f1; animation: gca-spin 1s linear infinite;"></div>
            <p>Wyszukiwanie repozytoriów...</p>
        </div>
        
        <div id="repo-search-pagination" style="margin-top: 20px; display: none; text-align: center;">
            <button id="repo-prev-page" style="padding: 8px 16px; background-color: #f3f4f6; border: 1px solid #d1d5db; border-radius: 4px; margin-right: 10px; cursor: pointer;">
                <i class="dashicons dashicons-arrow-left-alt"></i> Poprzednia
            </button>
            <span id="repo-page-info" style="display: inline-block; padding: 8px 0;">Strona 1</span>
            <button id="repo-next-page" style="padding: 8px 16px; background-color: #f3f4f6; border: 1px solid #d1d5db; border-radius: 4px; margin-left: 10px; cursor: pointer;">
                Następna <i class="dashicons dashicons-arrow-right-alt"></i>
            </button>
        </div>
    </div>
</div>