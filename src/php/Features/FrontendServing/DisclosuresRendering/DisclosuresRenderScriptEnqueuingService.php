<?php

namespace VeraTag\Features\FrontendServing\DisclosuresRendering;

use VeraTag\Persistence\Disclosures\Repositories\RenderScriptsRepository;

/** Enqueues the distinct render-script files required by the placements embedded on the current page. */
final class DisclosuresRenderScriptEnqueuingService
{
    public function __construct(
        private readonly RenderScriptsRepository $render_scripts_repository,
        private readonly RenderScriptFileWriter $render_script_file_writer
    ) {
    }

    /** @param list<array{file_name: string, required_version: string}> $required_scripts */
    public function enqueueRequiredScripts(array $required_scripts): void
    {
        if (! $this->shouldInjectFrontendAssets()) {
            return;
        }

        $seen = array();
        foreach ($required_scripts as $required) {
            $file_name        = isset($required['file_name']) ? trim((string) $required['file_name']) : '';
            $required_version = isset($required['required_version']) ? trim((string) $required['required_version']) : '';
            if ('' === $file_name || '' === $required_version || isset($seen[ $file_name ])) {
                continue;
            }
            $seen[ $file_name ] = true;

            $cached = $this->render_scripts_repository->findByFileName($file_name);
            if (! is_array($cached) || (string) ( $cached['script_version'] ?? '' ) !== $required_version) {
                continue;
            }
            $script_url = $this->render_script_file_writer->getPublicUrl($file_name);
            if (null === $script_url) {
                continue;
            }

            $modified = $this->render_script_file_writer->getFileModifiedTime($file_name);
            $handle   = 'veratag-render-script-' . substr(md5($file_name), 0, 16);
            wp_register_script($handle, $script_url, array(), null === $modified ? $required_version : (string) $modified, true);
            wp_enqueue_script($handle);
        }
    }

    private function shouldInjectFrontendAssets(): bool
    {
        if (is_admin()) {
            return false;
        }
        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            return false;
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return false;
        }
        if (function_exists('wp_is_json_request') && wp_is_json_request()) {
            return false;
        }
        return true;
    }
}
