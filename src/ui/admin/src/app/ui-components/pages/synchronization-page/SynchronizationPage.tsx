import { useEffect, useState } from 'react'
import {
  loadAdminSettings,
  loadPullSynchronizationStatus,
  loadPushApiStatus,
  type PullSynchronizationStatusResponse,
  type PushApiStatus,
  type SettingsResponse,
} from '../../../api/adminUiBridge'
import { ErrorPage } from '../../shared/error-page/ErrorPage'
import { Spinner } from '../../shared/misc/spinner/Spinner'
import { LatestPullSynchronizationSection } from './sections/latest-pull-synchronization-section/LatestPullSynchronizationSection'
import { LatestPushSynchronizationSection } from './sections/latest-push-synchronization-section/LatestPushSynchronizationSection'
import './synchronization-page.css'

type SynchronizationPageState =
  | { kind: 'loading' }
  | { kind: 'error'; message: string }
  | {
      kind: 'ready'
      settings: SettingsResponse
      pullSynchronizationStatus: PullSynchronizationStatusResponse
      pushApiStatus: PushApiStatus
    }

export function SynchronizationPage() {
  const [pageState, setPageState] = useState<SynchronizationPageState>({
    kind: 'loading',
  })
  const [loadAttempt, setLoadAttempt] = useState(0)

  useEffect(() => {
    let isMounted = true
    let refreshInterval: number | null = null

    function stopPolling(): void {
      if (refreshInterval !== null) {
        window.clearInterval(refreshInterval)
        refreshInterval = null
      }
    }

    async function hydrateSynchronizationPage(): Promise<void> {
      try {
        const [settings, pullSynchronizationStatus, pushApiStatus] = await Promise.all([
          loadAdminSettings(),
          loadPullSynchronizationStatus(),
          loadPushApiStatus(),
        ])

        if (!isMounted) {
          return
        }

        setPageState({
          kind: 'ready',
          settings,
          pullSynchronizationStatus,
          pushApiStatus,
        })

        if (refreshInterval === null) {
          refreshInterval = window.setInterval(() => void hydrateSynchronizationPage(), 5000)
        }
      } catch (error) {
        if (!isMounted) {
          return
        }

        stopPolling()
        setPageState({
          kind: 'error',
          message:
            error instanceof Error ? error.message : 'Failed to load synchronization page state.',
        })
      }
    }

    void hydrateSynchronizationPage()

    return () => {
      isMounted = false
      stopPolling()
    }
  }, [loadAttempt])

  function retryLoadingPage(): void {
    setPageState({ kind: 'loading' })
    setLoadAttempt((currentLoadAttempt) => currentLoadAttempt + 1)
  }

  if (pageState.kind === 'loading') {
    return (
      <section className="veratag-synchronization-page">
        <div className="veratag-synchronization-page__loading">
          <Spinner ariaLabel="Loading synchronization page" color="brand" size="lg" />
        </div>
      </section>
    )
  }

  if (pageState.kind === 'error') {
    return (
      <section className="veratag-synchronization-page">
        <ErrorPage
          message={pageState.message}
          onRetry={retryLoadingPage}
          title="Could not load synchronization"
        />
      </section>
    )
  }

  return (
    <section className="veratag-synchronization-page">
      <div className="veratag-synchronization-page__sections">
        <LatestPullSynchronizationSection
          hasStoredApiKey={pageState.settings.apiKey.trim() !== ''}
          lastPullSynchronizationStatus={
            pageState.pullSynchronizationStatus.lastPullSynchronizationStatus
          }
          nextPullSynchronizationAt={pageState.pullSynchronizationStatus.nextPullSynchronizationAt}
          onPullSynchronizationStatusUpdated={(pullSynchronizationStatus) => {
            setPageState((currentPageState) => {
              if (currentPageState.kind !== 'ready') {
                return currentPageState
              }

              return { ...currentPageState, pullSynchronizationStatus }
            })
          }}
        />
        <LatestPushSynchronizationSection status={pageState.pushApiStatus} />
      </div>
    </section>
  )
}
