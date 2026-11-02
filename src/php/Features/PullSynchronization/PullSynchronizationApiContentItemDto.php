<?php

namespace VeraTag\Features\PullSynchronization;

/** Mirrors CmsPluginContentItemDto. */
final class PullSynchronizationApiContentItemDto
{
    public function __construct(private readonly string $content_id, private readonly string $content_type, private readonly string $content_html_tag, private readonly mixed $content_display, private readonly ?string $disclosure_type, private readonly ?string $configuration_ref, private readonly ?bool $should_be_disclosed, private readonly array $issues)
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
        return new self(
            isset($v['contentId']) && is_string($v['contentId']) ? trim($v['contentId']) : '',
            isset($v['contentType']) && is_string($v['contentType']) ? trim($v['contentType']) : '',
            isset($v['contentHtmlTag']) && is_string($v['contentHtmlTag']) ? trim($v['contentHtmlTag']) : '',
            $v['contentDisplay'] ?? null,
            isset($v['disclosureType']) && is_string($v['disclosureType']) ? trim($v['disclosureType']) : null,
            isset($v['configurationRef']) && is_string($v['configurationRef']) ? trim($v['configurationRef']) : null,
            array_key_exists('shouldBeDisclosed', $v) && is_bool($v['shouldBeDisclosed']) ? $v['shouldBeDisclosed'] : null,
            $issues
        );
    }
    public function getContentId(): string
    {
        return $this->content_id;
    }
    public function getContentType(): string
    {
        return $this->content_type;
    }
    public function getContentHtmlTag(): string
    {
        return $this->content_html_tag;
    }
    public function getContentDisplay(): mixed
    {
        return $this->content_display;
    }
    public function getDisclosureType(): ?string
    {
        return $this->disclosure_type;
    }
    public function getConfigurationRef(): ?string
    {
        return null !== $this->configuration_ref && '' !== $this->configuration_ref ? $this->configuration_ref : null;
    }
    public function getShouldBeDisclosed(): ?bool
    {
        return $this->should_be_disclosed;
    }
    public function getIssues(): array
    {
        return $this->issues;
    }
    public function toArray(): array
    {
        return array(
            'contentId'         => $this->content_id,
            'contentType'       => $this->content_type,
            'contentHtmlTag'    => $this->content_html_tag,
            'contentDisplay'    => $this->content_display,
            'disclosureType'    => $this->disclosure_type,
            'configurationRef'  => $this->configuration_ref,
            'shouldBeDisclosed' => $this->should_be_disclosed,
            'issues'            => array_map(static fn (PullSynchronizationApiIssueDto $x): array => $x->toArray(), $this->issues),
        );
    }
}
