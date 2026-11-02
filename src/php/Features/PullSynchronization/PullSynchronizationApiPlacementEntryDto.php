<?php

namespace VeraTag\Features\PullSynchronization;

/** Mirrors CmsPluginPlacementEntryDto. */
final class PullSynchronizationApiPlacementEntryDto
{
    public function __construct(private readonly string $name, private readonly array $issues, private readonly PullSynchronizationApiPlacementSchemaDto $schema, private readonly PullSynchronizationApiRenderScriptDto $render_script, private readonly mixed $raw_params, private readonly array $slot_bindings)
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
        $bindings = array();
        if (isset($v['slotBindings']) && is_array($v['slotBindings'])) {
            foreach ($v['slotBindings'] as $path => $slot) {
                if (is_string($path) && is_string($slot)) {
                    $bindings[ $path ] = $slot;
                }
            }
        }
        return new self(
            isset($v['name']) && is_string($v['name']) ? trim($v['name']) : '',
            $issues,
            PullSynchronizationApiPlacementSchemaDto::fromArray(isset($v['schema']) && is_array($v['schema']) ? $v['schema'] : array()),
            PullSynchronizationApiRenderScriptDto::fromArray(isset($v['renderScript']) && is_array($v['renderScript']) ? $v['renderScript'] : array()),
            $v['rawParams'] ?? new \stdClass(),
            $bindings
        );
    }
    public function getRenderScript(): PullSynchronizationApiRenderScriptDto
    {
        return $this->render_script;
    }
    public function toArray(): array
    {
        return array(
            'name'         => $this->name,
            'issues'       => array_map(static fn (PullSynchronizationApiIssueDto $x): array => $x->toArray(), $this->issues),
            'schema'       => $this->schema->toArray(),
            'renderScript' => $this->render_script->toArray(),
            'rawParams'    => $this->raw_params,
            'slotBindings' => $this->slot_bindings,
        );
    }
}
