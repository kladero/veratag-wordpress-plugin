<?php

namespace VeraTag\Features\PullSynchronization;

/** Per-placement render-script identity from CmsPluginRenderScriptDto. */
final class PullSynchronizationApiRenderScriptDto
{
    public function __construct(private readonly string $file_name, private readonly string $script_release_version)
    {
    }
    public static function fromArray(array $v): self
    {
        return new self(isset($v['fileName']) && is_string($v['fileName']) ? trim($v['fileName']) : '', isset($v['scriptReleaseVersion']) && is_string($v['scriptReleaseVersion']) ? trim($v['scriptReleaseVersion']) : '');
    }
    public function getFileName(): string
    {
        return $this->file_name;
    }
    public function getScriptReleaseVersion(): string
    {
        return $this->script_release_version;
    }
    public function toArray(): array
    {
        return array(
            'fileName'             => $this->file_name,
            'scriptReleaseVersion' => $this->script_release_version,
        );
    }
}
