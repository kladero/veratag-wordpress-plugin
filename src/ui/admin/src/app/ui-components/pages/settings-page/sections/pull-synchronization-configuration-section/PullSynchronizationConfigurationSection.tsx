import { useState, type ChangeEvent } from 'react'
import { updateAdminSettings } from '../../../../../api/adminUiBridge'
import { Button } from '../../../../shared/form/button/Button'
import { InputText } from '../../../../shared/form/input-text/InputText'
import { maskSecret } from '../../../../shared/secret-mask/maskSecret'
import { useNotifications } from '../../../../shared/notifications/notification-provider/notificationContext'
import '../../../../shared/status-badge/status-badge.css'
import './pull-synchronization-configuration-section.css'

type PullSynchronizationConfigurationSectionProps = {
  apiKey: string
  onApiKeySaved: (apiKey: string) => void
}

export function PullSynchronizationConfigurationSection({
  apiKey,
  onApiKeySaved,
}: PullSynchronizationConfigurationSectionProps) {
  const [apiKeyError, setApiKeyError] = useState('')
  const [apiKeyOverride, setApiKeyOverride] = useState<string | null>(null)
  const [isSaving, setIsSaving] = useState(false)
  const [isShowingMaskedValue, setIsShowingMaskedValue] = useState(apiKey.trim() !== '')
  const { showError, showSuccess } = useNotifications()
  const apiKeyValue = apiKeyOverride ?? apiKey
  const hasStoredApiKey = apiKey.trim() !== ''
  const displayedApiKeyValue = isShowingMaskedValue ? maskSecret(apiKeyValue) : apiKeyValue

  function handleApiKeyChange(event: ChangeEvent<HTMLInputElement>) {
    setApiKeyOverride(event.target.value)
    setApiKeyError('')
  }

  function handleChangeApiKeyClicked() {
    setIsShowingMaskedValue(false)
    setApiKeyOverride('')
  }

  function resolveApiKeyError(apiKeyInput: string): string {
    if (apiKeyInput.length > 200) {
      return 'Pull Synchronization API Key cannot be longer than 200 characters.'
    }

    return ''
  }

  async function handleSaveApiKey() {
    const nextApiKeyError = resolveApiKeyError(apiKeyValue)

    if (nextApiKeyError !== '') {
      setApiKeyError(nextApiKeyError)
      return
    }

    setIsSaving(true)

    try {
      const response = await updateAdminSettings({
        apiKey: apiKeyValue,
      })

      setApiKeyOverride(response.apiKey)
      setIsShowingMaskedValue(response.apiKey.trim() !== '')
      onApiKeySaved(response.apiKey)
      showSuccess('Pull Synchronization API Key saved.')
    } catch (error) {
      const message =
        error instanceof Error ? error.message : 'Failed to save Pull Synchronization API Key.'
      showError(message)
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <section className="veratag-panel">
      <div className="veratag-panel__header">
        <h2 className="veratag-panel__title">Pull Synchronization API Key</h2>
        <span
          className={`veratag-status-badge ${
            hasStoredApiKey ? 'veratag-status-badge--success' : 'veratag-status-badge--error'
          }`}
        >
          {hasStoredApiKey ? 'Configured' : 'Action required'}
        </span>
      </div>

      <div className="veratag-panel__body">
        <p className="veratag-panel__text">
          A Pull Synchronization API key is unique to a specific channel and allows the vera[tag]
          WordPress plugin to securely retrieve the channel’s data from the vera[tag] platform. Once
          the site has been successfully verified using the verification file above, the API key is
          issued for that channel in the vera[tag] dashboard.
        </p>

        <p className="veratag-panel__text">
          Configuring this API key is required because pull synchronization is the default method
          used by the vera[tag] WordPress plugin to retrieve data from the vera[tag] platform.{' '}
        </p>

        <InputText
          errorText={apiKeyError}
          id="veratag-api-key"
          label="Pull Synchronization API Key"
          onChange={handleApiKeyChange}
          placeholder="Enter your Pull Synchronization API Key"
          readOnly={isShowingMaskedValue}
          value={displayedApiKeyValue}
        />

        <p className="veratag-panel__text">
          Once you save the Pull Synchronization API key using the button below, the vera[tag]
          WordPress plugin will begin synchronizing with the vera[tag] platform at regular intervals
          to retrieve data for the corresponding channel.
        </p>

        <div className="veratag-settings-actions veratag-settings-actions--end">
          {isShowingMaskedValue ? (
            <Button
              buttonText="Change API key"
              variant="outlined"
              onClick={handleChangeApiKeyClicked}
            />
          ) : (
            <Button
              buttonText={isSaving ? 'Saving API key...' : 'Save API key'}
              disabled={isSaving}
              onClick={handleSaveApiKey}
            />
          )}
        </div>
      </div>
    </section>
  )
}
