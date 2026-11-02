<?php

namespace VeraTag\Features\PullSynchronization;

/** Mirrors CmsPluginSlotValueDto: exactly one of templateRef or custom. */
final class PullSynchronizationApiSlotValueDto
{
    public function __construct(private readonly ?string $template_ref, private readonly ?string $custom)
    {
    }
    public static function fromArray(array $value): self
    {
        $template_ref = isset($value['templateRef']) && is_string($value['templateRef']) ? trim($value['templateRef']) : null;
        $custom       = isset($value['custom']) && is_string($value['custom']) ? $value['custom'] : null;
        return new self('' === $template_ref ? null : $template_ref, $custom);
    }
    public function getTemplateRef(): ?string
    {
        return $this->template_ref;
    }
    public function getCustom(): ?string
    {
        return $this->custom;
    }
    public function toArray(): array
    {
        return null !== $this->template_ref ? array( 'templateRef' => $this->template_ref ) : array( 'custom' => $this->custom ?? '' );
    }
}
