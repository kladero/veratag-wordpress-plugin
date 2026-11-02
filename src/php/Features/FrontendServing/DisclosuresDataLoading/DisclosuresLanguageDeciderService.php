<?php

namespace VeraTag\Features\FrontendServing\DisclosuresDataLoading;

use VeraTag\Settings\OptionValues\ChannelVisitorDisclosureLanguageMode;
use WP_Error;

final class DisclosuresLanguageDeciderService
{
    /**
     * @param string $disclosure_language_mode One of {@see ChannelVisitorDisclosureLanguageMode}'s
     *   constants, synced from the backend through /pull-synchronization. Fallback
     *   selection happens later, after these candidates have been tried
     *   against the concrete entry's available disclosure languages.
     */
    public function resolveCurrentPageLanguageCandidates(string $disclosure_language_mode): array
    {
        $languages = ChannelVisitorDisclosureLanguageMode::VISITOR_PREFERRED === $disclosure_language_mode
            ? $this->resolveUserPreferredLanguages()
            : array( $this->resolveServerPreferredLanguage() );

        return $this->normalizeLanguageCandidates($languages);
    }

    public function resolveCurrentPageLanguage(string $disclosure_language_mode, string $fallback_language): string
    {
        $languages = $this->resolveCurrentPageLanguageCandidates($disclosure_language_mode);

        if (array() !== $languages) {
            return $languages[0];
        }

        return $fallback_language;
    }

    /**
     * @param string $fallback_language The backend-ground-truth fallback
     *   language bcp47 code (GlobalSettings::getChannelVisitorDisclosureLanguageSettings()
     *   -\>getFallbackLanguageBcp47()), never a hardcoded default.
     */
    public function selectDisclosureLanguage(
        array $disclosures_by_language,
        string $page_language,
        string $fallback_language,
        ?callable $is_language_usable = null
    ): string|WP_Error {
        return $this->selectDisclosureLanguageFromCandidates($disclosures_by_language, array( $page_language ), $fallback_language, $is_language_usable);
    }

    /**
     * @param callable(array):bool|null $is_language_usable Called with a
     *   matched language's own `$disclosures_by_language[$language]` value
     *   (its full entry, not just `slots`) once a candidate is found to
     *   exist at all. A candidate language that exists but fails this check
     *   is skipped exactly like one that doesn't exist, so the walk moves on
     *   to the next preferred language and finally to the fallback — rather
     *   than stopping at the first language that merely has a `byLanguage`
     *   entry for *some* slot, which is not the same as having everything
     *   the caller actually needs from it (see
     *   DisclosuresDataLoadingService::buildDisclosureInformation, which
     *   requires three specific, non-blank slots — a language present for
     *   only some of them is not usable, and should not block falling
     *   through to a language that has all of them). Omitted (null),
     *   existence alone is the only requirement, the historical behavior.
     */
    public function selectDisclosureLanguageFromCandidates(
        array $disclosures_by_language,
        array $language_candidates,
        string $fallback_language,
        ?callable $is_language_usable = null
    ): string|WP_Error {
        if (array() === $disclosures_by_language) {
            return new WP_Error(
                'veratag_frontend_serving_missing_disclosure_languages',
                'Entry disclosures must define at least one language.',
                array( 'status' => 500 )
            );
        }

        // The channel's fallback language must ALWAYS be supported by an
        // entry: an entry without a usable fallback-language translation is
        // never served, not even in a visitor-preferred language it does
        // support. The caller
        // (DisclosuresDataLoadingService::prepareContentRecordForFrontend)
        // treats any WP_Error here as "skip this entry", so this is the
        // intended, deliberate "no match" outcome, not an exceptional one.
        $selected_fallback_language = $this->findUsableDisclosureLanguage($disclosures_by_language, $fallback_language, $is_language_usable);

        if ('' === $selected_fallback_language) {
            return new WP_Error(
                'veratag_frontend_serving_missing_fallback_language',
                'Entry does not support the configured fallback language, so it is not served.',
                array( 'status' => 404 )
            );
        }

        foreach ($this->normalizeLanguageCandidates($language_candidates) as $language_candidate) {
            $selected_language = $this->findUsableDisclosureLanguage($disclosures_by_language, $language_candidate, $is_language_usable);

            if ('' !== $selected_language) {
                return $selected_language;
            }
        }

        return $selected_fallback_language;
    }

    private function resolveServerPreferredLanguage(): string
    {
        $language = null;

        if (function_exists('pll_current_language')) {
            $language = pll_current_language('locale');

            if (! is_string($language) || '' === trim($language)) {
                $language = pll_current_language();
            }
        } elseif (has_filter('wpml_current_language')) {
            $language = apply_filters('wpml_current_language', null);
        } else {
            $language = get_locale();
        }

        if (! is_string($language) || '' === trim($language)) {
            return '';
        }

        return trim($language);
    }

    private function resolveUserPreferredLanguages(): array
    {
        $accept_language_header = isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT_LANGUAGE']))
            : '';

        if (! is_string($accept_language_header) || '' === trim($accept_language_header)) {
            return array();
        }

        return $this->extractPreferredLanguagesFromAcceptLanguageHeader($accept_language_header);
    }

    private function normalizeLanguageCandidates(array $languages): array
    {
        $candidates = array();

        foreach ($languages as $language) {
            if (! is_string($language)) {
                continue;
            }

            $trimmed_language = trim($language);

            if ('' === $trimmed_language || in_array($trimmed_language, $candidates, true)) {
                continue;
            }

            $candidates[] = $trimmed_language;
        }

        return $candidates;
    }

    private function extractBaseLanguage(string $language): string
    {
        $trimmed = trim($language);

        if ('' === $trimmed) {
            return '';
        }

        $separator_offset = strpos($trimmed, '_');

        if (false === $separator_offset) {
            $separator_offset = strpos($trimmed, '-');
        }

        if (false === $separator_offset) {
            return $trimmed;
        }

        return substr($trimmed, 0, $separator_offset);
    }

    /**
     * @param callable(array):bool|null $is_language_usable See
     *   {@see selectDisclosureLanguageFromCandidates}. When given, a
     *   matched language that fails the check is passed over rather than
     *   returned, so a later, less-preferred match (e.g. the base language,
     *   or a differently-cased available key) still gets a chance.
     */
    private function findUsableDisclosureLanguage(
        array $disclosures_by_language,
        string $requested_language,
        ?callable $is_language_usable
    ): string {
        foreach ($this->findDisclosureLanguageCandidates($disclosures_by_language, $requested_language, true) as $matched_language) {
            if (null === $is_language_usable || $is_language_usable($disclosures_by_language[ $matched_language ])) {
                return $matched_language;
            }
        }

        return '';
    }

    /**
     * Every available language key in {@code $disclosures_by_language} that
     * could satisfy {@code $requested_language}, most-specific first: an
     * exact match, then a case/separator-insensitive match, then (if
     * {@code $allow_base_language_match}) the same two checks against the
     * requested language's base (e.g. {@code de} for {@code de-AT}).
     * Returns every match rather than just the first so a caller with a
     * usability predicate can skip over a match that exists but isn't
     * actually usable and still fall through to a later one, instead of
     * stopping at whichever matches first regardless of usability.
     *
     * @return list<string>
     */
    private function findDisclosureLanguageCandidates(array $disclosures_by_language, string $requested_language, bool $allow_base_language_match): array
    {
        $trimmed_requested_language = trim($requested_language);

        if ('' === $trimmed_requested_language) {
            return array();
        }

        $matches = array();

        if (isset($disclosures_by_language[ $trimmed_requested_language ])) {
            $matches[] = $trimmed_requested_language;
        }

        $requested_language_key = $this->normalizeLanguageLookupKey($trimmed_requested_language);

        foreach (array_keys($disclosures_by_language) as $available_language) {
            if (
                is_string($available_language)
                && $requested_language_key === $this->normalizeLanguageLookupKey($available_language)
                && ! in_array($available_language, $matches, true)
            ) {
                $matches[] = $available_language;
            }
        }

        if (! $allow_base_language_match) {
            return $matches;
        }

        $requested_base_language = $this->extractBaseLanguage($trimmed_requested_language);

        if ('' === $requested_base_language || $requested_base_language === $trimmed_requested_language) {
            return $matches;
        }

        foreach ($this->findDisclosureLanguageCandidates($disclosures_by_language, $requested_base_language, false) as $base_match) {
            if (! in_array($base_match, $matches, true)) {
                $matches[] = $base_match;
            }
        }

        $requested_base_language_key = $this->normalizeLanguageLookupKey($requested_base_language);

        foreach (array_keys($disclosures_by_language) as $available_language) {
            if (
                is_string($available_language)
                && $requested_base_language_key === $this->normalizeLanguageLookupKey($this->extractBaseLanguage($available_language))
                && ! in_array($available_language, $matches, true)
            ) {
                $matches[] = $available_language;
            }
        }

        return $matches;
    }

    private function normalizeLanguageLookupKey(string $language): string
    {
        return strtolower(str_replace('-', '_', trim($language)));
    }

    private function extractPreferredLanguageFromAcceptLanguageHeader(string $header): string
    {
        $languages = $this->extractPreferredLanguagesFromAcceptLanguageHeader($header);

        return array() === $languages ? '' : $languages[0];
    }

    private function extractPreferredLanguagesFromAcceptLanguageHeader(string $header): array
    {
        $weighted_languages = array();

        foreach (explode(',', $header) as $index => $raw_language_range) {
            if (! is_string($raw_language_range)) {
                continue;
            }

            $language_range = trim($raw_language_range);
            if ('' === $language_range) {
                continue;
            }

            $segments     = explode(';', $language_range);
            $language_tag = $this->normalizeLanguageTag(array_shift($segments));

            if ('' === $language_tag) {
                continue;
            }

            $quality = 1.0;
            foreach ($segments as $segment) {
                $trimmed_segment = trim($segment);

                if (0 !== stripos($trimmed_segment, 'q=')) {
                    continue;
                }

                $quality_value = substr($trimmed_segment, 2);

                if (! is_numeric($quality_value)) {
                    continue;
                }

                $quality = (float) $quality_value;
                break;
            }

            if ($quality <= 0.0) {
                continue;
            }

            $weighted_languages[] = array(
                'language' => $language_tag,
                'quality'  => $quality,
                'index'    => $index,
            );
        }

        if (array() === $weighted_languages) {
            return array();
        }

        usort(
            $weighted_languages,
            static function (array $left, array $right): int {
                if ($left['quality'] === $right['quality']) {
                    return $left['index'] <=> $right['index'];
                }

                return $left['quality'] > $right['quality'] ? -1 : 1;
            }
        );

        $languages = array();

        foreach ($weighted_languages as $weighted_language) {
            $language = $weighted_language['language'] ?? null;

            if (is_string($language) && '' !== $language && ! in_array($language, $languages, true)) {
                $languages[] = $language;
            }
        }

        return $languages;
    }

    private function normalizeLanguageTag(mixed $language_tag): string
    {
        if (! is_string($language_tag)) {
            return '';
        }

        $trimmed = trim($language_tag);

        if ('' === $trimmed || '*' === $trimmed) {
            return '';
        }

        return str_replace('-', '_', $trimmed);
    }
}
