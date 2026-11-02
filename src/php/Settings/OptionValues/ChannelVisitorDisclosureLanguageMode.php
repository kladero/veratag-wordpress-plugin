<?php

namespace VeraTag\Settings\OptionValues;

/**
 * Mirrors the backend's ChannelVisitorDisclosureLanguageModeKey unique_name
 * values exactly (see channel_visitor_disclosure_language_modes on the
 * backend) — this is the backend-ground-truth mode, synced down via
 * /pull-synchronization and editable from here, distinct from (and replacing) the
 * plugin's earlier local-only DisclosureSelectionLanguageMode setting.
 */
final class ChannelVisitorDisclosureLanguageMode
{
    public const VISITOR_PREFERRED = 'visitor_preferred';
    public const SERVER_PREFERRED  = 'server_preferred';

    private function __construct()
    {
    }
}
