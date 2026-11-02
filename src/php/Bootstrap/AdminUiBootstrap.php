<?php

namespace VeraTag\Bootstrap;

final class AdminUiBootstrap
{
    private const SCRIPT_HANDLE             = 'veratag_admin_ui_app';
    private const MENU_SLUG                 = 'veratag';
    private const DISCLOSURES_MENU_SLUG     = 'veratag-content';
    private const SYNCHRONIZATION_MENU_SLUG = 'veratag-synchronization';
    private const SETTINGS_MENU_SLUG        = 'veratag-settings';

    public function enqueueAssets(
        string $current_page,
        string $current_page_key,
        string $wordmark_url,
        string $channel_dashboard_url
    ): void {
        if (! $this->isVeratagAdminPage($current_page)) {
            return;
        }

        $plugin_file = $this->getPluginFilePath();
        $script_path = plugin_dir_path($plugin_file) . 'assets/admin/veratag-admin-ui.js';
        $style_path  = plugin_dir_path($plugin_file) . 'assets/admin/veratag-admin-ui.css';
        $asset_base  = plugin_dir_url($plugin_file) . 'assets/admin/';
        $icons_base  = plugin_dir_url($plugin_file) . 'assets/icons/';

        if (file_exists($style_path)) {
            wp_enqueue_style(
                self::SCRIPT_HANDLE,
                $asset_base . 'veratag-admin-ui.css',
                array(),
                (string) filemtime($style_path)
            );
        }

        if (file_exists($script_path)) {
            $current_user              = wp_get_current_user();
            $current_user_display_name = '';

            if ($current_user instanceof \WP_User && is_string($current_user->display_name)) {
                $current_user_display_name = $current_user->display_name;
            }

            wp_enqueue_script(
                self::SCRIPT_HANDLE,
                $asset_base . 'veratag-admin-ui.js',
                array(),
                (string) filemtime($script_path),
                true
            );

            wp_add_inline_script(
                self::SCRIPT_HANDLE,
                'window.veratagAdminConfig = ' . wp_json_encode(
                    array(
                        'currentPage'            => $current_page_key,
                        'restApiBaseUrl'         => esc_url_raw(rest_url('veratag/v1/')),
                        'restNonce'              => wp_create_nonce('wp_rest'),
                        'channelDashboardUrl'    => esc_url_raw($channel_dashboard_url),
                        'settingsPageUrl'        => esc_url_raw(admin_url('admin.php?page=' . self::SETTINGS_MENU_SLUG)),
                        'iconsBaseUrl'           => esc_url_raw($icons_base),
                        'statusIcons'            => array(
                            'success' => esc_url_raw($icons_base . 'l/circle-check-big.svg'),
                            'failure' => esc_url_raw($icons_base . 'l/circle-x.svg'),
                        ),
                        'formIcons'              => array(
                            'selectChevron' => esc_url_raw($icons_base . 'l/chevron-right.svg'),
                            'server'        => esc_url_raw($icons_base . 'l/server.svg'),
                            'userRound'     => esc_url_raw($icons_base . 'l/user-round.svg'),
                        ),
                        'currentUserDisplayName' => $current_user_display_name,
                        'wordmarkUrl'            => $wordmark_url,
                    )
                ) . ';',
                'before'
            );
        }
    }

    private function isVeratagAdminPage(string $current_page): bool
    {
        return in_array(
            $current_page,
            array(
                self::MENU_SLUG,
                self::DISCLOSURES_MENU_SLUG,
                self::SYNCHRONIZATION_MENU_SLUG,
                self::SETTINGS_MENU_SLUG,
            ),
            true
        );
    }

    private function getPluginFilePath(): string
    {
        if (defined('VERATAG_WORDPRESS_PLUGIN_FILE')) {
            return (string) VERATAG_WORDPRESS_PLUGIN_FILE;
        }

        return dirname(__DIR__, 3) . '/veratag-wordpress-plugin.php';
    }
}
