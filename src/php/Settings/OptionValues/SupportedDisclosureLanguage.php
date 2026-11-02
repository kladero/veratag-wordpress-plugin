<?php

namespace VeraTag\Settings\OptionValues;

/**
 * Mirrors the backend's LanguageKey enum's bcp47 values exactly (the fixed
 * set of languages a channel's fallback disclosure language may be chosen
 * from) — used to populate the fallback-language dropdown in the admin UI
 * and to validate a value before sending it to the backend.
 */
final class SupportedDisclosureLanguage
{
    public const BCP47_VALUES = array(
        'bg',
        'hr',
        'cs',
        'da',
        'nl',
        'en',
        'et',
        'fi',
        'fr',
        'de',
        'el',
        'hu',
        'ga',
        'it',
        'lv',
        'lt',
        'mt',
        'pl',
        'pt',
        'ro',
        'sk',
        'sl',
        'es',
        'sv',
    );

    private function __construct()
    {
    }

    public static function isSupported(string $bcp47): bool
    {
        return in_array(strtolower(trim($bcp47)), self::BCP47_VALUES, true);
    }
}
