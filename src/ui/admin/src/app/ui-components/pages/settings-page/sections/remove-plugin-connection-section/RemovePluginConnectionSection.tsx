import { useState } from 'react'
import { dialogService } from '../../../../shared/overlays/dialog/dialogService'
import { unregisterFromChannel } from '../../../../../api/adminUiBridge'
import { Alert } from '../../../../shared/alert/Alert'
import { Button } from '../../../../shared/form/button/Button'
import { useNotifications } from '../../../../shared/notifications/notification-provider/notificationContext'

/** Long enough for the success toast to actually be visible before the
 * reload below replaces the page — the earlier bug here was reloading
 * immediately, which raced the toast off-screen before it ever rendered. */
const RELOAD_DELAY_AFTER_SUCCESS_TOAST_MS = 1500

type UnregisterWarning = {
  message: string
}

export function RemovePluginConnectionSection() {
  const [isUnregistering, setIsUnregistering] = useState(false)
  const [warning, setWarning] = useState<UnregisterWarning | null>(null)
  const { showError, showSuccess } = useNotifications()

  async function handleUnregister() {
    const confirmed = await dialogService.openDialog({
      title: 'Remove plugin connection?',
      content:
        'This removes the connection and push synchronization configuration, and permanently deletes all locally stored vera[tag] disclosures and API keys on this WordPress site. The plugin will be reset. This cannot be undone. Data on the vera[tag] platform is not deleted, except for its push configuration.',
      confirmLabel: 'Remove connection',
      cancelLabel: 'Keep connection',
      tone: 'danger',
    })
    if (!confirmed) {
      return
    }

    setIsUnregistering(true)
    setWarning(null)

    try {
      const result = await unregisterFromChannel()
      // Local state is always reset by this point regardless of
      // backendRemovalWarning (see unregisterFromChannel's own contract), so
      // this page is stale either way and reloading is still correct. A
      // full success is a quick toast + short-delayed reload; a backend
      // warning is shown as a persistent alert with its own "Continue"
      // button instead, since that outcome is worth the user deliberately
      // acknowledging before the page disappears.
      if (result.backendRemovalWarning === null) {
        showSuccess(
          'Successfully removed the connection between this vera[tag] WordPress plugin and the vera[tag] platform, including the push synchronization configuration on the vera[tag] platform.',
        )
        window.setTimeout(() => window.location.reload(), RELOAD_DELAY_AFTER_SUCCESS_TOAST_MS)
      } else {
        setWarning({
          message:
            'The connection was successfully removed from this vera[tag] WordPress plugin, but the vera[tag] platform did not confirm the corresponding removal. No data was removed from the vera[tag] platform. ' +
            `${result.backendRemovalWarning} You may need to remove the channel connection manually in the vera[tag] dashboard.`,
        })
      }
    } catch (error) {
      showError(
        error instanceof Error
          ? error.message
          : 'Failed to remove the connection from this vera[tag] WordPress plugin.',
      )
      setIsUnregistering(false)
    }
  }

  return (
    <section className="veratag-panel veratag-panel--red">
      <div className="veratag-panel__header">
        <h2 className="veratag-panel__title">Remove Plugin Connection to the vera[tag] Platform</h2>
      </div>
      <div className="veratag-panel__body">
        <p className="veratag-panel__text">
          Remove the connection between this vera[tag] WordPress plugin and the vera[tag] platform.
          This also removes the push synchronization configuration. All locally stored vera[tag]
          data, including AI content disclosures and API keys, will be deleted from this WordPress
          site, and the vera[tag] WordPress plugin will be reset to its initial state, as if it had
          just been installed.
        </p>

        <p className="veratag-panel__text veratag-panel__text--note">
          <strong>Note:</strong> Except for the push synchronization configuration, this action does
          not delete any data from the vera[tag] platform. It only removes this WordPress site's
          local connection and associated data. To reconnect the vera[tag] WordPress plugin,
          configure a new Pull Synchronization API Key.
        </p>

        {warning ? (
          <Alert label="Partially removed" tone="warning">
            {warning.message}
          </Alert>
        ) : null}
        <div className="veratag-settings-actions veratag-settings-actions--end">
          {warning ? (
            <Button
              buttonText="Continue"
              color="red"
              onClick={() => window.location.reload()}
              variant="outlined"
            />
          ) : (
            <Button
              buttonText={isUnregistering ? 'Removing...' : 'Remove plugin connection'}
              color="red"
              disabled={isUnregistering}
              onClick={() => void handleUnregister()}
              variant="outlined"
            />
          )}
        </div>
      </div>
    </section>
  )
}
