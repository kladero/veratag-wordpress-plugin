import { useState } from 'react'
import {
  loadPullSynchronizationStatus,
  runPullSynchronization,
  type PullSynchronizationStatusResponse,
  type PullSynchronizationAttemptStatus,
} from '../../../../../api/adminUiBridge'
import { loadVeratagAdminConfig } from '../../../../../config/loadVeratagAdminConfig'
import { Alert } from '../../../../shared/alert/Alert'
import { Button } from '../../../../shared/form/button/Button'
import { useNotifications } from '../../../../shared/notifications/notification-provider/notificationContext'
import '../../../../shared/status-badge/status-badge.css'
import './latest-pull-synchronization-section.css'

type LatestPullSynchronizationSectionProps = {
  hasStoredApiKey: boolean
  lastPullSynchronizationStatus: PullSynchronizationAttemptStatus
  nextPullSynchronizationAt: string | null
  onPullSynchronizationStatusUpdated: (
    pullSynchronizationStatusResponse: PullSynchronizationStatusResponse,
  ) => void
}

export function LatestPullSynchronizationSection({
  hasStoredApiKey,
  lastPullSynchronizationStatus,
  nextPullSynchronizationAt,
  onPullSynchronizationStatusUpdated,
}: LatestPullSynchronizationSectionProps) {
  const [isRunningFetch, setIsRunningFetch] = useState(false)
  const config = loadVeratagAdminConfig()
  const { showError, showSuccess } = useNotifications()

  function formatFetchDateTime(value: string | null): string {
    if (typeof value !== 'string' || value.trim() === '') {
      return 'Not available'
    }

    const parsedDate = new Date(value)

    if (Number.isNaN(parsedDate.getTime())) {
      return value
    }

    return new Intl.DateTimeFormat(undefined, {
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      month: 'short',
      year: 'numeric',
    }).format(parsedDate)
  }

  async function handleFetchNow() {
    setIsRunningFetch(true)

    try {
      const response = await runPullSynchronization()
      onPullSynchronizationStatusUpdated({
        entryCount: response.entryCount,
        lastPullSynchronizationStatus: response.lastPullSynchronizationStatus,
        nextPullSynchronizationAt: response.nextPullSynchronizationAt,
        storedContentCount: response.storedContentCount,
        storedContentSynchronizedAt: response.storedContentSynchronizedAt,
      })
      showSuccess(response.message)
    } catch (error) {
      try {
        const refreshedStatus = await loadPullSynchronizationStatus()
        onPullSynchronizationStatusUpdated(refreshedStatus)
      } catch {
        // Keep the original sync error as the user-facing failure.
      }

      const message = error instanceof Error ? error.message : 'Failed to run pull synchronization.'
      showError(message)
    } finally {
      setIsRunningFetch(false)
    }
  }

  const latestFetchValue =
    typeof lastPullSynchronizationStatus.updated_at === 'string' &&
    lastPullSynchronizationStatus.updated_at !== ''
      ? formatFetchDateTime(lastPullSynchronizationStatus.updated_at)
      : 'Not synced yet'
  const fetchStatusValue =
    lastPullSynchronizationStatus.message.trim() !== ''
      ? lastPullSynchronizationStatus.message
      : lastPullSynchronizationStatus.ok === false &&
          lastPullSynchronizationStatus.statusSource === 'remote-http-protocol' &&
          lastPullSynchronizationStatus.httpResponseCode > 0
        ? `HTTP Error ${lastPullSynchronizationStatus.httpResponseCode}: ${
            lastPullSynchronizationStatus.httpStatusText.trim() !== ''
              ? lastPullSynchronizationStatus.httpStatusText
              : 'Unknown response'
          }`
        : lastPullSynchronizationStatus.ok === true
          ? 'Healthy'
          : 'Unknown Error'
  const entriesValue =
    lastPullSynchronizationStatus.receivedDisclosureEntryCount === null
      ? 'N/A'
      : String(lastPullSynchronizationStatus.receivedDisclosureEntryCount)
  const nextFetchValue =
    nextPullSynchronizationAt === null
      ? 'Not scheduled'
      : formatFetchDateTime(nextPullSynchronizationAt)
  const headerBadgeTone =
    !hasStoredApiKey || lastPullSynchronizationStatus.ok === false ? 'error' : 'success'
  const headerBadgeText = !hasStoredApiKey
    ? 'API key missing'
    : lastPullSynchronizationStatus.ok === false
      ? 'Error'
      : 'Healthy'

  return (
    <section className="veratag-panel veratag-latest-pull-synchronization-section">
      <div className="veratag-panel__header">
        <h2 className="veratag-panel__title">Latest Pull Synchronization Status</h2>
        <span className={`veratag-status-badge veratag-status-badge--${headerBadgeTone}`}>
          {headerBadgeText}
        </span>
      </div>

      <div className="veratag-panel__body">
        <p className="veratag-panel__text">
          The vera[tag] WordPress plugin regularly retrieves data from the vera[tag] platform (e.g.,
          AI content disclosures) using the Pull Synchronization API Key to keep its local database
          synchronized.
        </p>
        {!hasStoredApiKey ? (
          <Alert label="Error" tone="error">
            A Pull Synchronization API Key is required to automatically synchronize the vera[tag]
            WordPress plugin with the vera[tag] platform. Please configure a Pull Synchronization
            API Key in the{' '}
            <a
              className="veratag-latest-pull-synchronization-section__settings-link"
              href={config.settingsPageUrl}
            >
              Settings
            </a>{' '}
            tab.
          </Alert>
        ) : null}

        {hasStoredApiKey ? (
          <>
            <p className="veratag-panel__text">
              The table below shows the latest pull synchronization status.
            </p>

            <div className="veratag-latest-pull-synchronization-section__summary">
              <table className="veratag-latest-pull-synchronization-section__table">
                <thead>
                  <tr>
                    <th scope="col">Latest pull</th>
                    <th scope="col">Status</th>
                    <th scope="col">Content items received</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>
                      <span className="veratag-latest-pull-synchronization-section__meta-value">
                        {latestFetchValue}
                      </span>
                    </td>
                    <td>
                      <span className="veratag-latest-pull-synchronization-section__meta-value">
                        {fetchStatusValue}
                      </span>
                    </td>
                    <td>
                      <span className="veratag-latest-pull-synchronization-section__meta-value">
                        {entriesValue}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <p className="veratag-panel__text">
              The vera[tag] WordPress plugin will perform the next pull synchronization at{' '}
              <strong>{nextFetchValue}</strong>. You can also start a pull synchronization manually
              using the button below.
            </p>

            <div className="veratag-settings-actions veratag-settings-actions--end">
              <Button
                buttonText={isRunningFetch ? 'Synchronizing...' : 'Synchronize now'}
                disabled={isRunningFetch}
                onClick={handleFetchNow}
              />
            </div>
          </>
        ) : null}
      </div>
    </section>
  )
}
