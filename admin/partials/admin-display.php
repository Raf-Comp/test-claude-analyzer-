<?php
// Jeśli ten plik jest wywołany bezpośrednio, przerwij.
if (!defined('WPINC')) {
    die;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="gca-admin-container">
        <div class="gca-card">
            <div class="gca-card-header">
                <h2><i class="dashicons dashicons-code-standards"></i> Analiza Repozytorium GitHub</h2>
            </div>
            <div class="gca-card-body">
                <p>Witaj w wtyczce GitHub Claude Analyzer! Ta wtyczka umożliwia analizę repozytoriów GitHub przy użyciu sztucznej inteligencji Claude.</p>
                
                <div class="gca-info-box">
                    <h3><i class="dashicons dashicons-info"></i> Jak używać?</h3>
                    <p>Aby przeanalizować repozytorium GitHub, możesz:</p>
                    <ol>
                        <li>Użyć formularza poniżej, lub</li>
                        <li>Umieścić shortcode <code>[github_claude_analyzer]</code> na dowolnej stronie lub wpisie.</li>
                    </ol>
                </div>
                
                <h3>Analizuj repozytorium</h3>
                
                <?php
                // Załaduj formularz analizy
                if (class_exists('GitHub_Claude_Analyzer_Public')) {
                    $public = new GitHub_Claude_Analyzer_Public();
                    echo $public->render_analyzer_form();
                } else {
                    echo '<div class="notice notice-error"><p>Nie można załadować formularza analizy. Klasa <code>GitHub_Claude_Analyzer_Public</code> nie istnieje.</p></div>';
                }
                ?>
            </div>
        </div>
        
        <div class="gca-card">
            <div class="gca-card-header">
                <h2><i class="dashicons dashicons-editor-help"></i> Dokumentacja</h2>
            </div>
            <div class="gca-card-body">
                <h3>Shortcode</h3>
                <p>Możesz użyć shortcode'a <code>[github_claude_analyzer]</code> na dowolnej stronie lub wpisie, aby wyświetlić formularz analizy.</p>
                
                <h3>Konfiguracja</h3>
                <p>Przejdź do zakładki <a href="<?php echo admin_url('admin.php?page=github-claude-analyzer-settings'); ?>">Ustawienia</a>, aby skonfigurować klucz API Claude i token GitHub.</p>
                
                <h3>Problemy?</h3>
                <p>Jeśli napotkasz problemy podczas korzystania z wtyczki:</p>
                <ol>
                    <li>Upewnij się, że klucz API Claude i token GitHub są poprawne.</li>
                    <li>Sprawdź, czy repozytorium GitHub jest publiczne lub czy masz odpowiednie uprawnienia do prywatnego repozytorium.</li>
                    <li>Sprawdź, czy wtyczka jest aktywowana i wszystkie pliki zostały poprawnie załadowane.</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<style>
    .gca-admin-container {
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
    
    .gca-info-box {
        background-color: #f0f6fc;
        border-left: 4px solid #3498db;
        padding: 12px 15px;
        margin-bottom: 15px;
    }
    
    .gca-info-box h3 {
        margin-top: 0;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .gca-info-box p {
        margin-top: 0;
    }
</style>