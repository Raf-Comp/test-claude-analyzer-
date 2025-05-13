<?php
// Jeśli ten plik jest wywołany bezpośrednio, przerwij
if (!defined('WPINC')) {
    die;
}
?>

<div class="wrap">
    <h2><?php echo esc_html__('Ustawienia Repozytoriów', 'github-claude-analyzer'); ?></h2>
    
    <div class="gca-settings-container">
        <form method="post" action="options.php">
            <?php settings_fields('gca_repository_settings_group'); ?>
            
            <div class="gca-card">
                <div class="gca-card-header">
                    <h3><i class="dashicons dashicons-admin-generic"></i> <?php echo esc_html__('Ustawienia API Repozytoriów', 'github-claude-analyzer'); ?></h3>
                </div>
                <div class="gca-card-body">
                    <div class="gca-tabs">
                        <div class="gca-tab-nav">
                            <button type="button" class="gca-tab-button active" data-tab="github">
                                <span class="dashicons dashicons-github"></span> GitHub
                            </button>
                            <button type="button" class="gca-tab-button" data-tab="gitlab">
                                <span class="dashicons dashicons-media-code"></span> GitLab
                            </button>
                            <button type="button" class="gca-tab-button" data-tab="bitbucket">
                                <span class="dashicons dashicons-admin-site"></span> Bitbucket
                            </button>
                        </div>
                        
                        <div class="gca-tab-content">
                            <div class="gca-tab-pane active" id="github">
                                <div class="gca-form-group">
                                    <label for="github_token"><?php echo esc_html__('GitHub Token', 'github-claude-analyzer'); ?></label>
                                    <input type="password" id="github_token" name="gca_repository_settings[github_token]" 
                                           value="<?php echo esc_attr(isset($settings['github_token']) ? $settings['github_token'] : ''); ?>" class="regular-text">
                                    <p class="description">
                                        <?php echo esc_html__('Token dostępu do GitHub. Stwórz token z uprawnieniami repo:read na stronie:', 'github-claude-analyzer'); ?>
                                        <a href="https://github.com/settings/tokens" target="_blank">https://github.com/settings/tokens</a>
                                    </p>
                                </div>
                                
                                <div class="gca-form-actions">
                                    <button type="button" id="verify-github-token" class="button button-secondary">
                                        <i class="dashicons dashicons-yes-alt"></i> <?php echo esc_html__('Weryfikuj token', 'github-claude-analyzer'); ?>
                                    </button>
                                </div>
                                
                                <div id="github-token-status" class="gca-token-status" style="margin-top: 15px; display: none;"></div>
                            </div>
                            
                            <div class="gca-tab-pane" id="gitlab">
                                <div class="gca-form-group">
                                    <label for="gitlab_token"><?php echo esc_html__('GitLab Token', 'github-claude-analyzer'); ?></label>
                                    <input type="password" id="gitlab_token" name="gca_repository_settings[gitlab_token]" 
                                           value="<?php echo esc_attr(isset($settings['gitlab_token']) ? $settings['gitlab_token'] : ''); ?>" class="regular-text">
                                    <p class="description">
                                        <?php echo esc_html__('Token dostępu do GitLab. Stwórz token na stronie:', 'github-claude-analyzer'); ?>
                                        <a href="https://gitlab.com/-/profile/personal_access_tokens" target="_blank">https://gitlab.com/-/profile/personal_access_tokens</a>
                                    </p>
                                </div>
                                
                                <div class="gca-form-actions">
                                    <button type="button" id="verify-gitlab-token" class="button button-secondary">
                                        <i class="dashicons dashicons-yes-alt"></i> <?php echo esc_html__('Weryfikuj token', 'github-claude-analyzer'); ?>
                                    </button>
                                </div>
                                
                                <div id="gitlab-token-status" class="gca-token-status" style="margin-top: 15px; display: none;"></div>
                            </div>
                            
                            <div class="gca-tab-pane" id="bitbucket">
                                <div class="gca-form-group">
                                    <label for="bitbucket_username"><?php echo esc_html__('Nazwa użytkownika Bitbucket', 'github-claude-analyzer'); ?></label>
                                    <input type="text" id="bitbucket_username" name="gca_repository_settings[bitbucket_username]" 
                                           value="<?php echo esc_attr(isset($settings['bitbucket_username']) ? $settings['bitbucket_username'] : ''); ?>" class="regular-text">
                                </div>
                                
                                <div class="gca-form-group">
                                    <label for="bitbucket_app_password"><?php echo esc_html__('Hasło aplikacji Bitbucket', 'github-claude-analyzer'); ?></label>
                                    <input type="password" id="bitbucket_app_password" name="gca_repository_settings[bitbucket_app_password]" 
                                           value="<?php echo esc_attr(isset($settings['bitbucket_app_password']) ? $settings['bitbucket_app_password'] : ''); ?>" class="regular-text">
                                    <p class="description">
                                        <?php echo esc_html__('Hasło aplikacji Bitbucket. Stwórz hasło aplikacji na stronie:', 'github-claude-analyzer'); ?>
                                        <a href="https://bitbucket.org/account/settings/app-passwords/" target="_blank">https://bitbucket.org/account/settings/app-passwords/</a>
                                    </p>
                                </div>
                                
                                <div class="gca-form-actions">
                                    <button type="button" id="verify-bitbucket-credentials" class="button button-secondary">
                                        <i class="dashicons dashicons-yes-alt"></i> <?php echo esc_html__('Weryfikuj poświadczenia', 'github-claude-analyzer'); ?>
                                    </button>
                                </div>
                                
                                <div id="bitbucket-credentials-status" class="gca-token-status" style="margin-top: 15px; display: none;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="gca-card">
                <div class="gca-card-header">
                    <h3><i class="dashicons dashicons-admin-settings"></i> <?php echo esc_html__('Ustawienia Zaawansowane', 'github-claude-analyzer'); ?></h3>
                </div>
                <div class="gca-card-body">
                    <div class="gca-form-group">
                        <label for="default_repo_type"><?php echo esc_html__('Domyślny typ repozytorium', 'github-claude-analyzer'); ?></label>
                        <select id="default_repo_type" name="gca_repository_settings[default_repo_type]" class="regular-text">
                            <option value="github" <?php selected(isset($settings['default_repo_type']) ? $settings['default_repo_type'] : 'github', 'github'); ?>>GitHub</option>
                            <option value="gitlab" <?php selected(isset($settings['default_repo_type']) ? $settings['default_repo_type'] : 'github', 'gitlab'); ?>>GitLab</option>
                            <option value="bitbucket" <?php selected(isset($settings['default_repo_type']) ? $settings['default_repo_type'] : 'github', 'bitbucket'); ?>>Bitbucket</option>