<?php

namespace VeraTag\Settings\OptionValues;

/**
 * Mirrors PullSynchronizationAttemptStatus but for the render-script
 * download check run by RenderScriptSyncScheduler — same ok/http/message/
 * updated_at shape, plus which script (name + version) this status refers
 * to, so the admin UI can show "script X vY — last checked at ..." without
 * a separate lookup.
 */
final class RenderScriptFetchLatestFetchStatus
{
    public function __construct(
        private readonly bool $ok,
        private readonly int $http_response_code,
        private readonly string $http_status_text,
        private readonly string $status_source,
        private readonly string $message,
        private readonly string $updated_at,
        private readonly string $script_file_name,
        private readonly string $script_version,
        private readonly bool $http_call_made
    ) {
    }

    public static function createInitial(): self
    {
        return new self(false, 0, '', SynchronizationStatusSource::LOCAL_PLUGIN, '', '', '', '', false);
    }

    public static function fromArray(array $value): self
    {
        $ok                 = isset($value['ok']) ? true === $value['ok'] : false;
        $http_response_code = isset($value['httpResponseCode']) ? (int) $value['httpResponseCode'] : 0;
        $http_status_text   = isset($value['httpStatusText']) && is_string($value['httpStatusText']) ? $value['httpStatusText'] : '';
        $status_source      = isset($value['statusSource']) && is_string($value['statusSource'])
            ? SynchronizationStatusSource::normalize($value['statusSource'])
            : SynchronizationStatusSource::LOCAL_PLUGIN;
        $message            = isset($value['message']) && is_string($value['message']) ? $value['message'] : '';
        $updated_at         = isset($value['updated_at']) && is_string($value['updated_at']) ? $value['updated_at'] : '';
        $script_file_name   = isset($value['scriptFileName']) && is_string($value['scriptFileName']) ? $value['scriptFileName'] : '';
        $script_version     = isset($value['scriptVersion']) && is_string($value['scriptVersion']) ? $value['scriptVersion'] : '';
        $http_call_made     = isset($value['httpCallMade']) ? true === $value['httpCallMade'] : false;

        return new self($ok, $http_response_code, $http_status_text, $status_source, $message, $updated_at, $script_file_name, $script_version, $http_call_made);
    }

    public function toArray(): array
    {
        return array(
            'ok'               => $this->ok,
            'httpResponseCode' => $this->http_response_code,
            'httpStatusText'   => $this->http_status_text,
            'statusSource'     => $this->status_source,
            'message'          => $this->message,
            'updated_at'       => $this->updated_at,
            'scriptFileName'   => $this->script_file_name,
            'scriptVersion'    => $this->script_version,
            'httpCallMade'     => $this->http_call_made,
        );
    }
}
