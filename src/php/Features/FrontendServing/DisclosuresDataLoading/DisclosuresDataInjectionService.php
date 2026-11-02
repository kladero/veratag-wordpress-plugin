<?php

namespace VeraTag\Features\FrontendServing\DisclosuresDataLoading;

/**
 * Injects the DisclosureData payload (see DisclosuresDataLoadingService's
 * class docs) under id="disclosure-data" — the exact element id
 * veratag-webpage-content-disclosure-renderer's bundled JS reads via
 * DISCLOSURE_DATA_ELEMENT_ID (src/constants/constants.ts) and
 * loadDisclosureDataFromDocument() (src/disclosure-data/disclosure-data.loader.ts).
 * A different id here means the renderer silently finds nothing and never
 * runs.
 */
final class DisclosuresDataInjectionService
{
    private const DISCLOSURE_DATA_ELEMENT_ID = 'disclosure-data';

    public function injectDisclosureDataScript(array $disclosure_data): void
    {
        if (array() === $disclosure_data) {
            return;
        }

        $encoded_disclosure_data = wp_json_encode(
            $disclosure_data,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        if (false === $encoded_disclosure_data) {
            return;
        }

        printf(
            "<script type=\"application/json\" id=\"%s\">%s</script>\n",
            esc_attr(self::DISCLOSURE_DATA_ELEMENT_ID),
            // JSON_HEX_* flags above escape < > & ' " so the payload cannot close the script tag.
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            $encoded_disclosure_data
        );
    }
}
