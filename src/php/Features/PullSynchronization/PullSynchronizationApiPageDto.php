<?php

namespace VeraTag\Features\PullSynchronization;

/** Mirrors CmsPluginPageDto. */
final class PullSynchronizationApiPageDto
{
    public function __construct(private readonly string $page_url, private readonly array $content)
    {
    }
    public static function fromArray(array $v): self
    {
        $content = array();
        if (isset($v['content']) && is_array($v['content'])) {
            foreach ($v['content'] as $item) {
                if (is_array($item)) {
                    $content[] = PullSynchronizationApiContentItemDto::fromArray($item);
                }
            }
        } return new self(isset($v['pageUrl']) && is_string($v['pageUrl']) ? trim($v['pageUrl']) : '', $content);
    }
    public function getPageUrl(): string
    {
        return $this->page_url;
    }
    public function getContent(): array
    {
        return $this->content;
    }
    public function toArray(): array
    {
        return array(
            'pageUrl' => $this->page_url,
            'content' => array_map(static fn (PullSynchronizationApiContentItemDto $x): array => $x->toArray(), $this->content),
        );
    }
}
