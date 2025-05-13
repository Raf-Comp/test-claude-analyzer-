<?php
// Jeśli ten plik jest wywołany bezpośrednio, przerwij
if (!defined('WPINC')) {
    die;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="gca-diagnostics-container">
        <div class="gca-card">
            <div class="gca-card-header">
                <h2><i class="dashicons dashicons-admin-tools"></i> Diagnostyka GitHub Claude Analyzer</h2>
            </div>
            <div class="gca-card-body">
                <p>Na tej stronie możesz przeprowadzić testy diagnostyczne, aby zweryfikować poprawność działania wtyczki.</p>
                
                <button id="run-full-diagnostics" class="button button-primary">
                    <i class="dashicons dashicons-search"></i> Przeprowadź pełną diagnostykę
                </button>
                
                <div id="diagnostics-results" style="margin-top: 20px; display: none;">
                    <div class="gca-spinner" style="display: block; margin: 20px auto;"></div>
                    <p class="diagnostics-status">Trwa przeprowadzanie testów diagnostycznych...</p>
                </div>
            </div>
        </div>
        
        <div class="gca-card">
            <div class="gca-card-header">
                <h2><i class="dashicons dashicons-database"></i> Status bazy danych</h2>
            </div>
            <div class="gca-card-body">
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Tabela</th>
                            <th>Status</th>
                            <th>Liczba rekordów</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($database_status as $table => $status): ?>
                            <tr>
                                <td><?php echo esc_html($table); ?></td>
                                <td>
                                    <?php if ($status['exists']): ?>
                                        <span class="dashicons dashicons-yes" style="color: green;"></span> Istnieje
                                    <?php else: ?>
                                        <span class="dashicons dashicons-no" style="color: red;"></span> Nie istnieje
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($status['exists']): ?>
                                        <?php echo esc_html($status['records']); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div style="margin-top: 15px;">
                    <button id="repair-database" class="button button-secondary">
                        <i class="dashicons dashicons-database-view"></i> Napraw tabele bazy danych
                    </button>
                </div>
            </div>
        </div>
        
        <div class="gca-card">
            <div class="gca-card-header">
                <h2><i class="dashicons dashicons-media-code"></i> Uprawnienia plików</h2>
            </div>
            <div class="gca-card-body">
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Plik</th>
                            <th>Status</th>
                            <th>Uprawnienia</th>
                            <th>Rozmiar</th>
                            <th>Ostatnia modyfikacja</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($files_permissions as $file => $status): ?>
                            <tr>
                                <td><?php echo esc_html($file); ?></td>
                                <td>
                                    <?php if ($status['exists']): ?>
                                        <span class="dashicons dashicons-yes" style="color: green;"></span> Istnieje
                                    <?php else: ?>
                                        <span class="dashicons dashicons-no" style="color: red;"></span> Nie istnieje
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($status['exists']): ?>
                                        <?php echo esc_html($status['permissions']); ?>
                                        <?php if ($status['readable']): ?>
                                            <span class="dashicons dashicons-visibility" style="color: green;" title="Plik jest czytelny"></span>
                                        <?php else: ?>
                                            <span class="dashicons dashicons-hidden" style="color: red;" title="Plik nie jest czytelny"></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($status['exists']): ?>
                                        <?php echo esc_html(size_format($status['size'])); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($status['exists']): ?>
                                        <?php echo esc_html($status['modified']); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="gca-card">
            <div class="gca-card-header">
                <h2><i class="dashicons dashicons-admin-network"></i> Status API</h2>
            </div>
            <div class="gca-card-body">
                <h3>Claude API</h3>
                <div class="gca-api-status">
                    <?php if ($claude_api_status['valid']): ?>
                        <div class="gca-notice gca-notice-success">
                            <span class="dashicons dashicons-yes-alt"></span> <?php echo esc_html($claude_api_status['message']); ?>
                        </div>
                        
                        <?php if (!empty($claude_api_status['details'])): ?>
                            <div class="gca-account-info">
                                <h4>Informacje o koncie Claude</h4>
                                <?php if (isset($claude_api_status['details']['status'])): ?>
                                    <div class="gca-info-item">
                                        <span class="gca-info-label">Status konta:</span>
                                        <span class="gca-info-value"><?php echo esc_html($claude_api_status['details']['status']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($claude_api_status['details']['current_model'])): ?>
                                    <div class="gca-info-item">
                                        <span class="gca-info-label">Aktualny model:</span>
                                        <span class="gca-info-value">
                                            <?php echo esc_html($claude_api_status['details']['current_model']); ?>
                                            <?php if (isset($claude_api_status['details']['model_available'])): ?>
                                                <?php if ($claude_api_status['details']['model_available']): ?>
                                                    <span style="color: green;">(dostępny)</span>
                                                <?php else: ?>
                                                    <span style="color: red;">(niedostępny)</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($claude_api_status['details']['models']) && is_array($claude_api_status['details']['models'])): ?>
                                    <div class="gca-info-item">
                                        <span class="gca-info-label">Dostępne modele:</span>
                                        <span class="gca-info-value"><?php echo esc_html(implode(', ', $claude_api_status['details']['models'])); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="gca-notice gca-notice-error">
                            <span class="dashicons dashicons-warning"></span> <?php echo esc_html($claude_api_status['message']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <button id="test-claude-connection" class="button button-secondary" style="margin-top: 10px;">
                        <i class="dashicons dashicons-update"></i> Testuj połączenie z Claude API
                    </button>
                </div>
                
                <h3 style="margin-top: 20px;">GitHub API</h3>
                <div class="gca-api-status">
                    <?php if ($github_api_status['valid']): ?>
                        <div class="gca-notice gca-notice-success">
                            <span class="dashicons dashicons-yes-alt"></span> <?php echo esc_html($github_api_status['message']); ?>
                        </div>
                        
                        <?php if (!empty($github_api_status['details'])): ?>
                            <div class="gca-account-info">
                                <h4>Informacje o tokenie GitHub</h4>
                                <?php if (isset($github_api_status['details']['limit'])): ?>
                                    <div class="gca-info-item">
                                        <span class="gca-info-label">Limit zapytań:</span>
                                        <span class="gca-info-value"><?php echo esc_html($github_api_status['details']['limit']); ?> / godzinę</span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($github_api_status['details']['remaining'])): ?>
                                    <div class="gca-info-item">
                                        <span class="gca-info-label">Pozostało zapytań:</span>
                                        <span class="gca-info-value"><?php echo esc_html($github_api_status['details']['remaining']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($github_api_status['details']['reset'])): ?>
                                    <div class="gca-info-item">
                                        <span class="gca-info-label">Reset limitu:</span>
                                        <span class="gca-info-value"><?php echo esc_html($github_api_status['details']['reset']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="gca-notice gca-notice-warning">
                            <span class="dashicons dashicons-warning"></span> <?php echo esc_html($github_api_status['message']); ?>
                        </div>
                        <p class="description">
                            Token GitHub jest opcjonalny, ale zalecany, aby zwiększyć limit zapytań do API GitHub.
                            Bez tokenu limit wynosi 60 zapytań na godzinę, z tokenem - 5000 zapytań na godzinę.
                        </p>
                    <?php endif; ?>
                    
                    <button id="test-github-connection" class="button button-secondary" style="margin-top: 10px;">
                        <i class="dashicons dashicons-update"></i> Testuj połączenie z GitHub API
                    </button>
                </div>
            </div>
        </div>
        
        <div class="gca-card">
            <div class="gca-card-header">
                <h2><i class="dashicons dashicons-info"></i> Informacje systemowe</h2>
            </div>
            <div class="gca-card-body">
                <table class="widefat">
                    <tbody>
                        <tr>
                            <th>Wersja PHP:</th>
                            <td><?php echo esc_html(phpversion()); ?></td>
                        </tr>
                        <tr>
                            <th>Wersja WordPress:</th>
                            <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                        </tr>
                        <tr>
                            <th>Wersja wtyczki:</th>
                            <td><?php echo esc_html(GCA_VERSION); ?></td>
                        </tr>
                        <tr>
                            <th>Limit pamięci PHP:</th>
                            <td><?php echo esc_html(ini_get('memory_limit')); ?></td>
                        </tr>
                        <tr>
                            <th>Maksymalny czas wykonania:</th>
                            <td><?php echo esc_html(ini_get('max_execution_time')); ?> sekund</td>
                        </tr>
                        <tr>
                            <th>Maksymalny rozmiar POST:</th>
                            <td><?php echo esc_html(ini_get('post_max_size')); ?></td>
                        </tr>
                        <tr>
                            <th>Maksymalny rozmiar wysyłanego pliku:</th>
                            <td><?php echo esc_html(ini_get('upload_max_filesize')); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Pełna diagnostyka
    $('#run-full-diagnostics').on('click', function() {
        const resultsDiv = $('#diagnostics-results');
        resultsDiv.show();
        
        $.ajax({
            url: gca_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'gca_verify_plugin',
                nonce: gca_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    let html = '<h3>Wyniki diagnostyki</h3>';
                    
                    // System info
                    html += '<div class="gca-section">';
                    html += '<h4>Informacje systemowe</h4>';
                    html += '<ul>';
                    html += `<li>PHP: ${response.data.system_info.php_version}</li>`;
                    html += `<li>WordPress: ${response.data.system_info.wordpress_version}</li>`;
                    html += `<li>Wersja wtyczki: ${response.data.system_info.plugin_version}</li>`;
                    html += `<li>Limit pamięci: ${response.data.system_info.memory_limit}</li>`;
                    html += `<li>Max czas wykonania: ${response.data.system_info.max_execution_time} sek.</li>`;
                    html += '</ul>';
                    html += '</div>';
                    
                    // Baza danych
                    html += '<div class="gca-section">';
                    html += '<h4>Status bazy danych</h4>';
                    html += '<ul>';
                    let dbOk = true;
                    
                    $.each(response.data.db_status, function(table, status) {
                        if (status.exists) {
                            html += `<li>${table}: <span style="color: green;">✓ OK</span> (${status.records} rekordów)</li>`;
                        } else {
                            html += `<li>${table}: <span style="color: red;">✗ Brak tabeli</span></li>`;
                            dbOk = false;
                        }
                    });
                    
                    html += '</ul>';
                    if (!dbOk) {
                        html += '<div class="notice notice-error"><p>Wykryto problemy z bazą danych. Kliknij "Napraw tabele bazy danych" poniżej.</p></div>';
                    }
                    
                    html += '</div>';
                    
                    // Pliki
                    html += '<div class="gca-section">';
                    html += '<h4>Status plików</h4>';
                    html += '<ul>';
                    let filesOk = true;
                    
                    $.each(response.data.files_status, function(file, status) {
                        if (status.exists && status.readable) {
                            html += `<li>${file}: <span style="color: green;">✓ OK</span> (${status.permissions})</li>`;
                        } else if (status.exists && !status.readable) {
                            html += `<li>${file}: <span style="color: orange;">⚠ Nieczytelny</span> (${status.permissions})</li>`;
                            filesOk = false;
                        } else {
                            html += `<li>${file}: <span style="color: red;">✗ Brak pliku</span></li>`;
                            filesOk = false;
                        }
                    });
                    
                    html += '</ul>';
                    
                    if (!filesOk) {
                        html += '<div class="notice notice-error"><p>Wykryto problemy z plikami. Rozważ ponowną instalację wtyczki.</p></div>';
                    }
                    
                    html += '</div>';
                    
                    // APIs
                    html += '<div class="gca-section">';
                    html += '<h4>Status API</h4>';
                    html += '<ul>';
                    
                    if (response.data.claude_api.valid) {
                        html += `<li>Claude API: <span style="color: green;">✓ OK</span></li>`;
                    } else {
                        html += `<li>Claude API: <span style="color: red;">✗ Problem</span> - ${response.data.claude_api.message}</li>`;
                    }
                    
                    if (response.data.github_api.valid) {
                        html += `<li>GitHub API: <span style="color: green;">✓ OK</span></li>`;
                    } else {
                        html += `<li>GitHub API: <span style="color: orange;">⚠ Problem</span> - ${response.data.github_api.message}</li>`;
                    }
                    
                    html += '</ul>';
                    html += '</div>';
                    
                    // Rekomendacje
                    if (response.data.recommendations && response.data.recommendations.length > 0) {
                        html += '<div class="gca-section">';
                        html += '<h4>Rekomendacje naprawy</h4>';
                        html += '<ul class="gca-recommendations">';
                        
                        $.each(response.data.recommendations, function(i, recommendation) {
                            html += `<li>${recommendation}</li>`;
                        });
                        
                        html += '</ul>';
                        html += '</div>';
                    }
                    
                    // Wyświetl wyniki
                    resultsDiv.html(html);
                } else {
                    resultsDiv.html('<div class="notice notice-error"><p>Wystąpił błąd podczas przeprowadzania diagnostyki: ' + response.data.message + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                resultsDiv.html('<div class="notice notice-error"><p>Błąd komunikacji z serwerem: ' + error + '</p></div>');
            }
        });
    });
    
    // Test połączenia z Claude API
    $('#test-claude-connection').on('click', function() {
        const button = $(this);
        button.prop('disabled', true).text('Testowanie...');
        
        $.ajax({
            url: gca_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'test_claude_connection',
                nonce: gca_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Test połączenia z Claude API zakończony sukcesem: ' + response.data.message);
                } else {
                    alert('Test połączenia z Claude API zakończony niepowodzeniem: ' + response.data.message);
                }
            },
            error: function() {
                alert('Błąd podczas testowania połączenia z Claude API.');
            },
            complete: function() {
                button.prop('disabled', false).html('<i class="dashicons dashicons-update"></i> Testuj połączenie z Claude API');
                // Odśwież stronę, aby zaktualizować status
                location.reload();
            }
        });
    });
    
    // Test połączenia z GitHub API
    $('#test-github-connection').on('click', function() {
        const button = $(this);
        button.prop('disabled', true).text('Testowanie...');
        
        $.ajax({
            url: gca_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'gca_verify_repository',
                nonce: gca_ajax.nonce,
                repo_url: 'https://github.com/WordPress/WordPress'
            },
            success: function(response) {
                if (response.success) {
                    alert('Test połączenia z GitHub API zakończony sukcesem.');
                } else {
                    alert('Test połączenia z GitHub API zakończony niepowodzeniem: ' + response.data.message);
                }
            },
            error: function() {
                alert('Błąd podczas testowania połączenia z GitHub API.');
            },
            complete: function() {
                button.prop('disabled', false).html('<i class="dashicons dashicons-update"></i> Testuj połączenie z GitHub API');
                // Odśwież stronę, aby zaktualizować status
                location.reload();
            }
        });
    });
    
    // Naprawa bazy danych
    $('#repair-database').on('click', function() {
        if (confirm('Czy na pewno chcesz naprawić tabele bazy danych? Ta operacja spróbuje odtworzyć brakujące tabele.')) {
            const button = $(this);
            button.prop('disabled', true).text('Naprawianie...');
            
            // Deaktywuj i aktywuj wtyczkę, aby odtworzyć tabele
            $.ajax({
                url: gca_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'gca_repair_database',
                    nonce: gca_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Tabele bazy danych zostały naprawione. Strona zostanie odświeżona.');
                        location.reload();
                    } else {
                        alert('Błąd podczas naprawiania tabel: ' + response.data.message);
                        button.prop('disabled', false).html('<i class="dashicons dashicons-database-view"></i> Napraw tabele bazy danych');
                    }
                },
                error: function() {
                    alert('Błąd podczas komunikacji z serwerem.');
                    button.prop('disabled', false).html('<i class="dashicons dashicons-database-view"></i> Napraw tabele bazy danych');
                }
            });
        }
    });
});
</script>

<style>
.gca-diagnostics-container {
    margin-top: 20px;
}

.gca-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
    margin-bottom: 20px;
}

.gca-card-header {
    border-bottom: 1px solid #ccd0d4;
    padding: 12px 15px;
}

.gca-card-header h2 {
    margin: 0;
    font-size: 14px;
    line-height: 1.4;
    display: flex;
    align-items: center;
    gap: 8px;
}

.gca-card-body {
    padding: 15px;
}

.gca-spinner {
    width: 40px;
    height: 40px;
    margin: 20px auto;
    border: 4px solid rgba(0, 0, 0, 0.1);
    border-radius: 50%;
    border-top: 4px solid #5C6AC4;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.gca-api-status {
    margin-bottom: 20px;
}

.gca-account-info {
    background: #f0f6fc;
    padding: 12px;
    border-radius: 4px;
    margin-top: 10px;
}

.gca-info-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e2e4e7;
}

.gca-info-item:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.gca-section {
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.gca-section h4 {
    margin-top: 0;
    margin-bottom: 10px;
    font-size: 16px;
}

.gca-recommendations li {
    margin-bottom: 8px;
    padding-left: 20px;
    position: relative;
}

.gca-recommendations li:before {
    content: "⚠";
    position: absolute;
    left: 0;
    color: #e67e22;
}
</style>                         