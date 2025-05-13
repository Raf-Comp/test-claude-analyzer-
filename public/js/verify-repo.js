(function($) {
    'use strict';
    
    $(document).ready(function() {
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
            
            // Wyciągnij nazwę użytkownika i repozytorium z URL
            const match = repoUrl.match(/github\.com\/([^\/]+)\/([^\/\?\#]+)/i);
            const username = match[1];
            const repo = match[2];
            
            // Sprawdź dostępność repozytorium bezpośrednio
            $.ajax({
                url: `https://api.github.com/repos/${username}/${repo}`,
                type: 'GET',
                success: function(response) {
                    resultContainer.html(`
                        <div style="color: green; margin-top: 10px;">
                            <span class="dashicons dashicons-yes-alt"></span> Repozytorium dostępne: ${response.full_name}<br>
                            <small>Właściciel: ${response.owner.login}, Gwiazdki: ${response.stargazers_count}, Forki: ${response.forks_count}</small>
                        </div>
                    `).show();
                },
                error: function(xhr) {
                    if (xhr.status === 404) {
                        resultContainer.html(`
                            <div style="color: red; margin-top: 10px;">
                                <span class="dashicons dashicons-no"></span> Repozytorium nie istnieje lub jest prywatne.<br>
                                <small>Dla prywatnych repozytoriów wymagany jest token GitHub w ustawieniach wtyczki.</small>
                            </div>
                        `).show();
                    } else {
                        resultContainer.html(`
                            <div style="color: red; margin-top: 10px;">
                                <span class="dashicons dashicons-warning"></span> Błąd sprawdzania repozytorium: ${xhr.status} ${xhr.statusText}
                            </div>
                        `).show();
                    }
                }
            });
        });
    });
    
    // Pomocnicza funkcja animacji rotacji
    document.head.insertAdjacentHTML('beforeend', `
        <style>
            @keyframes rotation {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        </style>
    `);
})(jQuery);