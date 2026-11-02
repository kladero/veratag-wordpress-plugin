import { useEffect, useState, type ChangeEvent } from 'react'
import {
  configureVerificationFile,
  removeVerificationFile,
  type VerificationFileState,
} from '../../../../../api/adminUiBridge'
import { Button } from '../../../../shared/form/button/Button'
import { InputText } from '../../../../shared/form/input-text/InputText'
import { useNotifications } from '../../../../shared/notifications/notification-provider/notificationContext'
import '../../../../shared/status-badge/status-badge.css'
import './verification-file-configuration-section.css'

type VerificationFileConfigurationSectionProps = {
  verificationFile: VerificationFileState
  onVerificationFileUpdated: (verificationFile: VerificationFileState) => void
}

export function VerificationFileConfigurationSection({
  verificationFile,
  onVerificationFileUpdated,
}: VerificationFileConfigurationSectionProps) {
  const [verificationFileTokenError, setVerificationFileTokenError] = useState('')
  const [verificationFileTokenValue, setVerificationFileTokenValue] = useState(
    verificationFile.token,
  )
  const [isMutatingVerificationFile, setIsMutatingVerificationFile] = useState(false)
  const { showError, showSuccess } = useNotifications()

  useEffect(() => {
    const synchronizationFrame = window.requestAnimationFrame(() => {
      setVerificationFileTokenValue(verificationFile.token)
      setVerificationFileTokenError('')
    })

    return () => {
      window.cancelAnimationFrame(synchronizationFrame)
    }
  }, [verificationFile.token])

  function handleVerificationFileTokenChange(event: ChangeEvent<HTMLInputElement>) {
    setVerificationFileTokenValue(event.target.value)
    setVerificationFileTokenError('')
  }

  function resolveVerificationFileTokenError(token: string): string {
    if (token.trim() === '') {
      return 'Verification Token cant be empty'
    }

    if (token.length > 60) {
      return 'Token cant be that long'
    }

    return ''
  }

  async function handleConfigureVerificationFile() {
    const tokenError = resolveVerificationFileTokenError(verificationFileTokenValue)

    if (tokenError !== '') {
      setVerificationFileTokenError(tokenError)
      return
    }

    setIsMutatingVerificationFile(true)

    try {
      const response = await configureVerificationFile(verificationFileTokenValue)

      setVerificationFileTokenValue(response.verificationFile.token)
      onVerificationFileUpdated(response.verificationFile)
      showSuccess('Verification file configured.')
    } catch (error) {
      const message =
        error instanceof Error ? error.message : 'Failed to configure verification file.'
      showError(message)
    } finally {
      setIsMutatingVerificationFile(false)
    }
  }

  async function handleRemoveVerificationFile() {
    setIsMutatingVerificationFile(true)

    try {
      const response = await removeVerificationFile()

      setVerificationFileTokenValue(response.verificationFile.token)
      onVerificationFileUpdated(response.verificationFile)
      showSuccess('Verification file removed.')
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Failed to remove verification file.'
      showError(message)
    } finally {
      setIsMutatingVerificationFile(false)
    }
  }

  return (
    <section className="veratag-panel">
      <div className="veratag-panel__header">
        <h2 className="veratag-panel__title">Verification File</h2>
        <span
          className={`veratag-status-badge ${
            verificationFile.exists
              ? 'veratag-status-badge--success'
              : 'veratag-status-badge--error'
          }`}
        >
          {verificationFile.exists ? 'Hosted' : 'Action required'}
        </span>
      </div>

      <div className="veratag-panel__body">
        {verificationFile.exists ? (
          <>
            <p className="veratag-panel__text">Your verification file is successfully hosted at:</p>
            <code className="veratag-verification-file-configuration-section__mono-code">
              {verificationFile.url}
            </code>
            <p className="veratag-panel__text veratag-panel__text--note">
              This verification file must remain publicly accessible at this URL because the
              vera[tag] platform periodically retrieves it to verify that you continue to have
              administrative access to the WordPress site.
            </p>
            <p className="veratag-panel__text">
              You can remove the verification file using the button below, for example if you need
              to replace it with a file containing a new verification token.
            </p>

            <div className="veratag-settings-actions veratag-settings-actions--end">
              <Button
                buttonText={isMutatingVerificationFile ? 'Removing...' : 'Remove verification file'}
                color="red"
                disabled={isMutatingVerificationFile}
                onClick={handleRemoveVerificationFile}
                variant="solid"
              />
            </div>
          </>
        ) : (
          <>
            <p className="veratag-panel__text">
              To connect this WordPress site to the vera[tag] platform, vera[tag] must verify that
              you have administrative access to the site at the URL specified when the channel was
              created in the vera[tag] dashboard. To verify the site, host a verification file at{' '}
              <code>/.well-known/veratag-verify-[TOKEN].txt</code>, replacing <code>[TOKEN]</code>{' '}
              with the verification token provided for the channel. The file must also contain the
              same token as plain text. You can obtain the verification token from the channel
              settings in the vera[tag] dashboard.
            </p>
            <p className="veratag-panel__text">
              To host the verification file, enter your verification token below and let the
              vera[tag] WordPress plugin automatically create the required file under{' '}
              <code>/.well-known/</code>. Alternatively, you can create and host the verification
              file manually.
            </p>
            <InputText
              errorText={verificationFileTokenError}
              id="veratag-verification-file-token"
              label="Verification token"
              onChange={handleVerificationFileTokenChange}
              placeholder="Enter the verification token"
              value={verificationFileTokenValue}
            />

            <p className="veratag-panel__text veratag-panel__text--note">
              <strong>Note:</strong> The verification file must remain publicly accessible at this
              URL because the vera[tag] platform periodically retrieves it to verify that you
              continue to have administrative access to the WordPress site.
            </p>

            <div className="veratag-settings-actions veratag-settings-actions--end">
              <Button
                buttonText={isMutatingVerificationFile ? 'Configuring...' : 'Configure'}
                disabled={isMutatingVerificationFile}
                onClick={handleConfigureVerificationFile}
              />
            </div>
          </>
        )}
      </div>
    </section>
  )
}
