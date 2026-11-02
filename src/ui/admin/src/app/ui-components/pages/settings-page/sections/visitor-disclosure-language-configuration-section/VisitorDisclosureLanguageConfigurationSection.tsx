import { useState } from 'react'
import {
  SUPPORTED_DISCLOSURE_LANGUAGES,
  updateVisitorDisclosureLanguageSettings,
  type ChannelVisitorDisclosureLanguageMode,
  type ChannelVisitorDisclosureLanguageSettings,
  type SupportedDisclosureLanguage,
} from '../../../../../api/adminUiBridge'
import { loadVeratagAdminConfig } from '../../../../../config/loadVeratagAdminConfig'
import { Alert } from '../../../../shared/alert/Alert'
import { Button } from '../../../../shared/form/button/Button'
import { InputSelect } from '../../../../shared/form/input-select/InputSelect'
import { useNotifications } from '../../../../shared/notifications/notification-provider/notificationContext'
import '../../../../../../../../../../assets/flags/flags.css'

type VisitorDisclosureLanguageConfigurationSectionProps = {
  hasApiKey: boolean
  settings: ChannelVisitorDisclosureLanguageSettings
  onSettingsSaved: (settings: ChannelVisitorDisclosureLanguageSettings) => void
}

const LANGUAGE_LABELS: Record<SupportedDisclosureLanguage, string> = {
  bg: 'Bulgarian',
  hr: 'Croatian',
  cs: 'Czech',
  da: 'Danish',
  nl: 'Dutch',
  en: 'English',
  et: 'Estonian',
  fi: 'Finnish',
  fr: 'French',
  de: 'German',
  el: 'Greek',
  hu: 'Hungarian',
  ga: 'Irish',
  it: 'Italian',
  lv: 'Latvian',
  lt: 'Lithuanian',
  mt: 'Maltese',
  pl: 'Polish',
  pt: 'Portuguese',
  ro: 'Romanian',
  sk: 'Slovak',
  sl: 'Slovenian',
  es: 'Spanish',
  sv: 'Swedish',
}

const LANGUAGE_FLAG_COUNTRY_CODES: Record<SupportedDisclosureLanguage, string> = {
  bg: 'BG',
  hr: 'HR',
  cs: 'CZ',
  da: 'DK',
  nl: 'NL',
  en: 'GB',
  et: 'EE',
  fi: 'FI',
  fr: 'FR',
  de: 'DE',
  el: 'GR',
  hu: 'HU',
  ga: 'IE',
  it: 'IT',
  lv: 'LV',
  lt: 'LT',
  mt: 'MT',
  pl: 'PL',
  pt: 'PT',
  ro: 'RO',
  sk: 'SK',
  sl: 'SI',
  es: 'ES',
  sv: 'SE',
}

const SUPPORTED_DISCLOSURE_LANGUAGE_VALUES: ReadonlySet<string> = new Set(
  SUPPORTED_DISCLOSURE_LANGUAGES,
)

function isChannelVisitorDisclosureLanguageMode(
  value: string,
): value is ChannelVisitorDisclosureLanguageMode {
  return value === 'visitor_preferred' || value === 'server_preferred'
}

function isSupportedDisclosureLanguage(value: string): value is SupportedDisclosureLanguage {
  return SUPPORTED_DISCLOSURE_LANGUAGE_VALUES.has(value)
}

export function VisitorDisclosureLanguageConfigurationSection({
  hasApiKey,
  settings,
  onSettingsSaved,
}: VisitorDisclosureLanguageConfigurationSectionProps) {
  const [modeOverride, setModeOverride] = useState<ChannelVisitorDisclosureLanguageMode | null>(
    null,
  )
  const [languageOverride, setLanguageOverride] = useState<SupportedDisclosureLanguage | null>(null)
  const [isSaving, setIsSaving] = useState(false)
  const config = loadVeratagAdminConfig()
  const { showError, showSuccess } = useNotifications()
  const modeValue = modeOverride ?? settings.preferredMode
  const languageValue = languageOverride ?? settings.fallbackLanguageBcp47

  // Hidden only when there is no API key at all yet — nothing has ever been
  // (or could have been) synced from the backend in that case, so there is
  // nothing for this section to configure. Once a key is saved, this
  // section stays visible even before/if the first sync completes, showing
  // its own alert below instead of disappearing with no explanation.
  if (!hasApiKey) {
    return null
  }

  async function handleSave() {
    if (modeValue === '' || languageValue === '') {
      return
    }

    setIsSaving(true)

    try {
      const updated = await updateVisitorDisclosureLanguageSettings(modeValue, languageValue)
      setModeOverride(updated.preferredMode === '' ? null : updated.preferredMode)
      setLanguageOverride(
        updated.fallbackLanguageBcp47 === '' ? null : updated.fallbackLanguageBcp47,
      )
      onSettingsSaved(updated)
      showSuccess('Visitor disclosure language settings saved.')
    } catch (error) {
      showError(
        error instanceof Error
          ? error.message
          : 'Failed to save visitor disclosure language settings.',
      )
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <section className="veratag-panel">
      <div className="veratag-panel__header">
        <h2 className="veratag-panel__title">Visitor Disclosure Language</h2>
      </div>
      <div className="veratag-panel__body">
        <p className="veratag-panel__text">
          vera[tag] enables you to provide language-aware AI content disclosures to visitors of your
          site. You can configure how the disclosure language is selected using one of two language
          modes:
        </p>
        <ul className="veratag-panel__list">
          <li className="veratag-stat-list__value">
            <strong>Site-preferred:</strong> Serves AI content disclosures in your WordPress site's
            primary language.
          </li>
          <li className="veratag-stat-list__value">
            <strong>Visitor-preferred:</strong> Serves AI content disclosures in the visitor's
            preferred browser language.
          </li>
        </ul>
        <p className="veratag-panel__text">
          To ensure that an AI content disclosure can always be served to visitors, you need to
          specify a fallback language. The fallback language is used whenever the required
          disclosure information is not available in the language selected by the current language
          mode. In visitor-preferred mode, it applies when the disclosure information is not
          available in the visitor’s preferred browser language. In site-preferred mode, it applies
          when the disclosure information is not available in the WordPress site’s primary language.
        </p>
        {!settings.hasBeenSynced ? (
          <Alert label="Error:" tone="error">
            Visitor disclosure language settings are not available yet. The settings have not been
            received from the vera[tag] platform. Run a pull synchronization to retrieve them.
          </Alert>
        ) : (
          <>
            <InputSelect
              id="veratag-visitor-disclosure-language-mode"
              label="Preferred language mode"
              onChange={(value) => {
                if (isChannelVisitorDisclosureLanguageMode(value)) {
                  setModeOverride(value)
                }
              }}
              options={[
                {
                  iconSrc: config.formIcons.server,
                  label: 'Site-preferred',
                  value: 'server_preferred',
                },
                {
                  iconSrc: config.formIcons.userRound,
                  label: 'Visitor-preferred',
                  value: 'visitor_preferred',
                },
              ]}
              placeholder="Select a mode"
              value={modeValue}
            />
            <InputSelect
              id="veratag-visitor-disclosure-fallback-language"
              label="Fallback language"
              onChange={(value) => {
                if (isSupportedDisclosureLanguage(value)) {
                  setLanguageOverride(value)
                }
              }}
              options={SUPPORTED_DISCLOSURE_LANGUAGES.map((bcp47) => ({
                iconClassName: `fflag fflag-${LANGUAGE_FLAG_COUNTRY_CODES[bcp47]} ff-sm`,
                label: LANGUAGE_LABELS[bcp47],
                value: bcp47,
              }))}
              placeholder="Select a fallback language"
              value={languageValue}
            />

            <p className="veratag-panel__text veratag-panel__text--note">
              <strong>Note:</strong> The Visitor Disclosure Language settings are synchronized with
              the vera[tag] platform and can also be managed in the vera[tag] dashboard.
            </p>

            <div className="veratag-settings-actions veratag-settings-actions--end">
              <Button
                buttonText={isSaving ? 'Saving...' : 'Save'}
                disabled={isSaving || modeValue === '' || languageValue === ''}
                onClick={() => void handleSave()}
              />
            </div>
          </>
        )}
      </div>
    </section>
  )
}
