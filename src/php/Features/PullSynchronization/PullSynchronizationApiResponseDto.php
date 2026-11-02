<?php

namespace VeraTag\Features\PullSynchronization;

/** Root mirror of CmsPluginDisclosuresResponseDto's ref-keyed disclosure payload. */
final class PullSynchronizationApiResponseDto
{
    public function __construct(
        private readonly PullSynchronizationApiChannelDetailsDto $channel_details,
        private readonly array $configurations,
        private readonly array $placements,
        private readonly array $text_templates,
        private readonly array $pages
    ) {
    }

    public static function fromArray(array $value): self
    {
        $configurations = array();
        foreach (self::readMap($value, 'configurations') as $ref => $entry) {
            if (is_string($ref) && is_array($entry)) {
                $configurations[ $ref ] = PullSynchronizationApiConfigurationEntryDto::fromArray($entry);
            }
        }
        $placements = array();
        foreach (self::readMap($value, 'placements') as $ref => $entry) {
            if (is_string($ref) && is_array($entry)) {
                $placements[ $ref ] = PullSynchronizationApiPlacementEntryDto::fromArray($entry);
            }
        }
        $text_templates = array();
        foreach (self::readMap($value, 'textTemplates') as $ref => $entry) {
            if (is_string($ref) && is_array($entry)) {
                $text_templates[ $ref ] = PullSynchronizationApiTextTemplateEntryDto::fromArray($entry);
            }
        }
        $pages = array();
        if (isset($value['pages']) && is_array($value['pages'])) {
            foreach (array_values($value['pages']) as $page) {
                if (is_array($page)) {
                    $pages[] = PullSynchronizationApiPageDto::fromArray($page);
                }
            }
        }
        return new self(
            PullSynchronizationApiChannelDetailsDto::fromArray(isset($value['channelDetails']) && is_array($value['channelDetails']) ? $value['channelDetails'] : array()),
            $configurations,
            $placements,
            $text_templates,
            $pages
        );
    }

    public function getChannelDetails(): PullSynchronizationApiChannelDetailsDto
    {
        return $this->channel_details;
    }
    /** @return array<string,PullSynchronizationApiConfigurationEntryDto> */
    public function getConfigurations(): array
    {
        return $this->configurations;
    }
    /** @return array<string,PullSynchronizationApiPlacementEntryDto> */
    public function getPlacements(): array
    {
        return $this->placements;
    }
    /** @return array<string,PullSynchronizationApiTextTemplateEntryDto> */
    public function getTextTemplates(): array
    {
        return $this->text_templates;
    }
    /** @return PullSynchronizationApiPageDto[] */
    public function getPages(): array
    {
        return $this->pages;
    }
    public function countRegisteredContentItems(): int
    {
        return array_sum(array_map(static fn (PullSynchronizationApiPageDto $page): int => count($page->getContent()), $this->pages));
    }
    public function countConfiguredContentItems(): int
    {
        $count = 0;
        foreach ($this->pages as $page) {
            foreach ($page->getContent() as $item) {
                if (null !== $item->getConfigurationRef()) {
                    ++$count;
                }
            }
        } return $count;
    }
    public function countUnconfiguredContentItems(): int
    {
        return $this->countRegisteredContentItems() - $this->countConfiguredContentItems();
    }
    public function toArray(): array
    {
        return array(
            'channelDetails' => $this->channel_details->toArray(),
            'configurations' => array_map(static fn (PullSynchronizationApiConfigurationEntryDto $x): array => $x->toArray(), $this->configurations),
            'placements'     => array_map(static fn (PullSynchronizationApiPlacementEntryDto $x): array => $x->toArray(), $this->placements),
            'textTemplates'  => array_map(static fn (PullSynchronizationApiTextTemplateEntryDto $x): array => $x->toArray(), $this->text_templates),
            'pages'          => array_map(static fn (PullSynchronizationApiPageDto $x): array => $x->toArray(), $this->pages),
        );
    }
    private static function readMap(array $value, string $key): array
    {
        return isset($value[ $key ]) && is_array($value[ $key ]) ? $value[ $key ] : array();
    }
}
