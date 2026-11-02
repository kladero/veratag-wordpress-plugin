<?php

namespace VeraTag\Features\AdminUiBridge\PullSynchronizationBridge;

final class PullSynchronizationBridgeResponse
{
    public function __construct(
        private readonly bool $ok,
        private readonly string $message,
        private readonly array $last_pull_synchronization_status,
        private readonly ?int $entry_count = null,
        private readonly int $stored_content_count = 0,
        private readonly ?string $stored_content_synchronized_at = null,
        private readonly ?string $next_pull_synchronization_at = null,
        private readonly ?int $batch_id = null
    ) {
    }

    public function toArray(): array
    {
        return array(
            'ok'                            => $this->ok,
            'message'                       => $this->message,
            'lastPullSynchronizationStatus' => $this->last_pull_synchronization_status,
            'entryCount'                    => $this->entry_count,
            'storedContentCount'            => $this->stored_content_count,
            'storedContentSynchronizedAt'   => $this->stored_content_synchronized_at,
            'nextPullSynchronizationAt'     => $this->next_pull_synchronization_at,
            'batchId'                       => $this->batch_id,
        );
    }
}
