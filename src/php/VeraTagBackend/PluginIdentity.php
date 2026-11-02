<?php

namespace VeraTag\VeraTagBackend;

/** This plugin's identity as reported to the backend's release-compatibility gate on every request. */
final class PluginIdentity
{
    /** Matches the veratag backend's seeded channel type unique name for WordPress. */
    private const CHANNEL_TYPE = 'channel:type:cms:wordpress';

    private static ?string $cached_version = null;

    public function getChannelType(): string
    {
        return self::CHANNEL_TYPE;
    }

    /** Reads the "Version:" header from the main plugin file's docblock — the only place the version is declared. */
    public function getVersion(): string
    {
        if (null !== self::$cached_version) {
            return self::$cached_version;
        }

        $plugin_file = $this->getPluginFilePath();

        if (! function_exists('get_file_data') || ! is_readable($plugin_file)) {
            self::$cached_version = '';
            return self::$cached_version;
        }

        $file_data            = get_file_data($plugin_file, array( 'Version' => 'Version' ));
        self::$cached_version = is_string($file_data['Version'] ?? null) ? trim($file_data['Version']) : '';

        return self::$cached_version;
    }

    private function getPluginFilePath(): string
    {
        if (defined('VERATAG_WORDPRESS_PLUGIN_FILE')) {
            return (string) VERATAG_WORDPRESS_PLUGIN_FILE;
        }

        return dirname(__DIR__, 3) . '/veratag-wordpress-plugin.php';
    }
}
