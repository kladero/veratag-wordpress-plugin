<?php

namespace VeraTag\Persistence\Disclosures;

use VeraTag\Features\FrontendServing\DisclosuresRendering\RenderScriptFileWriter;
use VeraTag\Persistence\Disclosures\Repositories\ConfigurationsRepository;
use VeraTag\Persistence\Disclosures\Repositories\ContentItemsRepository;
use VeraTag\Persistence\Disclosures\Repositories\PlacementsRepository;
use VeraTag\Persistence\Disclosures\Repositories\RenderScriptFetchStatusesRepository;
use VeraTag\Persistence\Disclosures\Repositories\RenderScriptsRepository;
use VeraTag\Persistence\Disclosures\Repositories\TextTemplatesRepository;

/** Reference-counted cleanup shared by pull reconciliation and incremental pushes. */
final class DictionaryGarbageCollector
{
    public function __construct(
        private readonly ConfigurationsRepository $configurations_repository,
        private readonly PlacementsRepository $placements_repository,
        private readonly TextTemplatesRepository $text_templates_repository,
        private readonly RenderScriptsRepository $render_scripts_repository,
        private readonly RenderScriptFetchStatusesRepository $render_script_fetch_statuses_repository,
        private readonly ContentItemsRepository $content_items_repository,
        private readonly RenderScriptFileWriter $render_script_file_writer
    ) {
    }

    public function collectGarbageForConfigurationRef(string $configuration_ref): void
    {
        $configuration_ref = trim($configuration_ref);
        if ('' === $configuration_ref || $this->content_items_repository->countByConfigurationRef($configuration_ref) > 0) {
            return;
        }
        $row = $this->configurations_repository->findByRef($configuration_ref);
        if (null === $row) {
            return;
        }
        $placements = $this->decodeStringArray($row['placement_refs'] ?? '[]');
        $slots      = $this->decodeAssoc($row['slots'] ?? '{}');
        $templates  = ConfigurationsRepository::extractTemplateRefs($slots);
        $this->configurations_repository->deleteByRef($configuration_ref);
        $this->collectGarbageForDetachedRefs($placements, $templates);
    }

    public function collectGarbageForDetachedRefs(array $dropped_placement_refs, array $dropped_template_refs): void
    {
        foreach (array_values(array_unique(array_filter($dropped_placement_refs, 'is_string'))) as $placement_ref) {
            $this->collectPlacementIfOrphaned($placement_ref);
        }
        foreach (array_values(array_unique(array_filter($dropped_template_refs, 'is_string'))) as $template_ref) {
            if ($this->configurations_repository->countReferencingTemplateRef($template_ref) < 1) {
                $this->text_templates_repository->deleteByRef($template_ref);
            }
        }
    }

    private function collectPlacementIfOrphaned(string $placement_ref): void
    {
        if ($this->configurations_repository->countReferencingPlacementRef($placement_ref) > 0) {
            return;
        }
        $row = $this->placements_repository->findByRef($placement_ref);
        if (null === $row) {
            return;
        }
        $file_name = isset($row['render_script_file_name']) ? (string) $row['render_script_file_name'] : '';
        $this->placements_repository->deleteByRef($placement_ref);
        if ('' !== $file_name && $this->placements_repository->countByRenderScriptFileName($file_name) < 1) {
            $this->render_scripts_repository->deleteByFileName($file_name);
            $this->render_script_fetch_statuses_repository->deleteByFileName($file_name);
            $this->render_script_file_writer->delete($file_name);
        }
    }

    private function decodeStringArray(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : array();
        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : array();
    }
    private function decodeAssoc(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : array();
        return is_array($decoded) ? $decoded : array();
    }
}
