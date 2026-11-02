<?php

namespace VeraTag\Settings\OptionValues;

/**
 * There is deliberately no stored "dashboard URL" field here — the full
 * dashboard link is always derived at read time from
 * {@see ChannelPaths::getChannelDashboardPath} (backend-owned, relative)
 * joined with {@code HttpConfig::getDashboardBaseUrl()} (build-time, this
 * site's own vera[tag] dashboard origin). See
 * {@code GlobalSettings::getChannelDashboardUrl()}. A separate stored
 * "dashboard URL" field existed here previously but nothing ever wrote to
 * it, so the dashboard link never appeared in the admin UI.
 */
final class ChannelDetails
{
    public function __construct(
        private readonly string $display_name,
        private readonly ChannelPaths $paths
    ) {
    }

    public static function createInitial(): self
    {
        return new self('', ChannelPaths::createInitial());
    }

    public static function fromArray(array $value): self
    {
        $display_name = isset($value['displayName']) && is_string($value['displayName'])
            ? trim($value['displayName'])
            : '';
        $paths        = isset($value['paths']) && is_array($value['paths'])
            ? ChannelPaths::fromArray($value['paths'])
            : ChannelPaths::createInitial();

        return new self($display_name, $paths);
    }

    public function getDisplayName(): string
    {
        return $this->display_name;
    }

    public function getPaths(): ChannelPaths
    {
        return $this->paths;
    }

    public function toArray(): array
    {
        return array(
            'displayName' => $this->display_name,
            'paths'       => $this->paths->toArray(),
        );
    }
}
