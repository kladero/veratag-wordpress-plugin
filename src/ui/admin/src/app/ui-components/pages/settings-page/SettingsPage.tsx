import { useEffect, useRef, useState } from 'react'
import {
  EMPTY_PUSH_API_STATUS,
  loadAdminSettings,
  loadPushApiStatus,
  loadVerificationFile,
  runPullSynchronization,
  type ChannelVisitorDisclosureLanguageSettings,
  type PushApiStatus,
  type SettingsResponse,
  type VerificationFileState,
} from '../../../api/adminUiBridge'
import { ErrorPage } from '../../shared/error-page/ErrorPage'
import { Spinner } from '../../shared/misc/spinner/Spinner'
import { useNotifications } from '../../shared/notifications/notification-provider/notificationContext'
import { PullSynchronizationConfigurationSection } from './sections/pull-synchronization-configuration-section/PullSynchronizationConfigurationSection'
import { PushSynchronizationConfigurationSection } from './sections/push-synchronization-configuration-section/PushSynchronizationConfigurationSection'
import { RemovePluginConnectionSection } from './sections/remove-plugin-connection-section/RemovePluginConnectionSection'
import { VerificationFileConfigurationSection } from './sections/verification-file-configuration-section/VerificationFileConfigurationSection'
import { VisitorDisclosureLanguageConfigurationSection } from './sections/visitor-disclosure-language-configuration-section/VisitorDisclosureLanguageConfigurationSection'
import './settings-page.css'


/** How many times to re-check for a freshly-saved API key's first successful
 * sync before giving up â€” a wrong key will never sync, so this must be
 * bounded rather than polled forever. */
const API_KEY_SYNC_RETRY_ATTEMPTS = 2
const API_KEY_SYNC_RETRY_DELAY_MS = 1500

type SettingsPageState =
  | { kind: 'loading' }
  | { kind: 'error'; message: string }
  | {
      kind: 'ready'
      settings: SettingsResponse
      verificationFile: VerificationFileState
      pushApiStatus: PushApiStatus
    }

export function SettingsPage() {
  const [pageState, setPageState] = useState<SettingsPageState>({
    kind: 'loading',
  })
  const [loadAttempt, setLoadAttempt] = useState(0)
  const { showError } = useNotifications()
  const isMountedRef = useRef(true)

  useEffect(() => {
    isMountedRef.current = true
    return () => {
      isMountedRef.current = false
    }
  }, [])

  /**
   * Saving a new/changed API key does not itself make the backend run a
   * pull synchronization â€” that normally only happens on the plugin's own
   * schedule, or via the "Synchronize now" button on the Synchronization
   * page. Without this, `channelVisitorDisclosureLanguageSettings` (and any
   * other backend-synced setting) would keep showing stale or "not synced
   * yet" state until that next scheduled run. So changing the API key here
   * explicitly triggers one sync attempt, then re-fetches settings â€” with a
   * few bounded retries in case the sync completes slightly after this
   * call returns, rather than polling forever (a wrong key will never
   * succeed, so this must stop eventually).
   */
  async function trySyncAndRefreshAfterApiKeyChange(): Promise<void> {
    try {
      await runPullSynchronization()
    } catch {
      // A failed sync (e.g. a wrong key) still means settings should be
      // re-fetched below so hasBeenSynced reflects reality â€” the sync
      // page's own status surfaces the failure itself.
    }

    for (let attempt = 0; attempt < API_KEY_SYNC_RETRY_ATTEMPTS; attempt += 1) {
      if (!isMountedRef.current) {
        return
      }

      let refreshed: SettingsResponse
      try {
        refreshed = await loadAdminSettings()
      } catch {
        return
      }

      if (!isMountedRef.current) {
        return
      }

      setPageState((currentPageState) => {
        if (currentPageState.kind !== 'ready') {
          return currentPageState
        }

        return { ...currentPageState, settings: refreshed }
      })

      if (refreshed.channelVisitorDisclosureLanguageSettings.hasBeenSynced) {
        return
      }

      await new Promise((resolve) => window.setTimeout(resolve, API_KEY_SYNC_RETRY_DELAY_MS))
    }
  }

  useEffect(() => {
    let isMounted = true

    async function hydrateSettingsPage(): Promise<void> {
      try {
        const [settingsResponse, verificationFileResponse] = await Promise.all([
          loadAdminSettings(),
          loadVerificationFile(),
        ])
        let pushApiStatus = EMPTY_PUSH_API_STATUS

        try {
          pushApiStatus = await loadPushApiStatus()
        } catch (error) {
          showError(error instanceof Error ? error.message : 'Failed to load push API status.')
        }

        if (!isMounted) {
          return
        }

        setPageState({
          kind: 'ready',
          settings: settingsResponse,
          verificationFile: verificationFileResponse.verificationFile,
          pushApiStatus,
        })
      } catch (error) {
        if (!isMounted) {
          return
        }

        setPageState({
          kind: 'error',
          message: error instanceof Error ? error.message : 'Failed to load settings.',
        })
      }
    }

    void hydrateSettingsPage()

    return () => {
      isMounted = false
    }
  }, [loadAttempt, showError])

  function retryLoadingPage(): void {
    setPageState({ kind: 'loading' })
    setLoadAttempt((currentLoadAttempt) => currentLoadAttempt + 1)
  }

  if (pageState.kind === 'loading') {
    return (
      <section className="veratag-settings-page">
        <div className="veratag-settings-page__loading">
          <Spinner ariaLabel="Loading settings page" color="brand" size="lg" />
        </div>
      </section>
    )
  }

  if (pageState.kind === 'error') {
    return (
      <section className="veratag-settings-page">
        <ErrorPage
          message={pageState.message}
          onRetry={retryLoadingPage}
          title="Could not load settings"
        />
      </section>
    )
  }

  return (
    <section className="veratag-settings-page">
      <div className="veratag-settings-page__sections">
        <VerificationFileConfigurationSection
          verificationFile={pageState.verificationFile}
          onVerificationFileUpdated={(verificationFile) => {
            setPageState((currentPageState) => {
              if (currentPageState.kind !== 'ready') {
                return currentPageState
              }

              return { ...currentPageState, verificationFile }
            })
          }}
        />
        <PullSynchronizationConfigurationSection
          apiKey={pageState.settings.apiKey}
          onApiKeySaved={(apiKey) => {
            const previousApiKey = pageState.settings.apiKey
            setPageState((currentPageState) => {
              if (currentPageState.kind !== 'ready') {
                return currentPageState
              }

              return {
                ...currentPageState,
                settings: { ...currentPageState.settings, apiKey },
                pushApiStatus:
                  apiKey.trim() === '' ? EMPTY_PUSH_API_STATUS : currentPageState.pushApiStatus,
              }
            })

            if (apiKey.trim() !== '' && apiKey !== previousApiKey) {
              void trySyncAndRefreshAfterApiKeyChange()
            }
          }}
        />
        <PushSynchronizationConfigurationSection
          hasApiKey={pageState.settings.apiKey.trim() !== ''}
          hasConnectedSuccessfullyAtLeastOnce={
            pageState.settings.hasConnectedSuccessfullyAtLeastOnce
          }
          status={pageState.pushApiStatus}
          onStatusUpdated={(pushApiStatus) => {
            setPageState((currentPageState) => {
              if (currentPageState.kind !== 'ready') {
                return currentPageState
              }

              return { ...currentPageState, pushApiStatus }
            })
          }}
        />
        <VisitorDisclosureLanguageConfigurationSection
          hasApiKey={pageState.settings.apiKey.trim() !== ''}
          settings={pageState.settings.channelVisitorDisclosureLanguageSettings}
          onSettingsSaved={(
            channelVisitorDisclosureLanguageSettings: ChannelVisitorDisclosureLanguageSettings,
          ) => {
            setPageState((currentPageState) => {
              if (currentPageState.kind !== 'ready') {
                return currentPageState
              }

              return {
                ...currentPageState,
                settings: {
                  ...currentPageState.settings,
                  channelVisitorDisclosureLanguageSettings,
                },
              }
            })
          }}
        />
        {pageState.settings.apiKey.trim() !== '' ? <RemovePluginConnectionSection /> : null}
        {
          null
        }
      </div>
    </section>
  )
}
