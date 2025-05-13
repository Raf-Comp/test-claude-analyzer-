<?php
/**
 * Klasa obsługująca wielojęzyczność.
 */
class GitHub_Claude_Analyzer_i18n {

    /**
     * Ładuje plik tłumaczenia wtyczki.
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'github-claude-analyzer',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }
}