<?php

namespace VeraTag\Persistence\Disclosures;

use VeraTag\Persistence\Disclosures\Repositories\ConfigurationsRepository;
use VeraTag\Persistence\Disclosures\Repositories\ContentItemIssuesRepository;
use VeraTag\Persistence\Disclosures\Repositories\ContentItemsRepository;
use VeraTag\Persistence\Disclosures\Repositories\PlacementsRepository;
use VeraTag\Persistence\Disclosures\Repositories\TextTemplatesRepository;

/**
 * Computes and stores the full merged issue set for one content item —
 * mirrors the backend's own {@code DisclosureIssueAggregator}: a content
 * item's "issues" are its own content-tier issues plus every issue on
 * whichever configuration it's assigned to, plus every issue on that
 * configuration's attached placements and referenced text templates.
 *
 * {@code content_item_issues} is always fully recomputed and rewritten by
 * {@see reconcileOne} rather than being a tier-tagged incremental table —
 * every tier's current issues are always fully known locally (each
 * dictionary table already stores its own {@code issues} JSON column) at
 * the moment any trigger below fires, so there is nothing to gain from
 * partial patching and it would only risk drift between tiers.
 *
 * Trigger points, mirroring the backend's own fan-out for the equivalent
 * reasons:
 * <ul>
 *   <li>{@see reconcileOne} — a content item's own row changed (its issues,
 *       or which configuration it's assigned to) — called from
 *       {@code WEB_CONTENT_UPSERT} and the periodic full sync.</li>
 *   <li>{@see reconcileAllByConfigurationRef} — a configuration's own
 *       issues changed — called from {@code WEB_CONFIGURATION_UPSERT}.</li>
 *   <li>{@see reconcileAllByPlacementRef} — a placement's issues changed —
 *       called from {@code WEB_PLACEMENT_UPSERT}, fanning out through
 *       every configuration that attaches it.</li>
 *   <li>{@see reconcileAllByTemplateRef} — a text template's issues
 *       changed — called from {@code WEB_TEXT_TEMPLATE_UPSERT}, fanning out
 *       through every configuration whose slots reference it.</li>
 * </ul>
 */
final class ContentItemIssueAggregationService
{
    public function __construct(
        private readonly ContentItemsRepository $content_items_repository,
        private readonly ContentItemIssuesRepository $content_item_issues_repository,
        private readonly ConfigurationsRepository $configurations_repository,
        private readonly PlacementsRepository $placements_repository,
        private readonly TextTemplatesRepository $text_templates_repository
    ) {
    }

    /**
     * Recomputes and rewrites {@code content_item_id}'s full merged issue
     * set — {@code own_content_issues} is this content item's own
     * content-tier issues, in the raw {@code {reason, impact, message,
     * detectedAt}} shape the backend sends; everything else is resolved
     * locally from {@code configuration_ref}.
     */
    public function reconcileOne(int $content_item_id, ?string $configuration_ref, array $own_content_issues): bool|\WP_Error
    {
        // Reject malformed transport/fixture entries before they become visible issues.
        $merged = array_values(array_filter($own_content_issues, fn (mixed $issue): bool => $this->isValidIssue($issue)));
        foreach ($this->resolveConfigurationTierIssues($configuration_ref) as $issue) {
            $merged[] = $issue;
        }
        $deleted = $this->content_item_issues_repository->deleteByContentItemIds(array( $content_item_id ));
        if (is_wp_error($deleted)) {
            return $deleted;
        }
        foreach ($merged as $issue) {
            $inserted = $this->content_item_issues_repository->insert(
                $content_item_id,
                (string) ( $issue['reason'] ?? '' ),
                (string) ( $issue['impact'] ?? '' ),
                (string) ( $issue['message'] ?? '' ),
                $this->toMysqlDateTime(isset($issue['detectedAt']) && is_string($issue['detectedAt']) ? $issue['detectedAt'] : null)
            );
            if (is_wp_error($inserted)) {
                return $inserted;
            }
        }
        return true;
    }

    /**
     * Re-fans {@see reconcileOne} out to every content item currently
     * assigned to {@code configuration_ref} — preserving each one's own
     * already-stored content-tier issues, since a configuration-only push
     * carries no information about those.
     */
    public function reconcileAllByConfigurationRef(string $configuration_ref): bool|\WP_Error
    {
        foreach ($this->content_items_repository->findByConfigurationRef($configuration_ref) as $row) {
            $result = $this->reconcileOne(
                (int) $row['id'],
                $configuration_ref,
                $this->ownContentTierIssues((int) $row['id'])
            );
            if (is_wp_error($result)) {
                return $result;
            }
        }
        return true;
    }

    /** Re-fans out through every configuration that attaches {@code placement_ref}. */
    public function reconcileAllByPlacementRef(string $placement_ref): bool|\WP_Error
    {
        foreach ($this->configurations_repository->findRefsReferencingPlacementRef($placement_ref) as $configuration_ref) {
            $result = $this->reconcileAllByConfigurationRef($configuration_ref);
            if (is_wp_error($result)) {
                return $result;
            }
        }
        return true;
    }

    /** Re-fans out through every configuration whose slots reference {@code template_ref}. */
    public function reconcileAllByTemplateRef(string $template_ref): bool|\WP_Error
    {
        foreach ($this->configurations_repository->findRefsReferencingTemplateRef($template_ref) as $configuration_ref) {
            $result = $this->reconcileAllByConfigurationRef($configuration_ref);
            if (is_wp_error($result)) {
                return $result;
            }
        }
        return true;
    }

    /**
     * This content item's own content-tier issues — read back out of
     * {@code content_item_issues} in the same raw shape {@see reconcileOne}
     * accepts, for a caller that must preserve them across a reconcile it
     * triggers for a different reason (e.g. the configuration changed, not
     * the content item itself). This is only ever correct to call BEFORE
     * {@see reconcileOne} has run for any OTHER trigger in between — see
     * {@see reconcileAllByConfigurationRef}, its only caller, which reads
     * it immediately before overwriting the same row.
     */
    private function ownContentTierIssues(int $content_item_id): array
    {
        $rows = $this->content_item_issues_repository->findByContentItemIds(array( $content_item_id ));
        return array_values(
            array_filter(
                array_map(
                    fn (array $row): ?array => $this->isContentTierReason((string) $row['reason'])
                        ? array(
                            'reason'     => (string) $row['reason'],
                            'impact'     => (string) $row['impact'],
                            'message'    => (string) $row['message'],
                            'detectedAt' => (string) $row['detected_at'],
                        )
                        : null,
                    $rows
                )
            )
        );
    }

    /**
     * Distinguishes a content-tier reason from a configuration/placement/
     * template-tier one already merged into a previous
     * {@see reconcileOne} write — content-tier reasons are always prefixed
     * {@code web-content-disclosure-}, mirroring the backend's
     * {@code WebContentDisclosureIssueReasonKey} unique names; every other
     * tier's reasons use their own distinct prefixes
     * ({@code disclosure-text-template-}, {@code disclosure-placement-}).
     * This is a convention, not a stored flag — see the class docs for why
     * a flag column was intentionally not added.
     */
    private function isContentTierReason(string $reason): bool
    {
        return str_starts_with($reason, 'web-content-disclosure-');
    }

    /** This configuration's own issues, plus every attached placement's and referenced template's — empty for {@code null} (unconfigured content). */
    private function resolveConfigurationTierIssues(?string $configuration_ref): array
    {
        if (null === $configuration_ref || '' === trim($configuration_ref)) {
            return array();
        }

        $configuration = $this->configurations_repository->findByRef($configuration_ref);
        if (null === $configuration) {
            return array();
        }

        $issues = $this->decodeIssuesArray($configuration['issues'] ?? '[]');

        foreach ($this->decodeStringArray($configuration['placement_refs'] ?? '[]') as $placement_ref) {
            $placement = $this->placements_repository->findByRef($placement_ref);
            if (null !== $placement) {
                array_push($issues, ...$this->decodeIssuesArray($placement['issues'] ?? '[]'));
            }
        }

        $template_refs = ConfigurationsRepository::extractTemplateRefs($this->decodeAssoc($configuration['slots'] ?? '{}'));
        foreach ($template_refs as $template_ref) {
            $template = $this->text_templates_repository->findByRef($template_ref);
            if (null !== $template) {
                array_push($issues, ...$this->decodeIssuesArray($template['issues'] ?? '[]'));
            }
        }

        return $issues;
    }

    private function decodeIssuesArray(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : array();
        return is_array($decoded) ? array_values(array_filter($decoded, fn (mixed $issue): bool => $this->isValidIssue($issue))) : array();
    }

    /** Never persist empty/malformed issue objects or impacts unsupported by the backend contract. */
    private function isValidIssue(mixed $issue): bool
    {
        if (! is_array($issue)) {
            return false;
        }
        foreach (array( 'reason', 'impact', 'message' ) as $field) {
            if (! isset($issue[ $field ]) || ! is_string($issue[ $field ]) || '' === trim($issue[ $field ])) {
                return false;
            }
        }
        return in_array($issue['impact'], array( 'action_required', 'action_recommended' ), true);
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

    private function toMysqlDateTime(?string $iso8601_value): string
    {
        if (null === $iso8601_value || '' === trim($iso8601_value)) {
            return current_time('mysql', true);
        }
        $timestamp = strtotime($iso8601_value);
        return false === $timestamp ? current_time('mysql', true) : gmdate('Y-m-d H:i:s', $timestamp);
    }
}
