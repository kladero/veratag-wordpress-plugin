<?php

namespace VeraTag\Settings\OptionValues;

final class PullSynchronizationAttemptStatus
{
    public function __construct(
        private readonly bool $ok,
        private readonly int $http_response_code,
        private readonly string $http_status_text,
        private readonly string $status_source,
        private readonly string $message,
        private readonly string $updated_at,
        private readonly ?int $received_disclosure_entry_count
    ) {
    }

    public static function createInitial(): self
    {
        return new self(false, 0, '', SynchronizationStatusSource::LOCAL_PLUGIN, '', '', null);
    }

    public static function fromArray(array $value): self
    {
        $ok                              = isset($value['ok']) ? true === $value['ok'] : false;
        $http_response_code              = isset($value['httpResponseCode']) ? (int) $value['httpResponseCode'] : 0;
        $http_status_text                = isset($value['httpStatusText']) && is_string($value['httpStatusText']) ? $value['httpStatusText'] : '';
        $status_source                   = isset($value['statusSource']) && is_string($value['statusSource'])
            ? SynchronizationStatusSource::normalize($value['statusSource'])
            : SynchronizationStatusSource::LOCAL_PLUGIN;
        $message                         = isset($value['message']) && is_string($value['message']) ? $value['message'] : '';
        $updated_at                      = isset($value['updated_at']) && is_string($value['updated_at']) ? $value['updated_at'] : '';
        $received_disclosure_entry_count = isset($value['receivedDisclosureEntryCount']) && is_numeric($value['receivedDisclosureEntryCount'])
            ? (int) $value['receivedDisclosureEntryCount']
            : null;

        return new self($ok, $http_response_code, $http_status_text, $status_source, $message, $updated_at, $received_disclosure_entry_count);
    }

    public function toArray(): array
    {
        return array(
            'ok'                           => $this->ok,
            'httpResponseCode'             => $this->http_response_code,
            'httpStatusText'               => $this->http_status_text,
            'statusSource'                 => $this->status_source,
            'message'                      => $this->message,
            'updated_at'                   => $this->updated_at,
            'receivedDisclosureEntryCount' => $this->received_disclosure_entry_count,
        );
    }
}
