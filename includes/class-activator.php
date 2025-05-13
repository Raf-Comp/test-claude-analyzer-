<?php
/**
 * Uruchamiana podczas aktywacji wtyczki.
 */
class GitHub_Claude_Analyzer_Activator {
    /**
     * Tworzy niezbędne tabele bazy danych i inicjalizuje ustawienia wtyczki.
     */
    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Utwórz tabelę claude_api_keys
        $table_name = $wpdb->prefix . 'claude_api_keys';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            api_key varchar(255) NOT NULL,
            github_token varchar(255) DEFAULT '',
            model varchar(100) DEFAULT 'claude-3-5-sonnet-20240620',
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";
        
        // Utwórz tabelę claude_logs
        $table_logs = $wpdb->prefix . 'claude_logs';
        $sql_logs = "CREATE TABLE $table_logs (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            repository_url varchar(255) NOT NULL,
            prompt text NOT NULL,
            file_filters text,
            max_files int(11) DEFAULT 30,
            total_files int(11) DEFAULT 0,
            status varchar(50) DEFAULT 'pending',
            response longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        // Utwórz tabelę przetworzonych plików
        $table_processed_files = $wpdb->prefix . 'claude_processed_files';
        $sql_processed_files = "CREATE TABLE $table_processed_files (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            repository_url varchar(255) NOT NULL,
            file_path varchar(255) NOT NULL,
            content_hash varchar(32) NOT NULL,
            analysis longtext NOT NULL,
            processed_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY user_repo (user_id, repository_url),
            KEY file_path (file_path)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($sql_logs);
        dbDelta($sql_processed_files);
        
        // Ustaw domyślne opcje wtyczki
        $options = array(
            'claude_model' => 'claude-3-5-sonnet-20240620',
            'max_tokens' => 4000,
            'max_files_per_analysis' => 30,
            'default_extensions' => array('php', 'js', 'css', 'html')
        );
        
        add_option('gca_settings', $options);
    }
}