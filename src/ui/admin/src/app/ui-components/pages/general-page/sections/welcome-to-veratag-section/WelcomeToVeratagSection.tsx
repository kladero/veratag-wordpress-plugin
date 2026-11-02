import { loadVeratagAdminConfig } from '../../../../../config/loadVeratagAdminConfig'

export function WelcomeToVeratagSection() {
  const config = loadVeratagAdminConfig()
  const username =
    config.currentUserDisplayName.trim() === '' ? 'there' : config.currentUserDisplayName.trim()

  return (
    <section className="veratag-panel">
      <div className="veratag-panel__header">
        <h1 className="veratag-panel__title">Hey {username}, welcome to vera[tag]!</h1>
      </div>

      <div className="veratag-panel__body">
        <p className="veratag-panel__text">
          Welcome aboard! The vera[tag] WordPress plugin connects your WordPress site to the
          vera[tag] platform to automatically serve language-aware AI content disclosures to your
          visitors.
        </p>
        <span className="veratag-stat-list__label">How the vera[tag] WordPress Plugin Works</span>
        <p className="veratag-panel__text">
          Once you connect the vera[tag] WordPress plugin to the corresponding channel in the
          vera[tag] platform, the plugin continuously synchronizes the AI content disclosures
          registered for that channel with the vera[tag] platform. For synchronization, the
          vera[tag] WordPress plugin either periodically pulls the latest AI content disclosure data
          from the vera[tag] platform or, if push synchronization is configured, receives updates
          from the vera[tag] platform whenever the relevant content disclosures change.
        </p>

        <p className="veratag-panel__text">
          When a visitor requests a page on your site, the vera[tag] WordPress plugin delivers the
          applicable AI content disclosures in either the site’s primary language or the visitor’s
          preferred language, depending on the configured language settings. A disclosure is
          delivered in the selected language only if the applicable disclosure configuration
          provides the required disclosure information in that language.
        </p>
      </div>
    </section>
  )
}
