import { loadVeratagAdminConfig } from '../../../../../config/loadVeratagAdminConfig'
import { Button } from '../../../../shared/form/button/Button'
import './setup-required-section.css'

export function SetupRequiredSection() {
  const config = loadVeratagAdminConfig()

  function handleOpenSettings() {
    if (config.settingsPageUrl === '') {
      return
    }

    window.location.href = config.settingsPageUrl
  }

  return (
    <section className="veratag-panel veratag-setup-required-section">
      <div className="veratag-panel__header">
        <h2 className="veratag-panel__title">Setup Required: Connect to the vera[tag] Platform</h2>
      </div>

      <div className="veratag-panel__body">
        <p className="veratag-panel__text">
          The vera[tag] WordPress plugin is not fully configured because no API key for connecting
          to the vera[tag] platform has been configured. To complete the connection between your
          WordPress site and the vera[tag] platform, follow the setup instructions on the vera[tag]
          dashboard and configure the required API key in the{' '}
          <a href={config.settingsPageUrl}>Settings</a> tab.
        </p>

        <div className="veratag-settings-actions veratag-settings-actions--end">
          <Button
            buttonText="Open Settings"
            disabled={config.settingsPageUrl === ''}
            onClick={handleOpenSettings}
          />
        </div>
      </div>
    </section>
  )
}
