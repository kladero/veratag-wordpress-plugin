<?php

namespace VeraTag\Bootstrap;

final class AdminShellBootstrap
{
    private const MENU_SLUG                  = 'veratag';
    private const DISCLOSURES_MENU_SLUG      = 'veratag-content';
    private const SYNCHRONIZATION_MENU_SLUG  = 'veratag-synchronization';
    private const SETTINGS_MENU_SLUG         = 'veratag-settings';
    private const DISCLOSURES_BODY_CLASS     = 'veratag_page_veratag-content';
    private const SYNCHRONIZATION_BODY_CLASS = 'veratag_page_veratag-synchronization';
    private const SETTINGS_BODY_CLASS        = 'veratag_page_veratag-settings';

    public function registerMenu(): void
    {
        add_menu_page(
            'vera[tag]',
            'vera[tag]',
            'manage_options',
            self::MENU_SLUG,
            array( $this, 'renderGeneralPage' ),
            $this->getAdminMenuIconUrl(),
            58
        );

        add_submenu_page(
            self::MENU_SLUG,
            'General',
            'General',
            'manage_options',
            self::MENU_SLUG,
            array( $this, 'renderGeneralPage' )
        );

        add_submenu_page(
            self::MENU_SLUG,
            'Content',
            'Content',
            'manage_options',
            self::DISCLOSURES_MENU_SLUG,
            array( $this, 'renderDisclosuresPage' )
        );

        add_submenu_page(
            self::MENU_SLUG,
            'Synchronization',
            'Synchronization',
            'manage_options',
            self::SYNCHRONIZATION_MENU_SLUG,
            array( $this, 'renderSynchronizationPage' )
        );

        add_submenu_page(
            self::MENU_SLUG,
            'Settings',
            'Settings',
            'manage_options',
            self::SETTINGS_MENU_SLUG,
            array( $this, 'renderSettingsPage' )
        );
    }

    public function renderGeneralPage(): void
    {
        $this->renderAdminPageShell();
    }

    public function renderSettingsPage(): void
    {
        $this->renderAdminPageShell();
    }

    public function renderDisclosuresPage(): void
    {
        $this->renderAdminPageShell();
    }

    public function renderSynchronizationPage(): void
    {
        $this->renderAdminPageShell();
    }

    public function printMenuIconStyles(): void
    {
        $inactive_icon_url = esc_url($this->getAdminMenuInactiveIconUrl());
        $active_icon_url   = esc_url($this->getAdminMenuActiveIconUrl());
        ?>
        <style>
            #toplevel_page_<?php echo esc_html(self::MENU_SLUG); ?> .wp-menu-image img {
                content: url('<?php echo esc_url($inactive_icon_url); ?>');
                width: 20px;
                height: 20px;
                padding-top: 7px;
                object-fit: contain;
                opacity: 1;
            }

            #toplevel_page_<?php echo esc_html(self::MENU_SLUG); ?>:hover .wp-menu-image img,
            #toplevel_page_<?php echo esc_html(self::MENU_SLUG); ?>.current .wp-menu-image img,
            #toplevel_page_<?php echo esc_html(self::MENU_SLUG); ?>.wp-has-current-submenu .wp-menu-image img {
                content: url('<?php echo esc_url($active_icon_url); ?>');
            }
        </style>
        <?php
    }

    public function printCanvasStyles(string $current_page): void
    {
        if (! $this->isVeratagAdminPage($current_page)) {
            return;
        }
        ?>
        <style>
            body.toplevel_page_<?php echo esc_html(self::MENU_SLUG); ?>,
            body.toplevel_page_<?php echo esc_html(self::MENU_SLUG); ?> #wpwrap,
            body.toplevel_page_<?php echo esc_html(self::MENU_SLUG); ?> #wpcontent,
            body.toplevel_page_<?php echo esc_html(self::MENU_SLUG); ?> #wpbody,
            body.toplevel_page_<?php echo esc_html(self::MENU_SLUG); ?> #wpbody-content,
            body.toplevel_page_<?php echo esc_html(self::MENU_SLUG); ?> .wrap,
            body.<?php echo esc_html(self::DISCLOSURES_BODY_CLASS); ?>,
            body.<?php echo esc_html(self::DISCLOSURES_BODY_CLASS); ?> #wpwrap,
            body.<?php echo esc_html(self::DISCLOSURES_BODY_CLASS); ?> #wpcontent,
            body.<?php echo esc_html(self::DISCLOSURES_BODY_CLASS); ?> #wpbody,
            body.<?php echo esc_html(self::DISCLOSURES_BODY_CLASS); ?> #wpbody-content,
            body.<?php echo esc_html(self::DISCLOSURES_BODY_CLASS); ?> .wrap,
            body.<?php echo esc_html(self::SYNCHRONIZATION_BODY_CLASS); ?>,
            body.<?php echo esc_html(self::SYNCHRONIZATION_BODY_CLASS); ?> #wpwrap,
            body.<?php echo esc_html(self::SYNCHRONIZATION_BODY_CLASS); ?> #wpcontent,
            body.<?php echo esc_html(self::SYNCHRONIZATION_BODY_CLASS); ?> #wpbody,
            body.<?php echo esc_html(self::SYNCHRONIZATION_BODY_CLASS); ?> #wpbody-content,
            body.<?php echo esc_html(self::SYNCHRONIZATION_BODY_CLASS); ?> .wrap,
            body.<?php echo esc_html(self::SETTINGS_BODY_CLASS); ?>,
            body.<?php echo esc_html(self::SETTINGS_BODY_CLASS); ?> #wpwrap,
            body.<?php echo esc_html(self::SETTINGS_BODY_CLASS); ?> #wpcontent,
            body.<?php echo esc_html(self::SETTINGS_BODY_CLASS); ?> #wpbody,
            body.<?php echo esc_html(self::SETTINGS_BODY_CLASS); ?> #wpbody-content,
            body.<?php echo esc_html(self::SETTINGS_BODY_CLASS); ?> .wrap {
                background: #ffffff;
            }

            body.toplevel_page_<?php echo esc_html(self::MENU_SLUG); ?> #wpbody-content,
            body.<?php echo esc_html(self::DISCLOSURES_BODY_CLASS); ?> #wpbody-content,
            body.<?php echo esc_html(self::SYNCHRONIZATION_BODY_CLASS); ?> #wpbody-content,
            body.<?php echo esc_html(self::SETTINGS_BODY_CLASS); ?> #wpbody-content {
                padding: 0;
            }

            body.toplevel_page_<?php echo esc_html(self::MENU_SLUG); ?> #wpcontent,
            body.<?php echo esc_html(self::DISCLOSURES_BODY_CLASS); ?> #wpcontent,
            body.<?php echo esc_html(self::SYNCHRONIZATION_BODY_CLASS); ?> #wpcontent,
            body.<?php echo esc_html(self::SETTINGS_BODY_CLASS); ?> #wpcontent {
                padding-left: 0;
            }
        </style>
        <?php
    }

    public function hideCoreUpdateNag(string $current_page): void
    {
        if (! $this->isVeratagAdminPage($current_page)) {
            return;
        }

        remove_action('admin_notices', 'update_nag', 3);
        remove_action('network_admin_notices', 'update_nag', 3);
    }

    public function isVeratagAdminPage(string $current_page): bool
    {
        return in_array($current_page, array( self::MENU_SLUG, self::DISCLOSURES_MENU_SLUG, self::SYNCHRONIZATION_MENU_SLUG, self::SETTINGS_MENU_SLUG ), true);
    }

    public function resolveCurrentPageKey(string $current_page): string
    {
        if (self::DISCLOSURES_MENU_SLUG === $current_page) {
            return 'disclosures';
        }

        if (self::SYNCHRONIZATION_MENU_SLUG === $current_page) {
            return 'synchronization';
        }

        if (self::SETTINGS_MENU_SLUG === $current_page) {
            return 'settings';
        }

        return 'general';
    }

    public function getCurrentAdminPage(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reads only the current admin route and never changes state.
        return isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    }

    public function getAdminWordmarkUrl(): string
    {
        return plugin_dir_url($this->getPluginFilePath()) . 'assets/logos/veratag/vera-tag-wordmark.svg';
    }

    private function renderAdminPageShell(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        ?>
        <div id="veratag-admin-root"></div>
        <?php
    }

    private function getAdminMenuIconUrl(): string
    {
        return $this->getAdminMenuInactiveIconUrl();
    }

    private function getAdminMenuInactiveIconUrl(): string
    {
        return plugin_dir_url($this->getPluginFilePath()) . 'assets/logos/veratag/vera-tag-mark-white.svg';
    }

    private function getAdminMenuActiveIconUrl(): string
    {
        return plugin_dir_url($this->getPluginFilePath()) . 'assets/logos/veratag/vera-tag-mark.svg';
    }

    private function getPluginFilePath(): string
    {
        if (defined('VERATAG_WORDPRESS_PLUGIN_FILE')) {
            return (string) VERATAG_WORDPRESS_PLUGIN_FILE;
        }

        return dirname(__DIR__, 3) . '/veratag-wordpress-plugin.php';
    }
}
