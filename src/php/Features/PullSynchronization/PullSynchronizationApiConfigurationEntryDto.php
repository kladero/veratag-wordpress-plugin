<?php

namespace VeraTag\Features\PullSynchronization;

/** Mirrors CmsPluginConfigurationEntryDto. */
final class PullSynchronizationApiConfigurationEntryDto
{
    public function __construct(private readonly string $name, private readonly array $issues, private readonly array $placement_refs, private readonly array $slots)
    {
    }
    public static function fromArray(array $value): self
    {
        $issues         = self::issuesFromArray($value['issues'] ?? array());
        $placement_refs = isset($value['placementRefs']) && is_array($value['placementRefs']) ? array_values(array_map('strval', array_filter($value['placementRefs'], 'is_string'))) : array();
        $slots          = array();
        if (isset($value['slots']) && is_array($value['slots'])) {
            foreach ($value['slots'] as $slot_type => $by_language) {
                if (! is_string($slot_type) || ! is_array($by_language)) {
                    continue;
                }
                foreach ($by_language as $bcp47 => $slot_value) {
                    if (is_string($bcp47) && is_array($slot_value)) {
                        $slots[ $slot_type ][ $bcp47 ] = PullSynchronizationApiSlotValueDto::fromArray($slot_value);
                    }
                }
            }
        }
        return new self(isset($value['name']) && is_string($value['name']) ? trim($value['name']) : '', $issues, $placement_refs, $slots);
    }
    public function getName(): string
    {
        return $this->name;
    }
    public function getIssues(): array
    {
        return $this->issues;
    }
    public function getPlacementRefs(): array
    {
        return $this->placement_refs;
    }
    public function getSlots(): array
    {
        return $this->slots;
    }
    public function toArray(): array
    {
        $slots = array();
        foreach ($this->slots as $slot_type => $by_language) {
            foreach ($by_language as $bcp47 => $value) {
                $slots[ $slot_type ][ $bcp47 ] = $value->toArray();
            }
        }
        return array(
            'name'          => $this->name,
            'issues'        => array_map(static fn (PullSynchronizationApiIssueDto $x): array => $x->toArray(), $this->issues),
            'placementRefs' => $this->placement_refs,
            'slots'         => $slots,
        );
    }
    private static function issuesFromArray(mixed $value): array
    {
        $issues = array();
        if (is_array($value)) {
            foreach ($value as $issue) {
                if (is_array($issue)) {
                    $issues[] = PullSynchronizationApiIssueDto::fromArray($issue);
                }
            }
        } return $issues;
    }
}
