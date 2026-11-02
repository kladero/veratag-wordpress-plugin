<?php

namespace VeraTag\VeraTagBackend;

final class HttpEndpoints
{
    private const DISCLOSURES_PATH                          = '/cms-integrations/pull-synchronization';
    private const RENDER_SCRIPT_PATH                        = '/cms-integrations/render-script';
    private const PUSH_API_PATH                             = '/cms-integrations/push-api';
    private const VISITOR_DISCLOSURE_LANGUAGE_SETTINGS_PATH = '/cms-integrations/visitor-disclosure-language-settings';

    public function __construct(
        private readonly HttpConfig $http_config
    ) {
    }

    public function getDisclosuresUrl(): string
    {
        $normalized_base_url         = rtrim($this->http_config->getBaseUrl(), '/');
        $normalized_disclosures_path = rtrim(self::DISCLOSURES_PATH, '/');

        if (str_ends_with($normalized_base_url, $normalized_disclosures_path)) {
            return $normalized_base_url;
        }

        return $normalized_base_url . self::DISCLOSURES_PATH;
    }

    public function getPushApiUrl(): string
    {
        return rtrim($this->http_config->getBaseUrl(), '/') . self::PUSH_API_PATH;
    }

    public function getPushApiEnabledUrl(): string
    {
        return $this->getPushApiUrl() . '/enabled';
    }

    public function getRenderScriptUrl(string $version): string
    {
        $normalized_base_url = rtrim($this->http_config->getBaseUrl(), '/');

        return $normalized_base_url . self::RENDER_SCRIPT_PATH . '?version=' . rawurlencode($version);
    }

    public function getVisitorDisclosureLanguageSettingsUrl(): string
    {
        return rtrim($this->http_config->getBaseUrl(), '/') . self::VISITOR_DISCLOSURE_LANGUAGE_SETTINGS_PATH;
    }
}
