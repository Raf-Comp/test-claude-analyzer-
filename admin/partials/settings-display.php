<?php
// Jeśli ten plik jest wywołany bezpośrednio, przerwij
if (!defined('WPINC')) {
    die;
}
?>

<div class="wrap gca-settings">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php settings_errors('gca_settings'); ?>
    
    <!-- Panel statusu kluczy API -->
    <div class="gca-key-status-panel">
        <?php
        $api_key_status = !empty($api_key_data['api_key']) ? 
            ['status' => 'success', 'message' => 'Klucz API Claude jest skonfigurowany.'] : 
            ['status' => 'error', 'message' => 'Klucz API Claude nie jest skonfigurowany.'];
            
        $github_token_status = !empty($api_key_data['github_token']) ? 
            ['status' => 'success', 'message' => 'Token GitHub jest skonfigurowany.'] : 
            ['status' => 'warning', 'message' => 'Token GitHub nie jest skonfigurowany.'];
        ?>
        
        <div class="gca-notice gca-notice-<?php echo esc_attr($api_key_status['status']); ?>">
            <span><i class="dashicons <?php echo $api_key_status['status'] === 'success' ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></i> <strong><?php echo esc_html($api_key_status['message']); ?></strong></span>
        </div>
        
        <div class="gca-notice gca-notice-<?php echo esc_attr($github_token_status['status']); ?>">
            <span><i class="dashicons <?php echo $github_token_status['status'] === 'success' ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></i> <strong><?php echo esc_html($github_token_status['message']); ?></strong></span>
            <?php if ($github_token_status['status'] !== 'success'): ?>
            <p style="margin-top: 10px; margin-bottom: 0;">Zalecamy dodanie tokenu GitHub nawet dla publicznych repozytoriów, aby zwiększyć limit zapytań do API GitHub (z 60 na 5000 zapytań/godz).</p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="gca-settings-container">
        <div class="gca-settings-primary">
            <div class="gca-card">
                <div class="gca-card-header">
                    <h2><i class="dashicons dashicons-admin-network"></i> Klucze API</h2>
                </div>
                <div class="gca-card-body">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="save_claude_api_key">
                        <?php wp_nonce_field('save_claude_api_key', 'gca_nonce'); ?>
                        
                        <div class="gca-form-group">
                            <label for="claude_api_key">Klucz API Claude</label>
                            <input type="password" id="claude_api_key" name="claude_api_key" class="regular-text" 
                                   value="<?php echo esc_attr($api_key_data['api_key'] ?? ''); ?>" required>
                            <p class="description">
                                Znajdziesz swój klucz API w <a href="https://console.anthropic.com/settings/keys" target="_blank">panelu Anthropic</a>.
                            </p>
                        </div>
                        
                        <div class="gca-form-group">
                            <label for="github_token">Token GitHub (zalecane)</label>
                            <input type="password" id="github_token" name="github_token" class="regular-text" 
                                   value="<?php echo esc_attr($api_key_data['github_token'] ?? ''); ?>">
                            <p class="description">
                                Zalecany nawet dla publicznych repozytoriów, aby zwiększyć limit zapytań. <a href="https://github.com/settings/tokens" target="_blank">Stwórz token GitHub</a>.
                            </p>
                            <!-- Przycisk weryfikacji tokenu -->
                            <button type="button" id="verify-github-token" class="button button-secondary" style="margin-top: 5px;">
                                <i class="dashicons dashicons-yes-alt"></i> Weryfikuj token
                            </button>
                            <div id="github-token-verification-result" style="margin-top: 8px; display: none;"></div>
                        </div>
                        
                        <div class="gca-form-group">
                            <label for="claude_model">Model Claude</label>
                            <select id="claude_model" name="claude_model" class="regular-text">
                                <?php
                                // Pobierz dostępne modele
                                if (class_exists('GitHub_Claude_API') && !empty($api_key_data['api_key'])) {
                                    $claude_api = new GitHub_Claude_API($api_key_data['api_key']);
                                    $available_models = $claude_api->get_available_models();
                                } else {
                                    // Domyślna lista modeli - dodano najnowszy model na pierwszym miejscu
                                    $available_models = array(
                                        'claude-3-7-sonnet-20250219',
                                        'claude-3-5-sonnet-20240620',
                                        'claude-3-opus-20240229',
                                        'claude-3-haiku-20240307',
                                        'claude-3-sonnet-20240229'
                                    );
                                }
                                
                                // Dodaj opcje wyboru modelu
                                foreach ($available_models as $model_name) {
                                    $selected = selected($api_key_data['model'] ?? 'claude-3-7-sonnet-20250219', $model_name, false);
                                    $model_display_name = str_replace('-', ' ', $model_name);
                                    
                                    // Dodaj opis dla modelu
                                    $model_description = '';
                                    if (strpos($model_name, 'opus') !== false) {
                                        $model_description = ' (najdokładniejszy)';
                                    } elseif (strpos($model_name, 'haiku') !== false) {
                                        $model_description = ' (najszybszy)';
                                    } elseif (strpos($model_name, '3-7') !== false) {
                                        $model_description = ' (najnowszy, zalecany)';
                                    } elseif (strpos($model_name, '3-5') !== false) {
                                        $model_description = ' (zrównoważony)';
                                    }
                                    
                                    echo '<option value="' . esc_attr($model_name) . '" ' . $selected . '>' . 
                                        ucwords($model_display_name) . $model_description . 
                                        '</option>';
                                }
                                ?>
                            </select>
                            <p class="description">
                                Wybierz model Claude do analizy repozytoriów. Lista modeli jest automatycznie aktualizowana na podstawie dostępnych modeli w API Claude.
                            </p>
                            
                            <!-- Przycisk do odświeżania modeli -->
                            <button type="button" id="refresh-models" class="button button-secondary">
                                <i class="dashicons dashicons-update"></i> Odśwież modele
                            </button>
                        </div>
                        
                        <div class="gca-form-submit">
                            <button type="submit" class="button button-primary">Zapisz ustawienia</button>
                            <button type="button" id="test-claude-connection" class="button button-secondary">Testuj połączenie</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Informacje o koncie Claude -->
            <div class="gca-card" id="account-info-card" style="<?php echo empty($account_info) && empty($connection_error) ? 'display:none;' : ''; ?>">
                <div class="gca-card-header">
                    <h2><i class="dashicons dashicons-info"></i> Informacje o koncie Claude</h2>
                </div>
                <div class="gca-card-body">
                    <?php if (!empty($connection_error)): ?>
                        <div class="gca-error-message">
                            <p><strong>Błąd połączenia:</strong> <?php echo esc_html($connection_error); ?></p>
                        </div>
                    <?php elseif (!empty($account_info)): ?>
                        <div class="gca-account-info">
                            <p><strong>Status konta:</strong> <?php echo esc_html($account_info['status'] ?? 'Aktywne'); ?></p>
                            
                            <?php if (isset($account_info['models']) && !empty($account_info['models'])): ?>
                            <div class="gca-info-item">
                                <span class="gca-info-label">Dostępne modele:</span>
                                <span class="gca-info-value"><?php echo esc_html(implode(', ', $account_info['models'])); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($account_info['current_model'])): ?>
                            <div class="gca-info-item">
                                <span class="gca-info-label">Wybrany model:</span>
                                <span class="gca-info-value">
                                    <?php echo esc_html($account_info['current_model']); ?>
                                    <?php if (isset($account_info['model_available'])): ?>
                                        <?php if ($account_info['model_available']): ?>
                                            <span style="color: green;">✓ dostępny</span>
                                        <?php else: ?>
                                            <span style="color: red;">✗ niedostępny</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($account_info['max_tokens'])): ?>
                            <div class="gca-info-item">
                                <span class="gca-info-label">Maksymalna liczba tokenów:</span>
                                <span class="gca-info-value"><?php echo esc_html($account_info['max_tokens']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Dodatkowy panel informacji o GitHub -->
            <?php 
            // Sprawdź czy mamy szczegóły tokenu GitHub
            $has_github_token_details = !empty($github_token_status) && 
                                       isset($github_token_status['details']) && 
                                       !empty($github_token_status['details']);
            ?>

            <?php if (!empty($api_key_data['github_token']) && $has_github_token_details): ?>
            <div class="gca-card">
                <div class="gca-card-header">
                    <h2><i class="dashicons dashicons-github"></i> Informacje o API GitHub</h2>
                </div>
                <div class="gca-card-body">
                    <div class="gca-account-info">
                        <?php if (isset($github_token_status['details']) && $github_token_status['details']): ?>
                            <div class="gca-info-item">
                                <span class="gca-info-label">Limit zapytań:</span>
                                <span class="gca-info-value"><?php echo esc_html($github_token_status['details']['limit']); ?> na godzinę</span>
                            </div>
                            
                            <div class="gca-info-item">
                                <span class="gca-info-label">Pozostałe zapytania:</span>
                                <span class="gca-info-value"><?php echo esc_html($github_token_status['details']['remaining']); ?></span>
                            </div>
                            
                            <div class="gca-info-item">
                                <span class="gca-info-label">Reset limitu:</span>
                                <span class="gca-info-value"><?php echo esc_html($github_token_status['details']['reset']); ?></span>
                            </div>
                            
                            <div class="gca-info-item">
                                <span class="gca-info-label">Status:</span>
                                <span class="gca-info-value">
                                    <?php if (isset($github_token_status['details']['authenticated']) && $github_token_status['details']['authenticated']): ?>
                                        <span style="color: green;">✓ Poprawnie uwierzytelniony (zwiększony limit)</span>
                                    <?php else: ?>
                                        <span style="color: orange;">⚠️ Podstawowy limit (60 zapytań/godz)</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php else: ?>
                            <p>Nie udało się pobrać informacji o limicie API GitHub.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="gca-settings-secondary">
            <div class="gca-card">
                <div class="gca-card-header">
                    <h2><i class="dashicons dashicons-admin-settings"></i> Ustawienia globalne</h2>
                </div>
                <div class="gca-card-body">
                    <form method="post" action="options.php">
                        <?php settings_fields('gca_settings_group'); ?>
                        
                        <div class="gca-form-group">
                            <label for="max_tokens">Maksymalna liczba tokenów</label>
                            <input type="number" id="max_tokens" name="gca_settings[max_tokens]" class="regular-text" 
                                   value="<?php echo esc_attr($settings['max_tokens'] ?? 4000); ?>" min="1000" max="100000">
                            <p class="description">
                                Maksymalna długość odpowiedzi generowanej przez Claude.
                            </p>
                        </div>
                        
                        <div class="gca-form-group">
                            <label for="max_files_per_analysis">Maksymalna liczba plików na analizę</label>
                            <input type="number" id="max_files_per_analysis" name="gca_settings[max_files_per_analysis]" class="regular-text" 
                                   value="<?php echo esc_attr($settings['max_files_per_analysis'] ?? 30); ?>" min="5" max="150">
                            <p class="description">
                                Maksymalna liczba plików, które mogą być przeanalizowane na raz.
                            </p>
                        </div>
                        
                        <div class="gca-form-group">
                            <label>Domyślne rozszerzenia plików</label>
                            <div class="gca-checkbox-group">
                                <?php
                                $default_extensions = $settings['default_extensions'] ?? array('php', 'js', 'css', 'html');
                                $extensions = array(
                                    'php' => 'PHP',
                                    'js' => 'JavaScript',
                                    'css' => 'CSS',
                                    'html' => 'HTML',
                                    'md' => 'Markdown',
                                    'json' => 'JSON',
                                    'txt' => 'TXT'
                                );
                                
                                foreach ($extensions as $ext => $label) {
                                    $checked = in_array($ext, $default_extensions) ? 'checked' : '';
                                    echo '<label class="gca-checkbox">
                                            <input type="checkbox" name="gca_settings[default_extensions][]" value="' . esc_attr($ext) . '" ' . $checked . '>
                                            ' . esc_html($label) . '
                                          </label>';
                                }
                                ?>
                            </div>
                            <p class="description">
                                Domyślne typy plików do przeanalizowania.
                            </p>
                        </div>
                        
                        <?php submit_button('Zapisz ustawienia globalne'); ?>
                    </form>
                </div>
            </div>
            
            <div class="gca-card">
                <div class="gca-card-header">
                    <h2><i class="dashicons dashicons-editor-help"></i> Pomoc</h2>
                </div>
                <div class="gca-card-body">
                    <p>Ta wtyczka umożliwia analizę repozytoriów GitHub przy użyciu sztucznej inteligencji Claude.</p>
                    <p><strong>Jak używać:</strong></p>
                    <ol>
                        <li>Wprowadź klucz API Claude i token GitHub (zalecane).</li>
                        <li>Użyj shortcode <code>[github_claude_analyzer]</code> na dowolnej stronie lub wpisie.</li>
                        <li>Wypełnij formularz z URL repozytorium i opcjami analizy.</li>
                        <li>Otrzymasz szczegółową analizę kodu z repozytorium.</li>
                    </ol>
                    
                    <div class="gca-notice gca-notice-warning" style="margin-top: 15px;">
                        <p><strong>Wskazówka:</strong> Zalecamy dodanie tokenu GitHub nawet dla publicznych repozytoriów, aby uniknąć limitów API (60 zapytań/godz dla niezautoryzowanych żądań vs 5000 zapytań/godz z tokenem).</p>
                    </div>
                    
                    <p style="margin-top: 15px;">
                        <a href="https://github.com/settings/tokens" target="_blank" class="button button-secondary">
                            <i class="dashicons dashicons-github"></i> Utwórz token GitHub
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <div id="connection-test-result" class="gca-connection-result" style="display:none;">
        <div class="gca-result-content"></div>
    </div>
</div>s