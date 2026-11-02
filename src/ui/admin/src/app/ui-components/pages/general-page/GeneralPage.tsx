import { useEffect, useState } from 'react'
import {
  EMPTY_PUSH_API_STATUS,
  EMPTY_PULL_SYNCHRONIZATION_STATUS,
  loadAdminSettings,
  loadPushApiStatus,
  type PushApiStatus,
  type SettingsResponse,
} from '../../../api/adminUiBridge'
import { ErrorPage } from '../../shared/error-page/ErrorPage'
import { Spinner } from '../../shared/misc/spinner/Spinner'
import { useNotifications } from '../../shared/notifications/notification-provider/notificationContext'
import { OverviewVeratagChannelSection } from './sections/overview-veratag-channel-section/OverviewVeratagChannelSection'
import { PullSynchronizationStatusSection } from './sections/pull-synchronization-status-section/PullSynchronizationStatusSection'
import { PushSynchronizationStatusSection } from './sections/push-synchronization-status-section/PushSynchronizationStatusSection'
import { SetupRequiredSection } from './sections/setup-required-section/SetupRequiredSection'
import { UnsuccessfulFirstConnectionSection } from './sections/unsuccessful-first-connection-section/UnsuccessfulFirstConnectionSection'
import { WelcomeToVeratagSection } from './sections/welcome-to-veratag-section/WelcomeToVeratagSection'
import './general-page.css'


type GeneralPageState =
  | { kind: 'loading' }
  | { kind: 'error'; message: string }
  | {
      kind: 'ready'
      settings: SettingsResponse
      pushStatus: PushApiStatus
    }

export function GeneralPage() {
  const [pageState, setPageState] = useState<GeneralPageState>({
    kind: 'loading',
  })
  const [loadAttempt, setLoadAttempt] = useState(0)
  const { showError } = useNotifications()

  useEffect(() => {
    let isMounted = true

    async function hydrateGeneralPage(): Promise<void> {
      try {
        const settings = await loadAdminSettings()
        let pushStatus = EMPTY_PUSH_API_STATUS

        if (settings.apiKey.trim() !== '') {
          try {
            pushStatus = await loadPushApiStatus()
          } catch (error) {
            showError(error instanceof Error ? error.message : 'Failed to load push API status.')
          }
        }

        if (!isMounted) {
          return
        }

        setPageState({ kind: 'ready', settings, pushStatus })
      } catch (error) {
        if (!isMounted) {
          return
        }

        setPageState({
          kind: 'error',
          message: error instanceof Error ? error.message : 'Failed to load general page state.',
        })
      }
    }

    void hydrateGeneralPage()

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
      <section className="veratag-general-page">
        <div className="veratag-general-page__loading">
          <Spinner ariaLabel="Loading general page" color="brand" size="lg" />
        </div>
      </section>
    )
  }

  if (pageState.kind === 'error') {
    return (
      <section className="veratag-general-page">
        <ErrorPage
          message={pageState.message}
          onRetry={retryLoadingPage}
          title="Could not load general information"
        />
      </section>
    )
  }

  const isSetupRequired = pageState.settings.apiKey.trim() === ''
  const shouldShowUnsuccessfulFirstConnectionSection =
    pageState.settings.apiKey.trim() !== '' &&
    pageState.settings.lastPullSynchronizationStatus.ok === false &&
    pageState.settings.hasConnectedSuccessfullyAtLeastOnce === false

  return (
    <section className="veratag-general-page">
      <div className="veratag-general-page__sections">
        <WelcomeToVeratagSection />
        {isSetupRequired ? <SetupRequiredSection /> : null}
        {
          null
        }
        {shouldShowUnsuccessfulFirstConnectionSection ? (
          <UnsuccessfulFirstConnectionSection
            errorMessage={pageState.settings.lastPullSynchronizationStatus.message}
          />
        ) : null}
        {pageState.settings.hasConnectedSuccessfullyAtLeastOnce ? (
          <div className="veratag-general-page__section-row">
            <OverviewVeratagChannelSection
              channelDashboardUrl={pageState.settings.channelDashboardUrl}
              channelDisplayName={pageState.settings.channelDisplayName}
            />
            <div className="veratag-general-page__section-row-stack">
              <PullSynchronizationStatusSection
                lastPullSynchronizationStatus={
                  pageState.settings.lastPullSynchronizationStatus ??
                  EMPTY_PULL_SYNCHRONIZATION_STATUS
                }
              />
              {isSetupRequired ? null : (
                <PushSynchronizationStatusSection status={pageState.pushStatus} />
              )}
            </div>
          </div>
        ) : null}
      </div>
    </section>
  )
}
