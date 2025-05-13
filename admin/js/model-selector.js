/**
 * Skrypt obsługujący zaawansowany selektor modeli Claude
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Stwórz kontener na informacje o modelu, jeśli nie istnieje
        if ($('#claude-model-details').length === 0) {
            $('#claude_model').after('<div id="claude-model-details" class="gca-model-details" style="margin-top: 15px;"></div>');
        }
        
        // Funkcja pobierająca szczegóły modelu z API
        function fetchModelDetails(modelName) {
            $.ajax({
                url: gca_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'gca_get_model_details',
                    nonce: gca_ajax.nonce,
                    model: modelName
                },
                success: function(response) {
                    if (response.success) {
                        displayModelDetails(response.data);
                    } else {
                        $('#claude-model-details').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                    }
                },
                error: function() {
                    $('#claude-model-details').html('<div class="notice notice-error"><p>Błąd podczas pobierania szczegółów modelu.</p></div>');
                }
            });
        }
        
        // Funkcja wyświetlająca szczegóły modelu
        function displayModelDetails(modelInfo) {
            let speedBadge = '';
            let sizeBadge = '';
            
            switch(modelInfo.speed) {
                case 'Bardzo szybki':
                    speedBadge = '<span class="gca-badge gca-badge-green">Bardzo szybki</span>';
                    break;
                case 'Szybki':
                    speedBadge = '<span class="gca-badge gca-badge-green">Szybki</span>';
                    break;
                case 'Średni':
                    speedBadge = '<span class="gca-badge gca-badge-yellow">Średni</span>';
                    break;
                case 'Wolny':
                    speedBadge = '<span class="gca-badge gca-badge-red">Wolny</span>';
                    break;
                default:
                    speedBadge = '<span class="gca-badge gca-badge-gray">' + modelInfo.speed + '</span>';
            }
            
            switch(modelInfo.size) {
                case 'Mały':
                    sizeBadge = '<span class="gca-badge gca-badge-green">Mały</span>';
                    break;
                case 'Średni':
                    sizeBadge = '<span class="gca-badge gca-badge-yellow">Średni</span>';
                    break;
                case 'Duży':
                    sizeBadge = '<span class="gca-badge gca-badge-red">Duży</span>';
                    break;
                default:
                    sizeBadge = '<span class="gca-badge gca-badge-gray">' + modelInfo.size + '</span>';
            }
            
            const premiumBadge = modelInfo.is_premium ? 
                '<span class="gca-badge gca-badge-purple">Premium</span>' : '';
            
            let html = `
                <div class="gca-model-info-card">
                    <div class="gca-model-info-header">
                        <h3>${modelInfo.name}</h3>
                        <div class="gca-model-badges">
                            ${speedBadge}
                            ${sizeBadge}
                            ${premiumBadge}
                        </div>
                    </div>
                    <div class="gca-model-info-body">
                        <p>${modelInfo.description}</p>
                        <div class="gca-model-info-points">
                            <div class="gca-model-info-item">
                                <strong>Mocne strony:</strong> ${modelInfo.strengths}
                            </div>
                            <div class="gca-model-info-item">
                                <strong>Zalecany dla:</strong> ${modelInfo.recommended_for}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('#claude-model-details').html(html);
        }
        
        // Obsługa zmiany modelu
        $('#claude_model').on('change', function() {
            const selectedModel = $(this).val();
            fetchModelDetails(selectedModel);
        });
        
        // Pobierz szczegóły dla aktualnie wybranego modelu
        fetchModelDetails($('#claude_model').val());
        
        // Dodaj style dla kart informacyjnych modeli
        $('head').append(`
            <style>
                .gca-model-details {
                    margin-top: 15px;
                }
                
                .gca-model-info-card {
                    background-color: #f9f9f9;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                    overflow: hidden;
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
                }
                
                .gca-model-info-header {
                    background-color: #f3f4f6;
                    padding: 12px 16px;
                    border-bottom: 1px solid #e5e7eb;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                
                .gca-model-info-header h3 {
                    margin: 0;
                    font-size: 16px;
                    font-weight: 600;
                }
                
                .gca-model-badges {
                    display: flex;
                    gap: 8px;
                }
                
                .gca-badge {
                   display: inline-block;
                   padding: 3px 8px;
                   border-radius: 12px;
                   font-size: 12px;
                   font-weight: 500;
                   text-transform: uppercase;
               }
               
               .gca-badge-green {
                   background-color: #d1fae5;
                   color: #047857;
               }
               
               .gca-badge-yellow {
                   background-color: #fef3c7;
                   color: #92400e;
               }
               
               .gca-badge-red {
                   background-color: #fee2e2;
                   color: #b91c1c;
               }
               
               .gca-badge-purple {
                   background-color: #ede9fe;
                   color: #6d28d9;
               }
               
               .gca-badge-gray {
                   background-color: #f3f4f6;
                   color: #4b5563;
               }
               
               .gca-model-info-body {
                   padding: 16px;
               }
               
               .gca-model-info-body p {
                   margin-top: 0;
                   margin-bottom: 12px;
               }
               
               .gca-model-info-points {
                   display: flex;
                   flex-direction: column;
                   gap: 8px;
               }
               
               .gca-model-info-item {
                   font-size: 14px;
                   line-height: 1.5;
               }
               
               @media (max-width: 768px) {
                   .gca-model-info-header {
                       flex-direction: column;
                       align-items: flex-start;
                   }
                   
                   .gca-model-badges {
                       margin-top: 8px;
                   }
               }
           </style>
       `);
   });
})(jQuery);