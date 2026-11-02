import { useState } from 'react'
import {
  configurePushApi,
  removePushApi,
  setPushApiEnabled,
  type PushApiStatus,
} from '../../../../../api/adminUiBridge'
import { Button } from '../../../../shared/form/button/Button'
import { ToggleSwitch } from '../../../../shared/controls/toggle-switch/ToggleSwitch'
import { maskSecret } from '../../../../shared/secret-mask/maskSecret'
import { useNotifications } from '../../../../shared/notifications/notification-provider/notificationContext'
import '../../../../shared/status-badge/status-badge.css'
import './push-synchronization-configuration-section.css'

type PushSynchronizationConfigurationSectionProps = {
  status: PushApiStatus
  hasApiKey: boolean
  hasConnectedSuccessfullyAtLeastOnce: boolean
  onStatusUpdated: (status: PushApiStatus) => void
}

export function PushSynchronizationConfigurationSection({
  status,
  hasApiKey,
  hasConnectedSuccessfullyAtLeastOnce,
  onStatusUpdated,
}: PushSynchronizationConfigurationSectionProps) {
  const [isWorking, setIsWorking] = useState(false)
  const { showError, showSuccess } = useNotifications()

  if (!hasConnectedSuccessfullyAtLeastOnce) {
    return null
  }

  async function runAction(action: () => Promise<PushApiStatus>, successMessage: string) {
    setIsWorking(true)

    try {
      const nextStatus = await action()

      onStatusUpdated(nextStatus)
      showSuccess(successMessage)
    } catch (error) {
      showError(error instanceof Error ? error.message : 'Push API request failed.')
    } finally {
      setIsWorking(false)
    }
  }

  return (
    <section className="veratag-panel veratag-push-synchronization-configuration-section">
      <div className="veratag-panel__header">
        <h2 className="veratag-panel__title">Push Synchronization API Key</h2>

        {status.configured ? (
          <label
            className="veratag-push-synchronization-configuration-section__toggle-label"
            htmlFor="veratag-push-api-enabled-toggle"
          >
            <ToggleSwitch
              checked={status.enabled}
              disabled={isWorking}
              inputId="veratag-push-api-enabled-toggle"
              onCheckedChange={(nextEnabled) =>
                void runAction(
                  () => setPushApiEnabled(nextEnabled),
                  nextEnabled ? 'Push synchronization enabled.' : 'Push synchronization disabled.',
                )
              }
            />

            <span>{status.enabled ? 'Enabled' : 'Disabled'}</span>
          </label>
        ) : (
          <span className="veratag-status-badge veratag-status-badge--warning">Not configured</span>
        )}
      </div>

      <div className="veratag-panel__body">
        <p className="veratag-panel__text">
          A Push Synchronization API Key is unique to a specific vera[tag] WordPress plugin
          installation and allows the vera[tag] platform to securely push data directly to the
          plugin, enabling live updates when channel data changes. Unlike pull synchronization, push
          synchronization is not mandatory. Therefore, configuring a Push Synchronization API Key is
          optional.
        </p>

        <p className="veratag-panel__text">
          The vera[tag] platform pushes data to this WordPress site at the following URL and
          authenticates each push synchronization request using the Push Synchronization API Key
          (this URL must therefore remain publicly accessible):
        </p>

        <code className="veratag-push-synchronization-configuration-section__mono-code">
          {status.receivingUrl}
        </code>
        {status.configured ? (
          <>
            <p className="veratag-panel__text">
              The vera[tag] platform authenticates each push synchronization request using the
              following Push Synchronization API Key:{' '}
            </p>
            <code className="veratag-push-synchronization-configuration-section__mono-code">
              {status.hasCredential
                ? status.secretStub
                  ? maskSecret(status.secretStub)
                  : 'Configured'
                : 'Not configured'}
            </code>

            <p className="veratag-panel__text">
              You can enable or disable push synchronization at any time using the toggle above. To
              completely remove the push synchronization configuration for this channel, use the
              button below. You can configure and enable push synchronization again at any time.
            </p>
          </>
        ) : null}
        <div className="veratag-settings-actions veratag-settings-actions--end">
          {status.configured ? (
            <Button
              buttonText="Remove push synchronization"
              color="red"
              disabled={isWorking}
              onClick={() =>
                void runAction(removePushApi, 'Push synchronization successfully removed..')
              }
            />
          ) : (
            <Button
              buttonText={isWorking ? 'Configuring...' : 'Configure push synchronization'}
              disabled={isWorking || !hasApiKey}
              onClick={() =>
                void runAction(configurePushApi, 'Push synchronization successfully configured.')
              }
            />
          )}
        </div>
      </div>
    </section>
  )
}
