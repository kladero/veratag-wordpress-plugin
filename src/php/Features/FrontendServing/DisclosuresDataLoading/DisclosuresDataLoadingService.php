<?php

namespace VeraTag\Features\FrontendServing\DisclosuresDataLoading;

use VeraTag\Settings\Services\GlobalSettings;
use WP_Error;

/**
 * Loads the disclosed content on the currently-requested page, in the
 * visitor's resolved language, in the exact shape the bundled renderer
 * expects (see veratag-webpage-content-disclosure-renderer's
 * src/disclosure-data/disclosure-data.model.ts: DisclosureData ->
 * contentRecords[] -> {contentIdentification, disclosure: {type,
 * disclosureInformation, disclosurePlacements[]}}).
 *
 * The renderer does NOT perform any slot substitution itself — every
 * placement's params tree it receives must already have the resolved text
 * baked in at the exact paths its own schema expects (see
 * DisclosurePlacementConfig in that model file). The backend, by contrast,
 * ships params UNresolved plus a separate slotBindings map (content path ->
 * slot key) specifically to avoid shipping the same text twice (see the backend CmsPluginPlacementEntryDto shape). This class
 * is therefore where that substitution actually happens: for the one
 * language selected for this page/visitor, each placement's slotBindings
 * are walked and the matching resolved slot text
 * (DisclosuresDataQueryService's byLanguage[lang].slots) is written into a
 * copy of that placement's raw params at the bound dot-path — producing
 * exactly the pre-resolved shape the renderer's decoder validates.
 *
 * The language that was selected is shipped alongside the texts, as
 * disclosureInformation.disclosureLanguage (BCP 47), because the renderer needs
 * it to mark the disclosure's language and cannot derive it from the text.
 */
final class DisclosuresDataLoadingService
{
    private const ACRONYM_SLOT_KEY = 'disclosure_acronym';
    private const LABEL_SLOT_KEY   = 'disclosure_label';
    private const MESSAGE_SLOT_KEY = 'disclosure_message';

    public function __construct(
        private readonly DisclosuresDataQueryService $disclosures_data_query_service,
        private readonly DisclosuresLanguageDeciderService $disclosures_language_decider_service,
        private readonly GlobalSettings $global_settings
    ) {
    }

    public function loadForCurrentPage(): array|WP_Error
    {
        return $this->loadCurrentPage()['payload'];
    }

    /**
     * The disclosure payload for the current page together with the render
     * scripts needed to display exactly the placements in that payload. Both
     * come from one pass, so a script is required if and only if a placement
     * that needs it is actually embedded on this page.
     *
     * @return array{payload: array, render_scripts: list<array{file_name: string, required_version: string}>}
     */
    public function loadCurrentPage(): array
    {
        $page_context   = $this->getCurrentPageContext();
        $latest_payload = $this->disclosures_data_query_service->loadLatestPayloadForPageCandidates($page_context['candidates']);
        $entries        = $latest_payload['entries'] ?? array();

        if (! is_array($entries) || array() === $entries) {
            return $this->emptyCurrentPage();
        }

        $visitor_disclosure_language_settings = $this->global_settings->getChannelVisitorDisclosureLanguageSettings();
        $fallback_language                    = $visitor_disclosure_language_settings->getFallbackLanguageBcp47();
        $page_language_candidates             = $this->disclosures_language_decider_service->resolveCurrentPageLanguageCandidates(
            $visitor_disclosure_language_settings->getPreferredMode()
        );
        $prepared_records                     = array();
        $render_scripts                       = array();

        foreach ($entries as $entry) {
            if (! is_array($entry) || ! $this->entryMatchesContext($entry, $page_context['candidates'])) {
                continue;
            }

            $prepared_record = $this->prepareContentRecordForFrontend($entry, $page_language_candidates, $fallback_language);

            if (is_wp_error($prepared_record)) {
                // A malformed stored content item must not prevent valid
                // sibling disclosures from being delivered on this page.
                continue;
            }

            if (null !== $prepared_record) {
                $prepared_records[] = $prepared_record['record'];
                array_push($render_scripts, ...$prepared_record['render_scripts']);
            }
        }

        if (array() === $prepared_records) {
            return $this->emptyCurrentPage();
        }

        return array(
            'payload'        => array(
                'site'           => home_url(),
                'page'           => $page_context['url'],
                'contentRecords' => $prepared_records,
            ),
            'render_scripts' => $render_scripts,
        );
    }

    /** @return array{payload: array, render_scripts: list<array{file_name: string, required_version: string}>} */
    private function emptyCurrentPage(): array
    {
        return array(
            'payload'        => array(),
            'render_scripts' => array(),
        );
    }

    /**
     * Null (not an error) when this entry simply cannot be rendered at all —
     * e.g. no placements, or not even the fallback language has the three
     * fixed slots this entry needs. A candidate language that exists in
     * {@code byLanguage} but is missing one of those slots (e.g. a
     * configuration edited to add a new language that hasn't had every slot
     * filled in for it yet) is treated as not usable, not selected and then
     * discarded — {@code $is_language_usable} below makes the decider skip
     * straight past it to the next preferred language, or to the fallback,
     * instead of stopping at it and failing the whole entry.
     *
     * @return array{record: array, render_scripts: list<array{file_name: string, required_version: string}>}|WP_Error|null
     */
    private function prepareContentRecordForFrontend(array $entry, array $page_language_candidates, string $fallback_language): array|WP_Error|null
    {
        $by_language = $entry['byLanguage'] ?? null;
        $placements  = $entry['placements'] ?? null;

        if (! is_array($by_language) || array() === $by_language || ! is_array($placements) || array() === $placements) {
            return null;
        }

        $selected_language = $this->disclosures_language_decider_service->selectDisclosureLanguageFromCandidates(
            $by_language,
            $page_language_candidates,
            $fallback_language,
            fn (array $language_entry): bool => null !== $this->buildDisclosureInformation(is_array($language_entry['slots'] ?? null) ? $language_entry['slots'] : array())
        );

        if (is_wp_error($selected_language)) {
            return null;
        }

        $selected_slots = $by_language[ $selected_language ]['slots'] ?? null;

        if (! is_array($selected_slots) || array() === $selected_slots) {
            return null;
        }

        $disclosure_information = $this->buildDisclosureInformation($selected_slots);

        if (null === $disclosure_information) {
            return null;
        }

        // The renderer marks the disclosure with this language (the HTML `lang`
        // attribute, WCAG 3.1.2) when it differs from the page's own, so the
        // texts are read in the right voice. Only this class knows which language
        // was selected for this page and visitor.
        $disclosure_information['disclosureLanguage'] = $selected_language;

        $resolved = $this->resolvePlacements($placements, $selected_slots);

        if (array() === $resolved['placements']) {
            return null;
        }

        $content_id       = $entry['contentId'] ?? null;
        $content_type     = $entry['contentType'] ?? null;
        $content_html_tag = $entry['contentHtmlTag'] ?? null;
        $disclosure_type  = $entry['disclosureType'] ?? null;

        if (
            ! is_string($content_id) || '' === trim($content_id)
            || ! is_string($content_type) || '' === trim($content_type)
            || ! is_string($content_html_tag) || '' === trim($content_html_tag)
            || ! is_string($disclosure_type) || '' === trim($disclosure_type)
        ) {
            return new WP_Error(
                'veratag_frontend_serving_missing_entry_identification',
                'Stored entry is missing required content identification fields.',
                array( 'status' => 500 )
            );
        }

        return array(
            'record'         => array(
                'contentIdentification' => array(
                    'contentId'      => $content_id,
                    'contentType'    => $content_type,
                    'contentHtmlTag' => $content_html_tag,
                ),
                'disclosure'            => array(
                    'type'                  => $disclosure_type,
                    'disclosureInformation' => $disclosure_information,
                    'disclosurePlacements'  => $resolved['placements'],
                ),
            ),
            'render_scripts' => $resolved['render_scripts'],
        );
    }

    /** Null if any of the three fixed slots is missing/blank for the selected language — the renderer's decoder requires all three as non-blank strings. */
    private function buildDisclosureInformation(array $selected_slots): ?array
    {
        $acronym = $selected_slots[ self::ACRONYM_SLOT_KEY ] ?? null;
        $label   = $selected_slots[ self::LABEL_SLOT_KEY ] ?? null;
        $message = $selected_slots[ self::MESSAGE_SLOT_KEY ] ?? null;

        if (
            ! is_string($acronym) || '' === trim($acronym)
            || ! is_string($label) || '' === trim($label)
            || ! is_string($message) || '' === trim($message)
        ) {
            return null;
        }

        return array(
            'disclosureAcronym'        => trim($acronym),
            'disclosureLabel'          => trim($label),
            'disclosureGeneralMessage' => trim($message),
        );
    }

    /**
     * For each stored placement, substitutes every slotBindings entry's
     * resolved text into a copy of that placement's raw params at the bound
     * dot-path. A placement with a binding pointing at a slot missing from
     * the selected language is dropped entirely (not partially resolved) —
     * same "all or nothing per placement" rule PlacementConfigResolver
     * already enforces on the backend when it decides whether a placement
     * is shippable at all.
     *
     * Also reports the render script of every placement that is kept, so the
     * caller can load only the scripts the page actually needs.
     *
     * @return array{placements: list<array>, render_scripts: list<array{file_name: string, required_version: string}>}
     */
    private function resolvePlacements(array $placements, array $selected_slots): array
    {
        $resolved       = array();
        $render_scripts = array();

        foreach ($placements as $placement) {
            if (! is_array($placement)) {
                continue;
            }

            $placement_name     = $placement['placementName'] ?? null;
            $schema_name        = $placement['schemaName'] ?? null;
            $schema_version     = $placement['schemaVersion'] ?? null;
            $params             = $placement['params'] ?? null;
            $slot_bindings      = $placement['slotBindings'] ?? null;
            $empty_object_paths = $placement['emptyObjectPaths'] ?? array();

            if (
                ! is_string($placement_name) || '' === trim($placement_name)
                || ! is_string($schema_name) || '' === trim($schema_name)
                || ! is_string($schema_version) || '' === trim($schema_version)
                || ! is_array($params)
                || ! is_array($slot_bindings)
                || ! is_array($empty_object_paths)
            ) {
                continue;
            }

            $resolved_params = $this->substituteSlotBindings($params, $slot_bindings, $selected_slots);

            if (null === $resolved_params) {
                continue;
            }

            $resolved[] = array(
                'placementName'         => $placement_name,
                'placementConfigSchema' => array(
                    'schemaName'    => $schema_name,
                    'schemaVersion' => $schema_version,
                ),
                'params'                => $this->restoreEmptyObjectShapes($resolved_params, $empty_object_paths),
            );

            $render_script = $placement['renderScript'] ?? null;
            $file_name     = is_array($render_script) && is_string($render_script['fileName'] ?? null) ? trim($render_script['fileName']) : '';
            $version       = is_array($render_script) && is_string($render_script['version'] ?? null) ? trim($render_script['version']) : '';

            if ('' !== $file_name && '' !== $version) {
                $render_scripts[] = array(
                    'file_name'        => $file_name,
                    'required_version' => $version,
                );
            }
        }

        return array(
            'placements'     => $resolved,
            'render_scripts' => $render_scripts,
        );
    }

    /** Null if any bound slot is missing from the selected language — the whole placement is then not shippable. */
    private function substituteSlotBindings(array $params, array $slot_bindings, array $selected_slots): ?array
    {
        $params_copy = $params;

        foreach ($slot_bindings as $content_path => $slot_key) {
            if (! is_string($content_path) || '' === trim($content_path) || ! is_string($slot_key)) {
                continue;
            }

            $slot_text = $selected_slots[ $slot_key ] ?? null;

            if (! is_string($slot_text) || '' === trim($slot_text)) {
                return null;
            }

            $this->setAtPath($params_copy, $content_path, $slot_text);
        }

        return $params_copy;
    }

    /** Writes value at a dot-separated path (e.g. "composition.box.label"), creating missing nested arrays as needed. Mirrors PlacementConfigResolver::setAtPath on the backend (the mechanism this plugin now performs instead). */
    private function setAtPath(array &$root, string $dot_path, string $value): void
    {
        $segments = explode('.', $dot_path);
        $leaf     = array_pop($segments);

        $current = &$root;
        foreach ($segments as $segment) {
            if (! isset($current[ $segment ]) || ! is_array($current[ $segment ])) {
                $current[ $segment ] = array();
            }

            $current = &$current[ $segment ];
        }

        $current[ $leaf ] = $value;
    }

    /**
     * json_decode(..., true) collapses an empty JSON object ("{}") and an
     * empty JSON array ("[]") into the same empty PHP array, and
     * wp_json_encode() always re-encodes an empty PHP array as "[]" — so a
     * params tree with an object-typed empty node (e.g. an unused
     * primitive's "content": {}) gets silently corrupted into "[]" on this
     * round trip, which the renderer's schema then rejects. $empty_object_paths
     * (see DisclosuresDataQueryService::findEmptyObjectPaths) names exactly
     * which dot-paths were objects in the original stored JSON; this forces
     * each one that is STILL an empty array after slot substitution back
     * into a (object) cast, which wp_json_encode renders as "{}".
     *
     * A path is skipped if substitution ended up writing a non-empty value
     * there — that would mean the schema itself declared that path both as
     * a slot-binding target and as an originally-empty object, which
     * shouldn't happen, but this stays defensive rather than overwriting
     * real content.
     *
     * @param list<string> $empty_object_paths
     */
    private function restoreEmptyObjectShapes(array $params, array $empty_object_paths): array
    {
        foreach ($empty_object_paths as $path) {
            if (! is_string($path) || '' === trim($path)) {
                continue;
            }

            $this->forceEmptyObjectAtPath($params, $path);
        }

        return $this->repairKnownRendererEmptyObjectParams($params);
    }

    private function forceEmptyObjectAtPath(array &$root, string $dot_path): void
    {
        $segments = explode('.', $dot_path);
        $leaf     = array_pop($segments);

        $current = &$root;
        foreach ($segments as $segment) {
            if (! isset($current[ $segment ]) || ! is_array($current[ $segment ])) {
                return;
            }

            $current = &$current[ $segment ];
        }

        if (isset($current[ $leaf ]) && is_array($current[ $leaf ]) && array() === $current[ $leaf ]) {
            $current[ $leaf ] = new \stdClass();
        }
    }

    private function repairKnownRendererEmptyObjectParams(array $params): array
    {
        foreach ($params as $key => $value) {
            if (array() === $value && is_string($key) && $this->isKnownEmptyObjectParamKey($key)) {
                $params[ $key ] = new \stdClass();
                continue;
            }

            if (is_array($value)) {
                $params[ $key ] = $this->repairKnownRendererEmptyObjectParams($value);
            }
        }

        return $params;
    }

    private function isKnownEmptyObjectParamKey(string $key): bool
    {
        return in_array(
            $key,
            array(
                'content',
                'compositionAccessibility',
                'compositionAppearance',
                'compositionBehaviour',
                'compositionPlacement',
            ),
            true
        );
    }

    private function getCurrentPageContext(): array
    {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '/';
        $url         = $this->buildCurrentPageUrl($request_uri);
        $path        = wp_parse_url($url, PHP_URL_PATH) ?? '/';

        $normalized_candidates = array(
            $this->normalizePageValue($url),
            $this->normalizePageValue($path),
            $this->normalizePageValue(trailingslashit($path)),
            $this->normalizePageValue(untrailingslashit($path)),
        );

        return array(
            'url'        => $url,
            'path'       => $path,
            'candidates' => array_values(
                array_unique(
                    array_filter($normalized_candidates)
                )
            ),
        );
    }

    /**
     * REQUEST_URI is rooted at the public host, not at WordPress's configured
     * home URL. Passing it to home_url() therefore duplicates the home path on
     * installations such as https://example.test/blog. Build the current URL
     * from the configured origin and the public request URI instead.
     */
    private function buildCurrentPageUrl(string $request_uri): string
    {
        $home_url_parts = wp_parse_url(home_url());

        if (
            false === $home_url_parts
            || ! isset($home_url_parts['scheme'])
            || ! isset($home_url_parts['host'])
        ) {
            throw new \RuntimeException('The WordPress home URL must include a scheme and host.');
        }

        $scheme         = (string) $home_url_parts['scheme'];
        $host           = (string) $home_url_parts['host'];
        $port           = isset($home_url_parts['port']) ? ':' . (string) $home_url_parts['port'] : '';
        $path_and_query = str_starts_with($request_uri, '/') ? $request_uri : '/' . $request_uri;

        return "{$scheme}://{$host}{$port}{$path_and_query}";
    }

    private function entryMatchesContext(array $entry, array $candidates): bool
    {
        $page = $entry['page'] ?? '';

        if (! is_string($page) || '' === trim($page)) {
            return false;
        }

        $normalized_page = $this->normalizePageValue($page);

        if ('' === $normalized_page) {
            return false;
        }

        return in_array($normalized_page, $candidates, true);
    }

    private function normalizePageValue(string $value): string
    {
        $trimmed_value = trim($value);

        if ('' === $trimmed_value) {
            return '';
        }

        $parsed = wp_parse_url($trimmed_value);

        if (false === $parsed) {
            $normalized_value = untrailingslashit($trimmed_value);

            return '' === $normalized_value ? '/' : $normalized_value;
        }

        $path = isset($parsed['path']) ? (string) $parsed['path'] : '';

        if (isset($parsed['host'])) {
            $scheme = isset($parsed['scheme']) ? strtolower((string) $parsed['scheme']) : 'http';
            $host   = strtolower((string) $parsed['host']);
            $port   = isset($parsed['port']) ? ':' . $parsed['port'] : '';
            $base   = "{$scheme}://{$host}{$port}{$path}";
        } else {
            $base = '' !== $path ? $path : $trimmed_value;
        }

        $normalized_value = untrailingslashit($base);

        return '' === $normalized_value ? '/' : $normalized_value;
    }
}
