<?php

namespace VeraTag\Features\RenderScriptSync;

/** 45-minute backstop retry for every render script currently required by cached placements. */
final class RenderScriptSyncScheduler
{
    private const CRON_HOOK              = 'veratag_render_script_sync_event';
    private const CRON_SCHEDULE          = 'veratag_every_45_minutes';
    private const FETCH_INTERVAL_SECONDS = 2700;

    public function __construct(private readonly RenderScriptReconciliationService $reconciliation_service)
    {
    }
    public function extendCronSchedules(array $schedules): array
    {
        $schedules[ self::CRON_SCHEDULE ] = array(
            'interval' => self::FETCH_INTERVAL_SECONDS,
            'display'  => 'Every 45 minutes',
        );
        return $schedules;
    }
    public function ensureSchedule(): void
    {
        if (false === wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + self::FETCH_INTERVAL_SECONDS, self::CRON_SCHEDULE, self::CRON_HOOK);
        }
    }
    public function clearSchedule(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }
    public function getCronHook(): string
    {
        return self::CRON_HOOK;
    }
    public function run(): void
    {
        $this->reconciliation_service->reconcileAllRequired();
    }
}
