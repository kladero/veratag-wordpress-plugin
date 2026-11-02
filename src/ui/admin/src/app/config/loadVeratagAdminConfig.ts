export type VeratagAdminPage = 'general' | 'disclosures' | 'synchronization' | 'settings'

export type VeratagAdminConfig = {
  channelDashboardUrl: string
  currentUserDisplayName: string
  currentPage: VeratagAdminPage
  formIcons: {
    selectChevron: string
    server: string
    userRound: string
  }
  /** Base URL for `assets/icons/` (see `AdminUiBootstrap::enqueueAssets`) — append a relative path, e.g. `l/ban.svg`. */
  iconsBaseUrl: string
  restApiBaseUrl: string
  restNonce: string
  settingsPageUrl: string
  statusIcons: {
    failure: string
    success: string
  }
  wordmarkUrl: string
}

type VeratagAdminWindow = Window & {
  veratagAdminConfig?: VeratagAdminConfig
}

export function loadVeratagAdminConfig(): VeratagAdminConfig {
  const windowWithConfig = window as VeratagAdminWindow
  const config = windowWithConfig.veratagAdminConfig

  if (config === undefined) {
    return {
      channelDashboardUrl: '',
      currentUserDisplayName: '',
      currentPage: 'general',
      formIcons: {
        selectChevron: '',
        server: '',
        userRound: '',
      },
      iconsBaseUrl: '',
      restApiBaseUrl: '',
      restNonce: '',
      settingsPageUrl: '',
      statusIcons: {
        failure: '',
        success: '',
      },
      wordmarkUrl: '',
    }
  }

  return config
}
