import { loadVeratagAdminConfig } from '../config/loadVeratagAdminConfig'

/** Mirrors the backend's ChannelVisitorDisclosureLanguageModeKey unique_name values exactly. */
export type ChannelVisitorDisclosureLanguageMode = 'visitor_preferred' | 'server_preferred'

/** Mirrors the backend's LanguageKey enum's bcp47 values exactly. */
export type SupportedDisclosureLanguage =
  | 'bg'
  | 'hr'
  | 'cs'
  | 'da'
  | 'nl'
  | 'en'
  | 'et'
  | 'fi'
  | 'fr'
  | 'de'
  | 'el'
  | 'hu'
  | 'ga'
  | 'it'
  | 'lv'
  | 'lt'
  | 'mt'
  | 'pl'
  | 'pt'
  | 'ro'
  | 'sk'
  | 'sl'
  | 'es'
  | 'sv'

export const SUPPORTED_DISCLOSURE_LANGUAGES: readonly SupportedDisclosureLanguage[] = [
  'bg',
  'hr',
  'cs',
  'da',
  'nl',
  'en',
  'et',
  'fi',
  'fr',
  'de',
  'el',
  'hu',
  'ga',
  'it',
  'lv',
  'lt',
  'mt',
  'pl',
  'pt',
  'ro',
  'sk',
  'sl',
  'es',
  'sv',
]

/**
 * The backend-ground-truth visitor disclosure language settings for this
 * channel, synced via /pull-synchronization. `hasBeenSynced` is false only before
 * the very first successful sync has ever populated these — the admin UI
 * must not show the editable section until this is true, since there is no
 * meaningful local default to show or edit before then.
 */
export type ChannelVisitorDisclosureLanguageSettings = {
  preferredMode: ChannelVisitorDisclosureLanguageMode | ''
  fallbackLanguageBcp47: SupportedDisclosureLanguage | ''
  hasBeenSynced: boolean
}

type SettingsResponse = {
  apiKey: string
  channelDashboardUrl: string
  channelDisplayName: string
  channelVisitorDisclosureLanguageSettings: ChannelVisitorDisclosureLanguageSettings
  compatibility: CompatibilityStatus
  hasConnectedSuccessfullyAtLeastOnce: boolean
  lastPullSynchronizationStatus: PullSynchronizationAttemptStatus
  veratagDashboardBaseUrl: string
}

/** Whether this plugin's own release is still accepted by the backend — see CompatibilityCheckApiService. */
export type CompatibilityStatus = {
  status: 'ok' | 'update_required' | 'disabled'
  message: string | null
}


type VerificationFileState = { exists: boolean; token: string; url: string }

type PullSynchronizationAttemptStatus = {
  httpResponseCode: number
  httpStatusText: string
  message: string
  ok: boolean
  receivedDisclosureEntryCount: number | null
  statusSource: 'local-plugin' | 'remote-backend-response' | 'remote-http-protocol'
  updated_at: string
}

const EMPTY_PULL_SYNCHRONIZATION_STATUS: PullSynchronizationAttemptStatus = {
  httpResponseCode: 0,
  httpStatusText: '',
  message: '',
  ok: false,
  receivedDisclosureEntryCount: null,
  statusSource: 'local-plugin',
  updated_at: '',
}

type PullSynchronizationStatusResponse = {
  entryCount: number | null
  lastPullSynchronizationStatus: PullSynchronizationAttemptStatus
  nextPullSynchronizationAt: string | null
  storedContentCount: number
  storedContentSynchronizedAt: string | null
}

type PullSynchronizationRunResponse = PullSynchronizationStatusResponse & {
  batchId: number | null
  message: string
  ok: boolean
}

type SettingsUpdatePayload = {
  apiKey?: string
}

type RawRenderScriptStatus = {
  fileName: string
  requiredVersion: string
  cachedVersion: string | null
  lastFetchOk: boolean
  lastFetchErrorMessage: string | null
  lastFetchAt: string | null
}

type RenderScriptSyncStatusResponse = {
  ok: boolean
  message: string
  statuses: RawRenderScriptStatus[]
}

type RawIssue = {
  id: number | null
  reason: string
  impact: string
  message: string
  detectedAt: string | null
}

type RawEmbeddedPushApiStatus = {
  configured: boolean
  enabled: boolean
  hasSecret: boolean
  secretStub: string | null
  lastPushAttemptAt: string | null
  lastPushAttemptStatus: string | null
  lastPushAttemptStatusMessage: string | null
  lastSuccessfulPushAt: string | null
  lastSuccessfulPushMessage: string | null
}

type RawConfigurationEntry = {
  name: string
  issues: RawIssue[]
  placementRefs: string[]
  slots: Record<string, Record<string, { templateRef: string } | { custom: string }>>
}

type RawPlacementEntry = {
  name: string
  issues: RawIssue[]
  schema: { schemaName: string; schemaVersion: string }
  renderScript: { fileName: string; scriptReleaseVersion: string }
  rawParams: unknown
  slotBindings: Record<string, string>
}

type RawTextTemplateEntry = {
  name: string
  issues: RawIssue[]
  texts: Record<string, string>
}

type RawContentItem = {
  contentId: string
  contentType: string
  contentHtmlTag: string
  contentDisplay: unknown
  disclosureType: string | null
  configurationRef: string | null
  shouldBeDisclosed: boolean | null
  issues: RawIssue[]
}

type RawPage = { pageUrl: string; content: RawContentItem[] }

export type ContentOverviewIssue = RawIssue

export type ContentOverviewItem = {
  id: string
  pageUrl: string
  contentType: string
  contentHtmlTag: string
  contentDisplay: unknown
  disclosureType: string | null
  configurationRef: string | null
  configurationName: string | null
  isDisclosed: boolean | null
  issues: ContentOverviewIssue[]
}

export type ContentOverviewPage = {
  content: ContentOverviewItem[]
  totalPages: number
  totalElements: number
  pageNumber: number
  pageSize: number
  first: boolean
  last: boolean
  numberOfElements: number
  empty: boolean
}

export type ContentOverviewPageUrlOption = { pageUrl: string; contentCount: number }

export type ContentOverviewSortField =
  | 'ADDED_ORDER'
  | 'CONTENT_TYPE'
  | 'DISCLOSURE_TYPE'
  | 'PAGE_URL'
export type ContentOverviewSortDirection = 'asc' | 'desc'

export type ContentOverviewQuery = {
  searchText: string
  contentTypes: readonly string[]
  disclosureTypes: readonly string[]
  pageUrls: readonly string[]
  isDisclosed: boolean | null
  issueImpacts: readonly string[]
  sortBy: ContentOverviewSortField | null
  sortDir: ContentOverviewSortDirection
  pageNumber: number
  pageSize: number
}

/** Mirrors the backend's CmsPluginChannelPathsDto — every value is a path relative to veratagDashboardBaseUrl. */
type RawChannelPaths = {
  channelDashboardPath: string
  channelDisclosureConfigurationsPath: string
  channelDisclosureTextTemplatesPath: string
  channelDisclosurePlacementsPath: string
  channelContentManagementPath: string
  channelSettingsPath: string
}

type RawDisclosuresResponse = {
  channelDetails: {
    displayName: string | null
    paths: RawChannelPaths
    pushApiStatus: RawEmbeddedPushApiStatus | null
  }
  configurations: Record<string, RawConfigurationEntry>
  placements: Record<string, RawPlacementEntry>
  textTemplates: Record<string, RawTextTemplateEntry>
  pages: RawPage[]
}

type PushApiStatus = {
  configured: boolean
  enabled: boolean
  endpointUrl: string | null
  hasCredential: boolean
  secretStub: string | null
  lastPushAttemptAt: string | null
  lastPushAttemptStatus: 'SUCCESS' | 'FAILURE' | null
  lastPushAttemptStatusMessage: string | null
  lastSuccessfulPushAt: string | null
  lastSuccessfulPushMessage: string | null
  /** The real, fixed WordPress REST route pushes are received at (see PushApiReceivingRouteRegistrar) — always present, independent of `configured`/`endpointUrl`. */
  receivingUrl: string
}

type PushReceivedLogEntry = {
  id: number
  receivedAt: string
  instruction: string
  ref: string | null
  appliedSuccessfully: boolean
  errorMessage: string | null
}

export type PushReceivedLogPage = {
  content: PushReceivedLogEntry[]
  totalPages: number
  totalElements: number
  pageNumber: number
  pageSize: number
  first: boolean
  last: boolean
  numberOfElements: number
  empty: boolean
}

const EMPTY_PUSH_API_STATUS: PushApiStatus = {
  configured: false,
  enabled: false,
  endpointUrl: null,
  hasCredential: false,
  secretStub: null,
  lastPushAttemptAt: null,
  lastPushAttemptStatus: null,
  lastPushAttemptStatusMessage: null,
  lastSuccessfulPushAt: null,
  lastSuccessfulPushMessage: null,
  receivingUrl: '',
}

function dispatchSettingsUpdatedEvent(settings: SettingsResponse): void {
  window.dispatchEvent(
    new CustomEvent<SettingsResponse>('veratag:settings-updated', { detail: settings }),
  )
}

async function requestAdminUiBridge<TResponse>(
  path: string,
  init?: RequestInit,
): Promise<TResponse> {
  const config = loadVeratagAdminConfig()
  const response = await fetch(`${config.restApiBaseUrl}${path}`, {
    ...init,
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': config.restNonce,
      ...(init?.headers ?? {}),
    },
  })

  const responseData = (await response.json()) as TResponse | { message?: string }
  if (!response.ok) {
    const message =
      typeof responseData === 'object' &&
      responseData !== null &&
      'message' in responseData &&
      typeof responseData.message === 'string'
        ? responseData.message
        : 'Request failed.'
    throw new Error(message)
  }
  return responseData as TResponse
}

export async function loadAdminSettings(): Promise<SettingsResponse> {
  const response = await requestAdminUiBridge<SettingsResponse>('settings', { method: 'GET' })
  dispatchSettingsUpdatedEvent(response)
  return response
}

export async function updateAdminSettings(
  payload: SettingsUpdatePayload,
): Promise<SettingsResponse> {
  const response = await requestAdminUiBridge<SettingsResponse>('settings', {
    body: JSON.stringify(payload),
    method: 'POST',
  })
  dispatchSettingsUpdatedEvent(response)
  return response
}

export async function updateVisitorDisclosureLanguageSettings(
  preferredMode: ChannelVisitorDisclosureLanguageMode,
  fallbackLanguageBcp47: SupportedDisclosureLanguage,
): Promise<ChannelVisitorDisclosureLanguageSettings> {
  return requestAdminUiBridge('visitor-disclosure-language-settings', {
    body: JSON.stringify({ preferredMode, fallbackLanguageBcp47 }),
    method: 'PUT',
  })
}

export async function loadVerificationFile(): Promise<{ verificationFile: VerificationFileState }> {
  return requestAdminUiBridge('verification-file', { method: 'GET' })
}
export async function configureVerificationFile(
  verificationFileToken: string,
): Promise<{ verificationFile: VerificationFileState }> {
  return requestAdminUiBridge('verification-file', {
    body: JSON.stringify({ verificationFileToken }),
    method: 'POST',
  })
}
export async function removeVerificationFile(): Promise<{
  verificationFile: VerificationFileState
}> {
  return requestAdminUiBridge('verification-file', { method: 'DELETE' })
}

export async function runPullSynchronization(): Promise<PullSynchronizationRunResponse> {
  return requestAdminUiBridge('pull-synchronization/run', {
    body: JSON.stringify({}),
    method: 'POST',
  })
}
export async function loadPullSynchronizationStatus(): Promise<PullSynchronizationStatusResponse> {
  return requestAdminUiBridge('pull-synchronization/status', { method: 'GET' })
}
export async function loadRawDisclosures(): Promise<RawDisclosuresResponse> {
  return requestAdminUiBridge('raw-disclosures', { method: 'GET' })
}

function buildContentOverviewQueryString(query: ContentOverviewQuery): string {
  const params = new URLSearchParams()
  if (query.searchText.trim() !== '') params.set('searchText', query.searchText.trim())
  for (const contentType of query.contentTypes) params.append('contentType[]', contentType)
  for (const disclosureType of query.disclosureTypes)
    params.append('disclosureType[]', disclosureType)
  for (const pageUrl of query.pageUrls) params.append('pageUrl[]', pageUrl)
  if (query.isDisclosed !== null) params.set('isDisclosed', String(query.isDisclosed))
  for (const issueImpact of query.issueImpacts) params.append('issueImpact[]', issueImpact)
  if (query.sortBy !== null) {
    params.set('sortBy', query.sortBy)
    params.set('sortDir', query.sortDir)
  }
  params.set('pageNumber', String(query.pageNumber))
  params.set('pageSize', String(query.pageSize))
  return params.toString()
}

export async function loadContentOverviewPage(
  query: ContentOverviewQuery,
): Promise<ContentOverviewPage> {
  return requestAdminUiBridge(`content-overview?${buildContentOverviewQueryString(query)}`, {
    method: 'GET',
  })
}

export async function loadContentOverviewPageUrls(): Promise<{
  pageUrls: ContentOverviewPageUrlOption[]
}> {
  return requestAdminUiBridge('content-overview/page-urls', { method: 'GET' })
}

export async function loadRenderScriptSyncStatus(): Promise<RenderScriptSyncStatusResponse> {
  return requestAdminUiBridge('render-script-sync/status', { method: 'GET' })
}
export async function runRenderScriptSync(): Promise<RenderScriptSyncStatusResponse> {
  return requestAdminUiBridge('render-script-sync/pull', {
    body: JSON.stringify({}),
    method: 'POST',
  })
}

export async function loadPushApiStatus(): Promise<PushApiStatus> {
  return requestAdminUiBridge('push-api', { method: 'GET' })
}
export async function configurePushApi(): Promise<PushApiStatus> {
  return requestAdminUiBridge('push-api', { body: JSON.stringify({}), method: 'PUT' })
}
export async function setPushApiEnabled(enabled: boolean): Promise<PushApiStatus> {
  return requestAdminUiBridge('push-api/enabled', {
    body: JSON.stringify({ enabled }),
    method: 'PATCH',
  })
}
export async function removePushApi(): Promise<PushApiStatus> {
  return requestAdminUiBridge('push-api', { method: 'DELETE' })
}

export type UnregisterFromChannelResult = {
  ok: boolean
  backendRemovalWarning: string | null
}

/**
 * Tells the backend to remove this channel's push API configuration, then
 * resets this plugin's own local state back to exactly how it was on a
 * fresh install — same local reset an authenticated CHANNEL_DELETE push
 * from the backend would trigger. After this resolves, every other
 * settings/status value this admin UI holds is stale and the page should
 * reload or re-fetch everything from scratch.
 */
export async function unregisterFromChannel(): Promise<UnregisterFromChannelResult> {
  return requestAdminUiBridge('unregister-from-channel', {
    body: JSON.stringify({}),
    method: 'POST',
  })
}
export async function loadPushReceivedLogPage(
  pageNumber: number,
  pageSize: number,
): Promise<PushReceivedLogPage> {
  return requestAdminUiBridge(
    `push-api/recent?pageNumber=${encodeURIComponent(String(pageNumber))}&pageSize=${encodeURIComponent(String(pageSize))}`,
    { method: 'GET' },
  )
}
export async function loadPushReceivedLog(limit = 50): Promise<PushReceivedLogEntry[]> {
  const page = await loadPushReceivedLogPage(1, limit)
  return page.content
}

export { EMPTY_PUSH_API_STATUS, EMPTY_PULL_SYNCHRONIZATION_STATUS }
export type {
  PullSynchronizationStatusResponse,
  PushApiStatus,
  PushReceivedLogEntry,
  RawChannelPaths,
  RawConfigurationEntry,
  RawContentItem,
  RawDisclosuresResponse,
  RawEmbeddedPushApiStatus,
  RawIssue,
  RawPage,
  RawPlacementEntry,
  RawRenderScriptStatus,
  RawTextTemplateEntry,
  RenderScriptSyncStatusResponse,
  SettingsResponse,
  PullSynchronizationRunResponse,
  PullSynchronizationAttemptStatus,
  VerificationFileState,
}
