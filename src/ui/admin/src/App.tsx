import { useEffect, useState, type ComponentType } from 'react'
import {
  loadAdminSettings,
  type CompatibilityStatus,
  type SettingsResponse,
} from './app/api/adminUiBridge'
import { loadVeratagAdminConfig, type VeratagAdminPage } from './app/config/loadVeratagAdminConfig'
import { AdminPageLayout } from './app/ui-components/layout/AdminPageLayout'
import { ContentPage } from './app/ui-components/pages/content-page/ContentPage'
import { ErrorPage } from './app/ui-components/shared/error-page/ErrorPage'
import { GeneralPage } from './app/ui-components/pages/general-page/GeneralPage'
import { SettingsPage } from './app/ui-components/pages/settings-page/SettingsPage'
import { SynchronizationPage } from './app/ui-components/pages/synchronization-page/SynchronizationPage'
import { Spinner } from './app/ui-components/shared/misc/spinner/Spinner'
import { NotificationProvider } from './app/ui-components/shared/notifications/notification-provider/NotificationProvider'
import { DialogProvider } from './app/ui-components/shared/overlays/dialog/DialogProvider'
import './app/ui-components/shared/panel/panel.css'

// Keep the existing WordPress route key stable; only the UI component is renamed.
const pageRegistry: Record<VeratagAdminPage, ComponentType> = {
  general: GeneralPage,
  disclosures: ContentPage,
  synchronization: SynchronizationPage,
  settings: SettingsPage,
}

type CompatibilityGateState =
  | { kind: 'checking' }
  | { kind: 'error'; message: string }
  | { kind: 'ready'; compatibility: CompatibilityStatus }

function App() {
  const config = loadVeratagAdminConfig()
  const [channelDashboardUrl, setChannelDashboardUrl] = useState(config.channelDashboardUrl)
  const [gateState, setGateState] = useState<CompatibilityGateState>({ kind: 'checking' })
  const [loadAttempt, setLoadAttempt] = useState(0)
  const ActivePage = pageRegistry[config.currentPage]

  useEffect(() => {
    let isMounted = true

    async function loadCompatibility(): Promise<void> {
      try {
        const settings = await loadAdminSettings()
        if (!isMounted) {
          return
        }

        if (
          !settings.compatibility ||
          !['ok', 'update_required', 'disabled'].includes(settings.compatibility.status)
        ) {
          throw new Error('The plugin compatibility status could not be determined.')
        }

        setChannelDashboardUrl(settings.channelDashboardUrl)
        setGateState({ kind: 'ready', compatibility: settings.compatibility })
      } catch (error) {
        if (!isMounted) {
          return
        }

        setGateState({
          kind: 'error',
          message: error instanceof Error ? error.message : 'Could not check plugin compatibility.',
        })
      }
    }

    function handleSettingsUpdated(event: Event): void {
      const customEvent = event as CustomEvent<SettingsResponse>
      if (customEvent.detail && typeof customEvent.detail.channelDashboardUrl === 'string') {
        setChannelDashboardUrl(customEvent.detail.channelDashboardUrl)
      }

      if (customEvent.detail?.compatibility) {
        setGateState({ kind: 'ready', compatibility: customEvent.detail.compatibility })
      }
    }

    void loadCompatibility()
    window.addEventListener('veratag:settings-updated', handleSettingsUpdated)

    return () => {
      isMounted = false
      window.removeEventListener('veratag:settings-updated', handleSettingsUpdated)
    }
  }, [loadAttempt])

  function retryCompatibilityCheck(): void {
    setGateState({ kind: 'checking' })
    setLoadAttempt((currentAttempt) => currentAttempt + 1)
  }

  if (gateState.kind === 'checking') {
    return (
      <div className="veratag-admin-app">
        <div className="veratag-admin-app__loading">
          <Spinner ariaLabel="Checking plugin compatibility" color="brand" size="lg" />
        </div>
      </div>
    )
  }

  if (gateState.kind === 'error') {
    return (
      <div className="veratag-admin-app">
        <ErrorPage
          title="Unable to verify plugin compatibility"
          message={gateState.message}
          onRetry={retryCompatibilityCheck}
          retryLabel="Try again"
        />
      </div>
    )
  }

  if (gateState.compatibility.status === 'update_required') {
    return (
      <div className="veratag-admin-app">
        <ErrorPage
          code="426"
          title="Update required"
          message={
            gateState.compatibility.message ??
            'This plugin version is no longer supported. Please update.'
          }
        />
      </div>
    )
  }

  if (gateState.compatibility.status === 'disabled') {
    return (
      <div className="veratag-admin-app">
        <ErrorPage
          code="403"
          title="Integration disabled"
          message={
            gateState.compatibility.message ??
            'This integration is currently disabled on the vera[tag] platform.'
          }
        />
      </div>
    )
  }

  return (
    <div className="veratag-admin-app">
      <NotificationProvider>
        <DialogProvider>
          <AdminPageLayout
            channelDashboardUrl={channelDashboardUrl}
            wordmarkUrl={config.wordmarkUrl}
          >
            <ActivePage />
          </AdminPageLayout>
        </DialogProvider>
      </NotificationProvider>
    </div>
  )
}

export default App
