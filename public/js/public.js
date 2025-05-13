(function($) {
    'use strict';
    
    $(document).ready(function() {
        const analyzeForm = $('#gca-analyze-form');
        const loader = $('#gca-loader');
        const results = $('#gca-results');
        
        console.log('GCA Debug: Inicjalizacja skryptu');
        
        // Obsługa błędów
        window.onerror = function(message, source, lineno, colno, error) {
            logJsError('Uncaught error: ' + message, { source, lineno, colno, stack: error ? error.stack : 'N/A' });
            return false;
        };
        
        // Funkcja pomocnicza do logowania błędów JavaScript
        function logJsError(message, details) {
            console.error('GCA Error:', message, details);
            
            // Wyślij błąd na serwer
            const errorData = new FormData();
            errorData.append('action', 'gca_log_js_error');
            errorData.append('nonce', gca_ajax.nonce);
            errorData.append('message', message);
            errorData.append('details', JSON.stringify(details));
            errorData.append('url', window.location.href);
            errorData.append('user_agent', navigator.userAgent);
            
            navigator.sendBeacon(gca_ajax.ajax_url, errorData);
        }
        
        // Obsługa przycisku anulowania analizy
        $('#cancel-analysis-btn').on('click', function() {
            if (confirm('Czy na pewno chcesz anulować bieżącą analizę?')) {
                const taskId = $(this).data('task-id');
                
                if (!taskId) {
                    console.error('Brak identyfikatora zadania do anulowania');
                    return;
                }
                
                // Wyłącz przycisk podczas anulowania
                $(this).prop('disabled', true).text('Anulowanie...');
                
                // Wyślij żądanie anulowania
                $.ajax({
                    url: gca_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'gca_cancel_analysis',
                        nonce: gca_ajax.nonce,
                        task_id: taskId
                    },
                    success: function(response) {
                        if (response.success) {
                            displayError('Analiza została anulowana przez użytkownika.');
                            loader.hide();
                        } else {
                            alert('Błąd podczas anulowania analizy: ' + response.data.message);
                            $('#cancel-analysis-btn').prop('disabled', false).text('Anuluj analizę');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Błąd AJAX:', status, error);
                        alert('Wystąpił błąd podczas anulowania analizy.');
                        $('#cancel-analysis-btn').prop('disabled', false).text('Anuluj analizę');
                    }
                });
            }
        });
        
        // Obsługa przycisku weryfikacji repozytorium
        $('#verify-repo-btn').on('click', function() {
            const repoUrl = $('#repo_url').val();
            const resultContainer = $('#repo-verification-result');
            
            if (!repoUrl) {
                resultContainer.html('<div style="color: red; margin-top: 10px;">Wprowadź URL repozytorium GitHub.</div>').show();
                return;
            }
            
            // Sprawdź czy URL ma poprawny format
            if (!repoUrl.match(/github\.com\/([^\/]+)\/([^\/\?\#]+)/i)) {
                resultContainer.html('<div style="color: red; margin-top: 10px;">Nieprawidłowy format URL repozytorium GitHub.</div>').show();
                return;
            }
            
            resultContainer.html('<div style="margin-top: 10px; display: flex; align-items: center;"><span class="dashicons dashicons-admin-site" style="animation: rotation 2s infinite linear; margin-right: 10px;"></span> Weryfikacja repozytorium...</div>').show();
            
            // Wyślij żądanie AJAX
            $.ajax({
                url: gca_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'gca_verify_repository',
                    nonce: gca_ajax.nonce,
                    repo_url: repoUrl
                },
                success: function(response) {
                    if (response.success) {
                        let html = `
                            <div style="color: green; margin-top: 10px;">
                                <span class="dashicons dashicons-yes-alt"></span> Repozytorium dostępne: ${response.data.repo_info.full_name}<br>
                                <small>Właściciel: ${response.data.repo_info.owner.login}, Gwiazdki: ${response.data.repo_info.stars}, Forki: ${response.data.repo_info.forks}</small>
                            </div>
                        `;
                        resultContainer.html(html).show();
                    } else {
                        resultContainer.html(`
                            <div style="color: red; margin-top: 10px;">
                                <span class="dashicons dashicons-no"></span> ${response.data.message}
                            </div>
                        `).show();
                    }
                },
                error: function(xhr, status, error) {
                    resultContainer.html(`
                        <div style="color: red; margin-top: 10px;">
                            <span class="dashicons dashicons-warning"></span> Błąd komunikacji z serwerem: ${error}
                        </div>
                    `).show();
                    logJsError('Verify repo error', { xhr: xhr.status, status, error });
                }
            });
        });
        
        // Obsługa dodatkowych rozszerzeń
        $('#other_extensions').on('change', function() {
            const otherExtensions = $(this).val().split(',');
            
            // Usuń istniejące dynamiczne checkboxy
            $('.gca-checkbox-group.dynamic').remove();
            
            // Dodaj nowe checkboxy dla dodatkowych rozszerzeń
            otherExtensions.forEach(function(ext) {
                ext = ext.trim().toLowerCase();
                if (ext && $('#filter_' + ext).length === 0) {
                    const checkboxHtml = `
                        <div class="gca-checkbox-group dynamic">
                            <input type="checkbox" id="filter_${ext}" name="file_filters[]" value="${ext}" checked>
                            <label for="filter_${ext}">${ext.toUpperCase()}</label>
                        </div>
                    `;
                    $('.gca-checkboxes').append(checkboxHtml);
                }
            });
        });
        
        // Obsługa przesłania formularza
        analyzeForm.on('submit', function(e) {
            e.preventDefault();
            
            try {
                // Pokaż loader i ukryj wyniki
                loader.show();
                results.hide();
                
                // Przygotuj komunikat postępu
                $('.gca-batch-progress').text('Inicjowanie analizy...');
                $('#gca-progress-bar').show().css('width', '5%');
                $('#current-file').text('Oczekiwanie...');
                $('#processed-count').text('0');
                $('#total-count').text('0');
                
                // Zbierz wszystkie dane z formularza
                const formData = new FormData(this);
                formData.append('action', 'start_github_analysis');
                formData.append('nonce', gca_ajax.nonce);
                
                // Dodaj wartość dla wymuszenia ponownej analizy
                formData.append('force_reanalysis', $('#force_reanalysis').is(':checked') ? '1' : '0');
                
                // Ogranicz długość prompt
                const promptText = formData.get('prompt');
                if (promptText && promptText.length > 1000) {
                    formData.set('prompt', promptText.substring(0, 1000) + "...");
                }
                
                console.log('GCA Debug: Wysyłanie analizy');
                
                // Rozpocznij proces asynchronicznej analizy
                $.ajax({
                    url: gca_ajax.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        console.log('GCA Debug: Response received', response);
                        if (response.success) {
                            // Rozpocznij sprawdzanie postępu
                            checkAnalysisProgress(response.data.task_id);
                        } else {
                            displayError(response.data.message || 'Wystąpił nieznany błąd');
                            loader.hide();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Błąd AJAX:', status, error);
                        displayError('Wystąpił błąd podczas inicjowania analizy: ' + error);
                        logJsError('Form submission error', { xhr: xhr.status, status, error });
                        loader.hide();
                    },
                    timeout: 60000 // Zwiększenie limitu czasu do 60 sekund
                });
            } catch (err) {
                displayError('Wystąpił błąd JavaScript: ' + err.message);
                logJsError('Form submission exception', { error: err.message, stack: err.stack });
                loader.hide();
            }
        });
        
        // Funkcja do sprawdzania postępu analizy
        function checkAnalysisProgress(taskId) {
            // Dodaj identyfikator zadania do przycisku anulowania
            $('#cancel-analysis-btn').data('task-id', taskId);
            
            $.ajax({
                url: gca_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'check_analysis_progress',
                    nonce: gca_ajax.nonce,
                    task_id: taskId
                },
                success: function(response) {
                    console.log('GCA Debug: Progress response', response);
                    if (response.success) {
                        // Aktualizuj wskaźnik postępu
                        const progress = response.data.progress;
                        const status = response.data.status;
                        const message = response.data.message;
                        
                        $('#gca-progress-bar').css('width', progress + '%');
                        $('.gca-batch-progress').text(message);
                        
                        // Aktualizuj informacje o przetwarzanym pliku
                        if (response.data.current_file) {
                            $('#current-file').text(response.data.current_file);
                        }
                        
                        // Aktualizuj licznik przetworzonych plików
                        $('#processed-count').text(response.data.processed_files);
                        $('#total-count').text(response.data.total_files || '0');
                        
                        if (response.data.completed) {
                            if (response.data.error) {
                                // Wystąpił błąd podczas analizy
                                const errorMessage = response.data.error.message || 'Wystąpił nieznany błąd podczas analizy.';
                                displayError(errorMessage, response.data.error);
                            } else {
                                // Proces zakończony, wyświetl wyniki
                                displayResults(response.data.results);
                            }
                            loader.hide();
                        } else {
                            // Nadal w trakcie, sprawdź ponownie za 2 sekundy
                            setTimeout(function() {
                                checkAnalysisProgress(taskId);
                            }, 2000);
                        }
                    } else {
                        displayError(response.data.message || 'Wystąpił błąd podczas sprawdzania postępu');
                        loader.hide();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Błąd AJAX:', status, error);
                    
                    // Jeśli to tymczasowy błąd, spróbuj ponownie
                    if (status === 'timeout' || status === 'parsererror') {
                        setTimeout(function() {
                            checkAnalysisProgress(taskId);
                        }, 5000); // Dłuższe opóźnienie przy błędach
                    } else {
                        displayError('Wystąpił błąd podczas sprawdzania postępu: ' + error);
                        logJsError('Analysis progress error', { xhr: xhr.status, status, error, taskId });
                        loader.hide();
                    }
                },
                timeout: 30000 // Zwiększ timeout do 30 sekund
            });
        }
        
        // Funkcja wyświetlająca wyniki analizy
        function displayResults(data) {
            if (!data) {
                displayError('Otrzymano puste dane wynikowe.');
                return;
            }
            
            // Przygotuj metadane analizy
            const metadata = `
                <div class="gca-analysis-metadata">
                    <div class="gca-metadata-item">
                        <i class="dashicons dashicons-admin-site"></i>
                        <span>${escapeHtml(data.repository_url)}</span>
                    </div>
                    <div class="gca-metadata-item">
                        <i class="dashicons dashicons-media-code"></i>
                        <span>Przeanalizowano ${data.total_files} plików</span>
                    </div>
                    ${data.filtered_extensions && data.filtered_extensions.length > 0 ? `
                        <div class="gca-metadata-item">
                            <i class="dashicons dashicons-filter"></i>
                            <span>Filtrowane rozszerzenia: ${data.filtered_extensions.join(', ')}</span>
                        </div>
                    ` : ''}
                    <div class="gca-metadata-item">
                        <i class="dashicons dashicons-calendar-alt"></i>
                        <span>Data analizy: ${new Date().toLocaleString()}</span>
                    </div>
                    ${data.processed_files_count ? `
                        <div class="gca-metadata-item">
                            <i class="dashicons dashicons-performance"></i>
                            <span>Pliki w historii: ${data.processed_files_count}</span>
                        </div>
                    ` : ''}
                </div>
            `;
            
            // Przygotuj zakładki analizy
            let tabs = '<div class="gca-analysis-tabs">';
            let tabContent = '<div class="gca-tab-content">';
            
            // Utwórz zakładkę z podsumowaniem wszystkich odpowiedzi
            tabs += '<button class="gca-tablink active" data-tab="summary">Podsumowanie</button>';
            
            // Sprawdź, czy mamy dane analiz
            if (data.analyses && data.analyses.length > 0) {
                // Utwórz zakładki dla każdej partii
                data.analyses.forEach(function(batch) {
                    // Dodaj klasę dla partii z błędem
                    const errorClass = batch.error ? 'gca-tab-error' : '';
                    // Dodaj ikonę dla partii z wcześniej przetworzonymi plikami
                    const historyIcon = batch.has_processed_files ? '<i class="dashicons dashicons-backup" title="Zawiera wcześniej przetworzone pliki"></i> ' : '';
                    
                    tabs += `
                        <button class="gca-tablink ${errorClass}" data-tab="batch-${batch.batch_number}">
                            ${historyIcon}Partia ${batch.batch_number} <span class="gca-file-count">(${batch.files.length} plików)</span>
                        </button>
                    `;
                });
            }

            // Dodaj zakładkę dla historii przetworzonych plików, jeśli istnieje
            if (data.processed_files_history && data.processed_files_history.length > 0) {
                tabs += '<button class="gca-tablink" data-tab="history"><i class="dashicons dashicons-clock"></i> Historia</button>';
            }
            
            // Dodaj zakładkę dla logu
            if (data.log) {
                tabs += '<button class="gca-tablink" data-tab="log"><i class="dashicons dashicons-list-view"></i> Log</button>';
            }
            
            tabs += '</div>';
            
            // Utwórz zawartość zakładki z podsumowaniem
            tabContent += '<div id="summary" class="gca-tab-pane active">';
            tabContent += '<h3>Podsumowanie analizy</h3>';
            
            if (data.analyses && data.analyses.length > 0) {
                // Połącz wszystkie odpowiedzi w jedno podsumowanie
                let allResponses = '';
                let hasErrors = false;
                
                data.analyses.forEach(function(batch) {
                    if (batch.error) {
                        hasErrors = true;
                    } else {
                        allResponses += batch.response + "\n\n";
                    }
                });
                
                // Sprawdź, czy były jakieś błędy
                if (hasErrors) {
                    tabContent += '<div class="gca-notice gca-notice-warning">';
                    tabContent += '<p><strong>Uwaga:</strong> Niektóre partie plików nie zostały przeanalizowane z powodu błędów. Sprawdź zakładki oznaczone na czerwono, aby uzyskać więcej informacji.</p>';
                    tabContent += '</div>';
                }
                
                tabContent += `<div class="gca-claude-response">${nl2br(escapeHtml(allResponses))}</div>`;
            } else {
                tabContent += '<div class="gca-notice gca-notice-warning">';
                tabContent += '<p><strong>Uwaga:</strong> Nie znaleziono wyników analizy.</p>';
                tabContent += '</div>';
            }
            
            tabContent += '</div>';
            
            // Utwórz zawartość zakładek dla każdej partii
            if (data.analyses && data.analyses.length > 0) {
                data.analyses.forEach(function(batch) {
                    tabContent += `<div id="batch-${batch.batch_number}" class="gca-tab-pane">`;
                    tabContent += `<h3>Partia ${batch.batch_number}</h3>`;
                    
                    // Dodaj komunikat błędu, jeśli wystąpił
                    if (batch.error) {
                        tabContent += `
                            <div class="gca-notice gca-notice-error">
                                <p><strong>Błąd:</strong> ${escapeHtml(batch.response)}</p>
                            </div>
                        `;
                    }
                    
                    // Dodaj informację o wcześniej przetworzonych plikach
                    if (batch.has_processed_files) {
                        tabContent += `
                            <div class="gca-notice gca-notice-info">
                                <p><strong>Uwaga:</strong> Ta partia zawiera pliki, które były wcześniej analizowane. Zostały one uwzględnione w analizie.</p>
                            </div>
                        `;
                    }
                    
                    // Dodaj listę plików w tej partii
                    tabContent += `
                        <div class="gca-file-list-container">
                            <h4>Pliki w tej partii:</h4>
                            <div class="gca-file-list">
                    `;
                    
                    batch.files.forEach(function(file) {
                        const fileExtension = file.split('.').pop().toLowerCase();
                        tabContent += `
                            <div class="gca-file-item">
                                <span class="gca-file-icon gca-file-${fileExtension}"><i class="dashicons dashicons-media-code"></i></span>
                                <span class="gca-file-name">${escapeHtml(file)}</span>
                            </div>
                        `;
                    });
                    
                    tabContent += '</div></div>';
                    
                    // Dodaj odpowiedź Claude (tylko jeśli nie ma błędu)
                    if (!batch.error) {
                        tabContent += `<div class="gca-claude-response"><pre>${escapeHtml(batch.response)}</pre></div>`;
                    }
                    
                    tabContent += '</div>';
                });
            }
            
            // Dodaj zakładkę z historią przetworzonych plików
            if (data.processed_files_history && data.processed_files_history.length > 0) {
                tabContent += '<div id="history" class="gca-tab-pane">';
                tabContent += '<h3>Historia przetworzonych plików</h3>';
                
                tabContent += `
                    <div class="gca-history-container">
                        <p>Poniżej znajduje się lista plików, które zostały przeanalizowane dla tego repozytorium:</p>
                        <table class="gca-history-table" style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background-color: #f3f4f6;">
                                    <th style="padding: 8px; text-align: left; border: 1px solid #e5e7eb;">Plik</th>
                                    <th style="padding: 8px; text-align: left; border: 1px solid #e5e7eb;">Data analizy</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                data.processed_files_history.forEach(function(item) {
                    tabContent += `
                        <tr>
                           <td style="padding: 8px; border: 1px solid #e5e7eb;">${escapeHtml(item.file_path)}</td>
                            <td style="padding: 8px; border: 1px solid #e5e7eb;">${new Date(item.processed_at).toLocaleString()}</td>
                        </tr>
                    `;
                });
                
                tabContent += `
                            </tbody>
                        </table>
                        <p class="gca-history-info" style="margin-top: 15px;">Pliki w historii są pomijane podczas kolejnych analiz, chyba że ich zawartość uległa zmianie.</p>
                        <button class="gca-btn gca-btn-secondary gca-clear-history-btn" style="margin-top: 10px;">
                            <i class="dashicons dashicons-trash"></i> Wyczyść historię dla tego repozytorium
                        </button>
                    </div>
                `;
                
                tabContent += '</div>';
            }
            
            // Dodaj zakładkę z logiem
            if (data.log) {
                tabContent += '<div id="log" class="gca-tab-pane">';
                tabContent += '<h3>Log repozytorium</h3>';
                tabContent += `<div class="gca-claude-response"><pre>${escapeHtml(data.log)}</pre></div>`;
                tabContent += '</div>';
            }
            
            tabContent += '</div>';
            
            // Połącz wszystko i pokaż wyniki
            results.html(`
                <div class="gca-card">
                    <div class="gca-card-header">
                        <h2><i class="dashicons dashicons-chart-bar"></i> Wyniki analizy</h2>
                        <div class="gca-header-actions">
                            <button class="gca-btn gca-btn-secondary gca-new-analysis-btn">
                                <i class="dashicons dashicons-update"></i>
                                Nowa analiza
                            </button>
                            <button class="gca-btn gca-btn-primary gca-reanalyze-btn">
                                <i class="dashicons dashicons-controls-repeat"></i>
                                Przeanalizuj ponownie
                            </button>
                        </div>
                    </div>
                    <div class="gca-card-body">
                        ${metadata}
                        ${tabs}
                        ${tabContent}
                    </div>
                </div>
            `).show();
            
            // Obsługa przełączania zakładek
            $('.gca-tablink').on('click', function() {
                const tabId = $(this).data('tab');
                
                $('.gca-tablink').removeClass('active');
                $(this).addClass('active');
                
                $('.gca-tab-pane').removeClass('active').hide();
                $('#' + tabId).addClass('active').show();
            });
            
            // Obsługa przycisku nowej analizy
            $('.gca-new-analysis-btn').on('click', function() {
                results.hide();
                analyzeForm.trigger('reset');
                $('html, body').animate({
                    scrollTop: analyzeForm.offset().top - 50
                }, 500);
            });
            
            // Obsługa przycisku ponownej analizy
            $('.gca-reanalyze-btn').on('click', function() {
                // Zaznacz pole wymuszenia ponownej analizy
                $('#force_reanalysis').prop('checked', true);
                
                // Prześlij formularz
                analyzeForm.submit();
            });
            
            // Obsługa przycisku czyszczenia historii
            $('.gca-clear-history-btn').on('click', function() {
                if (confirm('Czy na pewno chcesz wyczyścić historię przetworzonych plików dla tego repozytorium? Ta operacja jest nieodwracalna.')) {
                    const repoUrl = $('#repo_url').val();
                    
                    $.ajax({
                        url: gca_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'clear_processed_files_history',
                            nonce: gca_ajax.nonce,
                            repository_url: repoUrl
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Historia przetworzonych plików została wyczyszczona.');
                                window.location.reload();
                            } else {
                                alert('Błąd: ' + (response.data.message || 'Nie udało się wyczyścić historii.'));
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Błąd AJAX:', status, error);
                            alert('Wystąpił błąd podczas komunikacji z serwerem. Sprawdź konsolę przeglądarki.');
                            logJsError('Clear history error', { xhr: xhr.status, status, error });
                        }
                    });
                }
            });
            
            // Przewiń do wyników
            $('html, body').animate({
                scrollTop: results.offset().top - 30
            }, 500);
        }
        
        // Funkcja wyświetlająca błąd
        function displayError(message, errorDetails) {
            console.error('Error:', message, errorDetails);
            
            let errorHtml = `
                <div class="gca-notice gca-notice-error">
                    <p><strong>Błąd:</strong> ${escapeHtml(message)}</p>
            `;
            
            if (errorDetails && errorDetails.trace) {
                errorHtml += `
                    <div class="gca-error-details">
                        <p><strong>Szczegóły błędu:</strong></p>
                        <pre>${escapeHtml(errorDetails.trace)}</pre>
                    </div>
                `;
            }
            
            errorHtml += `
                    <p><button class="gca-btn gca-btn-secondary gca-new-analysis-btn">
                        <i class="dashicons dashicons-update"></i>
                        Spróbuj ponownie
                    </button>
                    <button class="gca-btn gca-btn-secondary gca-diagnostic-btn" style="margin-left: 10px;">
                        <i class="dashicons dashicons-sos"></i>
                        Uruchom diagnostykę
                    </button></p>
                </div>
            `;
            
            results.html(errorHtml).show();
            
            // Obsługa przycisku ponownej próby
            $('.gca-new-analysis-btn').on('click', function() {
                results.hide();
                $('html, body').animate({
                    scrollTop: analyzeForm.offset().top - 50
                }, 500);
            });
            
            // Obsługa przycisku diagnostyki
            $('.gca-diagnostic-btn').on('click', function() {
                runDiagnostics();
            });
            
            // Przewiń do komunikatu błędu
            $('html, body').animate({
                scrollTop: results.offset().top - 30
            }, 500);
        }
        
        // Funkcja do uruchamiania diagnostyki
        function runDiagnostics() {
            const diagnosticResults = $('<div class="gca-diagnostic-results"></div>');
            results.append(diagnosticResults);
            
            diagnosticResults.html('<p><strong>Uruchamianie diagnostyki...</strong></p>');
            
            // Lista testów
            const tests = [
                { name: 'Połączenie AJAX', action: 'gca_diagnostic_ping' },
                { name: 'Sprawdzenie uprawnień plików', action: 'gca_diagnostic_file_permissions' },
                { name: 'Sprawdzenie limitów PHP', action: 'gca_diagnostic_php_limits' },
               { name: 'Sprawdzenie konfiguracji bazy danych', action: 'gca_diagnostic_db_check' }
           ];
           
           let testsHtml = '<h3>Testy diagnostyczne:</h3><ul>';
           
           // Uruchom wszystkie testy
           runTests(tests, 0, testsHtml, diagnosticResults);
       }
       
       // Funkcja wykonująca testy diagnostyczne
       function runTests(tests, index, testsHtml, resultsContainer) {
           if (index >= tests.length) {
               // Zakończono wszystkie testy
               testsHtml += `
                   <li>Przeglądarka: ${navigator.userAgent}</li>
                   <li>Czas klienta: ${new Date().toLocaleString()}</li>
                   <li>URL: ${window.location.href}</li>
               `;
               testsHtml += '</ul>';
               
               resultsContainer.html(testsHtml);
               
               // Dodaj przyciski
               resultsContainer.append(`
                   <p><button class="gca-btn gca-btn-secondary gca-copy-diagnostic-btn" style="margin-top: 10px;">
                       <i class="dashicons dashicons-clipboard"></i>
                       Kopiuj wyniki do schowka
                   </button></p>
               `);
               
               // Obsługa kopiowania
               $('.gca-copy-diagnostic-btn').on('click', function() {
                   const text = $('.gca-diagnostic-results').text();
                   navigator.clipboard.writeText(text)
                       .then(() => {
                           alert('Wyniki diagnostyki zostały skopiowane do schowka.');
                       })
                       .catch(err => {
                           alert('Nie udało się skopiować wyników: ' + err.message);
                       });
               });
               
               return;
           }
           
           // Przygotuj aktualny test
           const test = tests[index];
           testsHtml += `<li>${test.name}: <span class="status">Trwa...</span></li>`;
           resultsContainer.html(testsHtml + '</ul>');
           
           // Wykonaj test
           $.ajax({
               url: gca_ajax.ajax_url,
               type: 'POST',
               data: {
                   action: test.action,
                   nonce: gca_ajax.nonce
               },
               success: function(response) {
                   if (response.success) {
                       testsHtml = testsHtml.replace(`${test.name}: <span class="status">Trwa...</span>`, 
                                     `${test.name}: <span class="status" style="color: green;">OK</span> - ${response.data.message}`);
                   } else {
                       testsHtml = testsHtml.replace(`${test.name}: <span class="status">Trwa...</span>`, 
                                     `${test.name}: <span class="status" style="color: red;">Błąd</span> - ${response.data.message}`);
                   }
                   
                   // Przejdź do następnego testu
                   runTests(tests, index + 1, testsHtml, resultsContainer);
               },
               error: function(xhr, status, error) {
                   testsHtml = testsHtml.replace(`${test.name}: <span class="status">Trwa...</span>`, 
                                 `${test.name}: <span class="status" style="color: red;">Błąd AJAX</span> - ${error}`);
                   
                   // Przejdź do następnego testu
                   runTests(tests, index + 1, testsHtml, resultsContainer);
                   
                   logJsError('Diagnostic test error', { test: test.name, xhr: xhr.status, status, error });
               }
           });
       }
       
       // Helper do escapowania HTML
       function escapeHtml(text) {
           if (text === undefined || text === null) {
               return '';
           }
           const div = document.createElement('div');
           div.textContent = text;
           return div.innerHTML;
       }
       
       // Helper do zamiany nowych linii na <br>
       function nl2br(text) {
           if (text === undefined || text === null) {
               return '';
           }
           return text.replace(/\n/g, '<br>');
       }
       
       // Dodaj przycisk diagnostyki na stronie
       $('.gca-form-submit').append(`
           <button type="button" id="run-diagnostics-btn" class="gca-btn gca-btn-secondary" style="margin-left: 10px;">
               <i class="dashicons dashicons-sos"></i>
               Diagnostyka
           </button>
       `);
       
       // Obsługa przycisku diagnostyki
       $('#run-diagnostics-btn').on('click', function() {
           runDiagnostics();
       });
   });
})(jQuery);