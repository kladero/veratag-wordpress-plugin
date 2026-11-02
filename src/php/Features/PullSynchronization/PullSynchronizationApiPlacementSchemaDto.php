<?php

namespace VeraTag\Features\PullSynchronization;

final class PullSynchronizationApiPlacementSchemaDto
{
    public function __construct(private readonly string $schema_name, private readonly string $schema_version)
    {
    }
    public static function fromArray(array $v): self
    {
        return new self(isset($v['schemaName']) && is_string($v['schemaName']) ? trim($v['schemaName']) : '', isset($v['schemaVersion']) && is_string($v['schemaVersion']) ? trim($v['schemaVersion']) : '');
    }
    public function toArray(): array
    {
        return array(
            'schemaName'    => $this->schema_name,
            'schemaVersion' => $this->schema_version,
        );
    }
}
