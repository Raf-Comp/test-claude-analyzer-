/**
 * Moduł odpowiedzialny za wybór modelu Claude AI
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Pobierz listę dostępnych modeli
        const modelsSelect = $('#claude_model');
        
        if (modelsSelect.length === 0) return;
        
        // Funkcja do renderowania informacji o modelu
        function renderModelInfo(model) {
            const container = $('#model-info');
            if (!container.length) return;
            
            let modelInfo = '';
            
            switch (model) {
                case 'claude-3-7-sonnet-20250219':
                    modelInfo = `
                        <div class="gca-model-info">
                            <h4>Claude 3.7 Sonnet</h4>
                            <p>Najnowszy model Claude, oferujący zrównoważone podejście do analizy kodu.</p>
                            <ul>
                                <li><strong>Mocne strony:</strong> Rozumienie kontekstu, analiza kodu, znajdowanie błędów</li>
                                <li><strong>Zalecany dla:</strong> Większości przypadków użycia, analizy średniej wielkości repozytoriów</li>
                            </ul>
                        </div>
                    `;
                    break;
                    
                case 'claude-3-5-sonnet-20240620':
                    modelInfo = `
                        <div class="gca-model-info">
                            <h4>Claude 3.5 Sonnet</h4>
                            <p>Zrównoważony model, dobry do większości zadań analizy kodu.</p>
                            <ul>
                                <li><strong>Mocne strony:</strong> Dobra wydajność, zrównoważone odpowiedzi</li>
                                <li><strong>Zalecany dla:</strong> Standardowej analizy kodu i zadań programistycznych</li>
                            </ul>
                        </div>
                    `;
                    break;
                    
                case 'claude-3-opus-20240229':
                    modelInfo = `
                        <div class="gca-model-info">
                            <h4>Claude 3 Opus</h4>
                            <p>Najbardziej zaawansowany model o największych możliwościach analizy kodu.</p>
                            <ul>
                                <li><strong>Mocne strony:</strong> Głęboka analiza, rozpoznawanie złożonych wzorców</li>
                                <li><strong>Zalecany dla:</strong> Złożonych projektów, dogłębnej analizy architektonicznej</li>
                                <li><strong>Uwaga:</strong> Wolniejszy i droższy w użyciu</li>
                            </ul>
                        </div>
                    `;
                    break;
                    
                case 'claude-3-haiku-20240307':
                    modelInfo = `
                        <div class="gca-model-info">
                            <h4>Claude 3 Haiku</h4>
                            <p>Najszybszy model, idealny do szybkiej analizy.</p>
                            <ul>
                                <li><strong>Mocne strony:</strong> Szybkość, efektywność</li>
                                <li><strong>Zalecany dla:</strong> Małych repozytoriów, szybkich odpowiedzi</li>
                                <li><strong>Uwaga:</strong> Może pominąć niektóre szczegóły w złożonych kodach</li>
                            </ul>
                        </div>
                    `;
                    break;
                    
                default:
                    modelInfo = `
                        <div class="gca-model-info">
                            <p>Wybierz model Claude AI, aby zobaczyć więcej informacji.</p>
                        </div>
                    `;
            }
            
            container.html(modelInfo);
        }
        
        // Dodaj kontener na informacje o modelu, jeśli nie istnieje
        if ($('#model-info').length === 0) {
            modelsSelect.after('<div id="model-info" class="gca-model-info-container"></div>');
        }
        
        // Renderuj informacje o aktualnie wybranym modelu
        renderModelInfo(modelsSelect.val());
        
        // Obsługa zmiany modelu
        modelsSelect.on('change', function() {
            const selectedModel = $(this).val();
            renderModelInfo(selectedModel);
        });
    });
    
})(jQuery);