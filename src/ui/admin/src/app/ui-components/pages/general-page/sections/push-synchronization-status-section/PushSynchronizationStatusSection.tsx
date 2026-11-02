import type { PushApiStatus } from '../../../../../api/adminUiBridge'
import { loadVeratagAdminConfig } from '../../../../../config/loadVeratagAdminConfig'
import './push-synchronization-status-section.css'

type PushSynchronizationStatusSectionProps = { status: PushApiStatus }

function formatDateTime(value: string | null): string {
  if (value === null || value.trim() === '') return 'Never'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return new Intl.DateTimeFormat(undefined, {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(date)
}

function resolvePushMessage(status: PushApiStatus): string {
  if (!status.configured) {
    return 'Push synchronization has not been configured.'
  }

  if (!status.enabled) {
    return 'Push synchronization is configured but currently disabled.'
  }

  if (status.lastPushAttemptStatus === 'FAILURE') {
    return (
      status.lastPushAttemptStatusMessage ?? 'The most recent push synchronization attempt failed.'
    )
  }

  if (status.lastPushAttemptStatus === 'SUCCESS') {
    return 'The most recent push synchronization attempt completed successfully.'
  }

  return 'Push synchronization is enabled, but no synchronization attempt has been recorded yet.'
}

export function PushSynchronizationStatusSection({
  status,
}: PushSynchronizationStatusSectionProps) {
  const config = loadVeratagAdminConfig()
  const tone =
    status.configured && status.enabled && status.lastPushAttemptStatus !== 'FAILURE'
      ? 'success'
      : 'failure'
  const latestPushDate = formatDateTime(status.lastPushAttemptAt)
  const pushMessage = resolvePushMessage(status)

  return (
    <div className="veratag-push-synchronization-status-section">
      <section
        className={`veratag-push-synchronization-status-panel veratag-push-synchronization-status-panel--${tone}`}
      >
        <div
          className={`veratag-push-synchronization-status-panel__icon-shell veratag-push-synchronization-status-panel__icon-shell--${tone}`}
        >
          <img
            alt=""
            aria-hidden="true"
            className="veratag-push-synchronization-status-panel__icon"
            src={tone === 'failure' ? config.statusIcons.failure : config.statusIcons.success}
          />
        </div>

        <div className="veratag-push-synchronization-status-panel__content">
          <div className="veratag-push-synchronization-status-panel__topline">
            <span className="veratag-push-synchronization-status-panel__label">
              Latest Push Synchronization Status
            </span>
            <span className="veratag-push-synchronization-status-panel__time">
              {latestPushDate}
            </span>
          </div>

          <div className="veratag-push-synchronization-status-panel__message">{pushMessage}</div>
        </div>
      </section>
    </div>
  )
}
