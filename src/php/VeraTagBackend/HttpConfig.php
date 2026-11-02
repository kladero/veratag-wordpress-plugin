<?php

namespace VeraTag\VeraTagBackend;

use VeraTag\Bootstrap\GeneratedBuildConfig;

final class HttpConfig
{
    public function getBaseUrl(): string
    {
        $base_url = GeneratedBuildConfig::BACKEND_BASE_URL;

        if (! in_array($base_url, GeneratedBuildConfig::ALLOWED_BACKEND_BASE_URLS, true)) {
            throw new \RuntimeException('The configured VeraTag backend URL is not an allowed HTTPS endpoint.');
        }

        return $base_url;
    }

    public function getDashboardBaseUrl(): string
    {
        $base_url = GeneratedBuildConfig::DASHBOARD_BASE_URL;

        if (! in_array($base_url, GeneratedBuildConfig::ALLOWED_DASHBOARD_BASE_URLS, true)) {
            throw new \RuntimeException('The configured VeraTag dashboard URL is not an allowed HTTPS endpoint.');
        }

        return $base_url;
    }
}
