(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Test połączenia z API Claude
        $('#test-claude-connection').on('click', function() {
            const button = $(this);
            const resultContainer = $('#connection-test-result');
            const resultContent = resultContainer.find('.gca-result-content');
            
            // Zmień tekst przycisku i wyłącz go
            button.text('Testowanie...').prop('disabled', true);
            
            // Wyczyść poprzednie wyniki
            resultContainer.removeClass('success error').hide();
            
            // Wyślij zapytanie AJAX
            $.ajax({
                url: gca_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'test_claude_connection',
                    nonce: gca_ajax.nonce
                },
                success: function(response) {
                    console.log('Test response:', response);
                    if (response.success) {
                        resultContainer.addClass('success');
                        
                        let html = '<p><strong>Sukces:</strong> ' + response.data.message + '</p>';
                        
                        // Dodaj informacje o koncie, jeśli są dostępne
                        if (response.data.account_info) {
                            const account = response.data.account_info;
                            html += '<div class="gca-account-info">';
                            
                            if (account.status) {
                                html += '<p><strong>Status konta:</strong> ' + account.status + '</p>';
                            }
                            
                            if (account.models && account.models.length > 0) {
                                html += '<div class="gca-info-item">' +
                                    '<span class="gca-info-label">Dostępne modele:</span>' +
                                    '<span class="gca-info-value">' + account.models.join(', ') + '</span>' +
                                    '</div>';
                            }
                            
                            if (account.current_model) {
                                const modelStatus = account.model_available ? 
                                    '<span style="color: green;">✓ dostępny</span>' : 
                                    '<span style="color: red;">✗ niedostępny</span>';
                                
                                html += '<div class="gca-info-item">' +
                                    '<span class="gca-info-label">Wybrany model:</span>' +
                                    '<span class="gca-info-value">' + account.current_model + ' ' + modelStatus + '</span>' +
                                    '</div>';
                            }
                            
                            if (account.max_tokens !== undefined) {
                                html += '<div class="gca-info-item">' +
                                    '<span class="gca-info-label">Maksymalna liczba tokenów:</span>' +
                                    '<span class="gca-info-value">' + account.max_tokens + '</span>' +
                                    '</div>';
                            }
                            
                            html += '</div>';
                            
                            // Pokaż kartę informacji o koncie
                            $('#account-info-card').show();
                        }
                        
                        resultContent.html(html);
                    } else {
                        resultContainer.addClass('error');
                        resultContent.html('<p><strong>Błąd:</strong> ' + response.data.message + '</p>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Błąd AJAX:', status, error);
                    console.log('Pełna odpowiedź:', xhr.responseText);
                    
                    let errorMessage = 'Wystąpił problem z połączeniem. Spróbuj ponownie.';
                    
                    // Sprawdź, czy mamy bardziej szczegółową informację o błędzie
                    if (xhr.responseText) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.data && response.data.message) {
                                errorMessage = response.data.message;
                            }
                        } catch (e) {
                            // Jeśli nie jest to poprawny JSON, użyj tekstu odpowiedzi
                            if (xhr.responseText.length < 100) {
                                errorMessage += ' Szczegóły: ' + xhr.responseText;
                            }
                        }
                    }
                    
                    resultContainer.addClass('error');
                    resultContent.html('<p><strong>Błąd:</strong> ' + errorMessage + '</p><p>Więcej szczegółów znajdziesz w konsoli przeglądarki (naciśnij F12).</p>');
                },
                complete: function() {
                    // Przywróć początkowy stan przycisku
                    button.text('Testuj połączenie').prop('disabled', false);
                    // Pokaż kontener wyników
                    resultContainer.show();
                },
                timeout: 30000 // Zwiększ timeout do 30 sekund
            });
        });
        
        // Odświeżanie listy modeli po zmianie klucza API
        $('#claude_api_key').on('change', function() {
            const apiKey = $(this).val();
            if (apiKey.length > 20) { // Podstawowe sprawdzenie, czy to wygląda jak klucz API
                $('#refresh-models').show();
            }
        });

        // Przycisk do odświeżania listy modeli
        $('#refresh-models').on('click', function(e) {
            e.preventDefault();
            const button = $(this);
            const apiKey = $('#claude_api_key').val();
            
            if (!apiKey) {
                alert('Wprowadź klucz API Claude, aby pobrać listę dostępnych modeli.');
                return;
            }
            
            button.text('Pobieranie modeli...').prop('disabled', true);
            
            $.ajax({
                url: gca_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'refresh_claude_models',
                    nonce: gca_ajax.nonce,
                    api_key: apiKey
                },
                success: function(response) {
                    if (response.success) {
                        // Odśwież listę modelów
                        const modelSelect = $('#claude_model');
                        modelSelect.empty();
                        
                        if (response.data.models && response.data.models.length > 0) {
                            $.each(response.data.models, function(i, model) {
                                let modelDisplayName = model.replace(/-/g, ' ');
                                modelDisplayName = modelDisplayName.charAt(0).toUpperCase() + modelDisplayName.slice(1);
                                
                                let modelDescription = '';
                                if (model.includes('opus')) {
                                    modelDescription = ' (najdokładniejszy)';
                                } else if (model.includes('haiku')) {
                                    modelDescription = ' (najszybszy)';
                                } else if (model.includes('3-7')) {
                                    modelDescription = ' (najnowszy, zalecany)';
                                } else if (model.includes('3-5')) {
                                    modelDescription = ' (zrównoważony)';
                                }
                                
                                modelSelect.append(
                                    $('<option></option>')
                                        .attr('value', model)
                                        .text(modelDisplayName + modelDescription)
                                );
                            });
                            
                            alert('Pomyślnie pobrano ' + response.data.models.length + ' dostępnych modeli Claude.');
                        } else {
                            alert('Nie znaleziono dostępnych modeli Claude.');
                        }
                    } else {
                        alert('Błąd: ' + (response.data.message || 'Nie udało się pobrać modeli Claude.'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Błąd AJAX:', status, error);
                    console.log('Pełna odpowiedź:', xhr.responseText);
                    
                    let errorMessage = 'Błąd podczas komunikacji z serwerem.';
                    
                    // Sprawdź, czy mamy bardziej szczegółową informację o błędzie
                    if (xhr.responseText) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.data && response.data.message) {
                                errorMessage = response.data.message;
                            }
                        } catch (e) {
                            // Jeśli nie jest to poprawny JSON, użyj tekstu odpowiedzi
                            if (xhr.responseText.length < 100) {
                                errorMessage += ' Szczegóły: ' + xhr.responseText;
                            }
                        }
                    }
                    
                    alert('Błąd: ' + errorMessage);
                },
                complete: function() {
                    button.text('Odśwież modele').prop('disabled', false);
                },
                timeout: 30000 // Zwiększ timeout do 30 sekund
            });
        });
        
        // Weryfikacja tokenu GitHub
        $('#verify-github-token').on('click', function() {
            const button = $(this);
            const resultContainer = $('#github-token-verification-result');
            const token = $('#github_token').val();
            
            if (!token) {
                resultContainer.html('<div style="color: #b91c1c;"><i class="dashicons dashicons-warning"></i> Wprowadź token GitHub, aby go zweryfikować</div>').show();
                return;
            }
            
            button.prop('disabled', true).text('Weryfikacja...');
            
            $.ajax({
                url: gca_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'verify_github_token',
                    nonce: gca_ajax.nonce,
                    token: token
                },
                success: function(response) {
                    if (response.success) {
                        let html = '<div style="color: #065f46;"><i class="dashicons dashicons-yes-alt"></i> ';
                        html += '<strong>Token jest poprawny</strong> - Limit: ' + response.data.limit + ' zapytań/godz, ';
                        html += 'Pozostało: ' + response.data.remaining + ' zapytań</div>';
                        resultContainer.html(html).show();
                    } else {
                        resultContainer.html('<div style="color: #b91c1c;"><i class="dashicons dashicons-warning"></i> ' + response.data.message + '</div>').show();
                    }
                },
                error: function() {
                    resultContainer.html('<div style="color: #b91c1c;"><i class="dashicons dashicons-warning"></i> Błąd podczas weryfikacji tokenu. Spróbuj ponownie.</div>').show();
                },
                complete: function() {
                    button.prop('disabled', false).html('<i class="dashicons dashicons-yes-alt"></i> Weryfikuj token');
                }
            });
        });
    });
})(jQuery);