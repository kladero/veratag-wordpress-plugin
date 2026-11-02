<?php

/**
 * Plugin Name: vera[tag]
 * Plugin URI: https://vera-tag.com
 * Description: Connect your WordPress site with vera[tag] to automatically synchronize and serve AI content disclosures to your website visitors.
 * Version: 1.0.0
 * Author: Kladero GmbH
 * Author URI: https://kladero.com
 * Requires PHP: 8.3
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: veratag-wordpress-plugin
 * Requires at least: 6.5
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('VERATAG_WORDPRESS_PLUGIN_FILE')) {
    define('VERATAG_WORDPRESS_PLUGIN_FILE', __FILE__);
}

// PSR-4 autoloading for VeraTag classes. No Composer runtime dependency.
spl_autoload_register(
    static function (string $class): void {
        $prefix = 'VeraTag\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $relative = substr($class, strlen($prefix));
        $file = __DIR__ . '/src/php/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
);

$veratag_plugin = new \VeraTag\Bootstrap\Plugin();

register_activation_hook(__FILE__, array( $veratag_plugin, 'onActivate' ));
register_deactivation_hook(__FILE__, array( $veratag_plugin, 'onDeactivate' ));
register_uninstall_hook(__FILE__, array( '\VeraTag\Bootstrap\PluginUninstaller', 'run' ));

$veratag_plugin->register();
