import { useEffect, useState } from 'react'
import {
  loadAdminSettings,
  loadPullSynchronizationStatus,
  loadRenderScriptSyncStatus,
  type PullSynchronizationStatusResponse,
  type RenderScriptSyncStatusResponse,
  type SettingsResponse,
} from '../../../api/adminUiBridge'
import { ErrorPage } from '../../shared/error-page/ErrorPage'
import { Spinner } from '../../shared/misc/spinner/Spinner'
import { ContentOverviewSection } from './sections/content-overview-section/ContentOverviewSection'
import { RenderScriptStatusSection } from './sections/render-script-status-section/RenderScriptStatusSection'
import './content-page.css'

type ContentPageState =
  | { kind: 'loading' }
  | { kind: 'error'; message: string }
  | {
      kind: 'ready'
      settings: SettingsResponse
      pullSynchronizationStatus: PullSynchronizationStatusResponse
      renderScriptSyncStatus: RenderScriptSyncStatusResponse
    }

export function ContentPage() {
  const [pageState, setPageState] = useState<ContentPageState>({
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

    async function hydrateContentPage(): Promise<void> {
      try {
        const [settings, pullSynchronizationStatus, renderScriptSyncStatus] = await Promise.all([
          loadAdminSettings(),
          loadPullSynchronizationStatus(),
          loadRenderScriptSyncStatus(),
        ])

        if (!isMounted) {
          return
        }

        setPageState({
          kind: 'ready',
          settings,
          pullSynchronizationStatus,
          renderScriptSyncStatus,
        })

        if (refreshInterval === null) {
          refreshInterval = window.setInterval(() => void hydrateContentPage(), 5000)
        }
      } catch (error) {
        if (!isMounted) {
          return
        }

        stopPolling()
        setPageState({
          kind: 'error',
          message: error instanceof Error ? error.message : 'Failed to load content page state.',
        })
      }
    }

    void hydrateContentPage()

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
      <section className="veratag-content-page">
        <div className="veratag-content-page__loading">
          <Spinner ariaLabel="Loading content page" color="brand" size="lg" />
        </div>
      </section>
    )
  }

  if (pageState.kind === 'error') {
    return (
      <section className="veratag-content-page">
        <ErrorPage
          message={pageState.message}
          onRetry={retryLoadingPage}
          title="Could not load content"
        />
      </section>
    )
  }

  return (
    <section className="veratag-content-page">
      <div className="veratag-content-page__sections">
        <ContentOverviewSection
          storedContentCount={pageState.pullSynchronizationStatus.storedContentCount}
          storedContentSynchronizedAt={
            pageState.pullSynchronizationStatus.storedContentSynchronizedAt
          }
          veratagDashboardBaseUrl={pageState.settings.veratagDashboardBaseUrl}
        />
        <RenderScriptStatusSection
          status={pageState.renderScriptSyncStatus}
          onStatusUpdated={(renderScriptSyncStatus) => {
            setPageState((currentPageState) => {
              if (currentPageState.kind !== 'ready') {
                return currentPageState
              }

              return { ...currentPageState, renderScriptSyncStatus }
            })
          }}
        />
      </div>
    </section>
  )
}
