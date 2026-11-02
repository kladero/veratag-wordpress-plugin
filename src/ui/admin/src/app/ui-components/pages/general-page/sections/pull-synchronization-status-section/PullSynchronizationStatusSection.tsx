import { type PullSynchronizationAttemptStatus } from '../../../../../api/adminUiBridge'
import { loadVeratagAdminConfig } from '../../../../../config/loadVeratagAdminConfig'
import './pull-synchronization-status-section.css'

type PullSynchronizationStatusSectionProps = {
  lastPullSynchronizationStatus: PullSynchronizationAttemptStatus
}

export function PullSynchronizationStatusSection({
  lastPullSynchronizationStatus,
}: PullSynchronizationStatusSectionProps) {
  const config = loadVeratagAdminConfig()
  const tone = lastPullSynchronizationStatus.ok ? 'success' : 'failure'
  const latestSyncDate = formatSyncDate(lastPullSynchronizationStatus.updated_at)
  const syncMessage = resolveSyncMessage(lastPullSynchronizationStatus)

  return (
    <div className="veratag-pull-synchronization-status-section">
      <section
        className={`veratag-pull-synchronization-status-panel veratag-pull-synchronization-status-panel--${tone}`}
      >
        <div
          className={`veratag-pull-synchronization-status-panel__icon-shell veratag-pull-synchronization-status-panel__icon-shell--${tone}`}
        >
          <img
            alt=""
            aria-hidden="true"
            className="veratag-pull-synchronization-status-panel__icon"
            src={tone === 'failure' ? config.statusIcons.failure : config.statusIcons.success}
          />
        </div>

        <div className="veratag-pull-synchronization-status-panel__content">
          <div className="veratag-pull-synchronization-status-panel__topline">
            <span className="veratag-pull-synchronization-status-panel__label">
              Latest Pull Synchronization Status
            </span>
            <span className="veratag-pull-synchronization-status-panel__time">
              {latestSyncDate}
            </span>
          </div>

          <div className="veratag-pull-synchronization-status-panel__message">{syncMessage}</div>
        </div>
      </section>
    </div>
  )
}

function formatSyncDate(value: string): string {
  if (value.trim() === '') {
    return 'Not synced yet'
  }

  const parsedDate = new Date(value)

  if (Number.isNaN(parsedDate.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat(undefined, {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(parsedDate)
}

function resolveSyncMessage(
  lastPullSynchronizationStatus: PullSynchronizationAttemptStatus,
): string {
  if (lastPullSynchronizationStatus.ok) {
    return lastPullSynchronizationStatus.message.trim() !== ''
      ? lastPullSynchronizationStatus.message
      : 'Periodic pull synchronization completed successfully.'
  }

  if (
    lastPullSynchronizationStatus.statusSource === 'remote-http-protocol' &&
    lastPullSynchronizationStatus.httpResponseCode > 0
  ) {
    return `HTTP Error ${lastPullSynchronizationStatus.httpResponseCode}: ${
      lastPullSynchronizationStatus.httpStatusText.trim() !== ''
        ? lastPullSynchronizationStatus.httpStatusText
        : 'Unknown response'
    }`
  }

  if (lastPullSynchronizationStatus.message.trim() !== '') {
    return lastPullSynchronizationStatus.message
  }

  return 'Unknown Error'
}
