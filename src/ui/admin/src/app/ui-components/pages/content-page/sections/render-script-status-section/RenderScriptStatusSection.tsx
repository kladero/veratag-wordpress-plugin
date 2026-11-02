import { useState } from 'react'
import {
  loadRenderScriptSyncStatus,
  runRenderScriptSync,
  type RenderScriptSyncStatusResponse,
} from '../../../../../api/adminUiBridge'
import { Button } from '../../../../shared/form/button/Button'
import { useNotifications } from '../../../../shared/notifications/notification-provider/notificationContext'
import '../../../../shared/status-badge/status-badge.css'
import './render-script-status-section.css'

type RenderScriptStatusSectionProps = {
  status: RenderScriptSyncStatusResponse
  onStatusUpdated: (status: RenderScriptSyncStatusResponse) => void
}

function formatDateTime(value: string | null): string {
  if (value === null || value.trim() === '') return 'Not checked yet'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return new Intl.DateTimeFormat(undefined, {
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(date)
}

export function RenderScriptStatusSection({
  status,
  onStatusUpdated,
}: RenderScriptStatusSectionProps) {
  const [isRunningCheck, setIsRunningCheck] = useState(false)
  const { showError, showSuccess } = useNotifications()
  const isHealthy = status.statuses.every(
    (entry) => entry.cachedVersion === entry.requiredVersion && entry.lastFetchOk,
  )
  const badgeText =
    status.statuses.length === 0 ? 'No scripts required' : isHealthy ? 'Healthy' : 'Action required'

  async function handleCheckNow() {
    setIsRunningCheck(true)
    try {
      const response = await runRenderScriptSync()
      onStatusUpdated(response)
      if (response.ok) showSuccess(response.message)
      else showError(response.message)
    } catch (error) {
      try {
        onStatusUpdated(await loadRenderScriptSyncStatus())
      } catch {
        // Preserve the original error below.
      }
      showError(error instanceof Error ? error.message : 'Failed to check render scripts.')
    } finally {
      setIsRunningCheck(false)
    }
  }

  return (
    <section className="veratag-panel veratag-render-script-status-section">
      <header className="veratag-render-script-status-section__header">
        <div className="veratag-render-script-status-section__title-row">
          <h2 className="veratag-render-script-status-section__title">Render Script Overview</h2>
          <span
            className={`veratag-status-badge veratag-status-badge--${isHealthy ? 'success' : 'error'}`}
          >
            {badgeText}
          </span>
        </div>
        <p className="veratag-render-script-status-section__description">
          In addition to storing content items and their AI content disclosures, the vera[tag]
          WordPress plugin also stores the vera[tag] render engine locally. The render engine is
          required to display AI content disclosures to visitors in their browsers, including the
          disclosure designs assigned through the applicable disclosure configurations.
        </p>

        <p className="veratag-render-script-status-section__description">
          The table below shows the render engines currently stored in the local database of the
          vera[tag] WordPress plugin.
        </p>
      </header>

      <div className="veratag-render-script-status-section__summary">
        <table className="veratag-render-script-status-section__table">
          <thead>
            <tr>
              <th scope="col">File</th>
              <th scope="col">Required</th>
              <th scope="col">State</th>
              <th scope="col">Last Pull</th>
              <th scope="col">Status</th>
            </tr>
          </thead>
          <tbody>
            {status.statuses.length === 0 ? (
              <tr>
                <td colSpan={5}>No disclosure designs currently require a render script.</td>
              </tr>
            ) : (
              status.statuses.map((entry) => {
                const current = entry.cachedVersion === entry.requiredVersion && entry.lastFetchOk
                return (
                  <tr key={entry.fileName}>
                    <td>
                      <span className="veratag-render-script-status-section__meta-value">
                        {entry.fileName}
                      </span>
                    </td>
                    <td>{entry.requiredVersion}</td>
                    <td>{entry.cachedVersion ?? 'Missing'}</td>
                    <td>{formatDateTime(entry.lastFetchAt)}</td>
                    <td>
                      <span
                        className={`veratag-status-badge veratag-status-badge--${current ? 'success' : 'error'}`}
                      >
                        {current ? 'Ready' : (entry.lastFetchErrorMessage ?? 'Needs fetch')}
                      </span>
                    </td>
                  </tr>
                )
              })
            )}
          </tbody>
        </table>
      </div>
      <p className="veratag-render-script-status-section__description">
        Render engines normally stay up to date on their own, through pull and push synchronization
        and a background check every 45 minutes. You can also check and fetch any missing or
        outdated render engines immediately using the button below — useful right after a disclosure
        design change, or to retry after a previous fetch failed.
      </p>
      <div className="veratag-settings-actions veratag-settings-actions--end">
        <Button
          buttonText={isRunningCheck ? 'Checking...' : 'Check all render scripts'}
          disabled={isRunningCheck}
          onClick={handleCheckNow}
        />
      </div>
    </section>
  )
}
