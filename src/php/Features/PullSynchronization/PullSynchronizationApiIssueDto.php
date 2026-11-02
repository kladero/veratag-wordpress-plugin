<?php

namespace VeraTag\Features\PullSynchronization;

final class PullSynchronizationApiIssueDto
{
    public function __construct(private readonly ?int $id, private readonly string $reason, private readonly string $impact, private readonly string $message, private readonly ?string $detected_at)
    {
    }
    public static function fromArray(array $v): self
    {
        return new self(isset($v['id']) && is_numeric($v['id']) ? (int) $v['id'] : null, isset($v['reason']) && is_string($v['reason']) ? trim($v['reason']) : '', isset($v['impact']) && is_string($v['impact']) ? trim($v['impact']) : '', isset($v['message']) && is_string($v['message']) ? trim($v['message']) : '', isset($v['detectedAt']) && is_string($v['detectedAt']) ? trim($v['detectedAt']) : null);
    }
    public function getReason(): string
    {
        return $this->reason;
    }
    public function getImpact(): string
    {
        return $this->impact;
    }
    public function getMessage(): string
    {
        return $this->message;
    }
    public function getDetectedAt(): ?string
    {
        return $this->detected_at;
    }
    public function toArray(): array
    {
        return array(
            'id'         => $this->id,
            'reason'     => $this->reason,
            'impact'     => $this->impact,
            'message'    => $this->message,
            'detectedAt' => $this->detected_at,
        );
    }
}
