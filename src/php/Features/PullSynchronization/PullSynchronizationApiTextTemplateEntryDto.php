<?php

namespace VeraTag\Features\PullSynchronization;

/** Mirrors CmsPluginTextTemplateEntryDto. */
final class PullSynchronizationApiTextTemplateEntryDto
{
    public function __construct(private readonly string $name, private readonly array $issues, private readonly array $texts)
    {
    }
    public static function fromArray(array $v): self
    {
        $issues = array();
        if (isset($v['issues']) && is_array($v['issues'])) {
            foreach ($v['issues'] as $issue) {
                if (is_array($issue)) {
                    $issues[] = PullSynchronizationApiIssueDto::fromArray($issue);
                }
            }
        }
        $texts = array();
        if (isset($v['texts']) && is_array($v['texts'])) {
            foreach ($v['texts'] as $lang => $text) {
                if (is_string($lang) && is_string($text)) {
                    $texts[ $lang ] = $text;
                }
            }
        }
        return new self(isset($v['name']) && is_string($v['name']) ? trim($v['name']) : '', $issues, $texts);
    }
    public function toArray(): array
    {
        return array(
            'name'   => $this->name,
            'issues' => array_map(static fn (PullSynchronizationApiIssueDto $x): array => $x->toArray(), $this->issues),
            'texts'  => $this->texts,
        );
    }
}
