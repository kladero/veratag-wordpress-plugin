import { Fragment, useEffect, useMemo, useRef, useState, type CSSProperties } from 'react'

import {
  loadContentOverviewPage,
  loadContentOverviewPageUrls,
  loadRawDisclosures,
  type ContentOverviewItem,
  type ContentOverviewPageUrlOption,
  type ContentOverviewQuery,
  type ContentOverviewSortDirection,
  type ContentOverviewSortField,
} from '../../../../../api/adminUiBridge'

import { getContentTypeIconUrl } from '../../../../../config/typeIcons'

import { loadVeratagAdminConfig } from '../../../../../config/loadVeratagAdminConfig'

import { Alert } from '../../../../shared/alert/Alert'
import { InputText } from '../../../../shared/form/input-text/InputText'
import { Badge } from '../../../../shared/misc/badge/Badge'
import { Spinner } from '../../../../shared/misc/spinner/Spinner'
import { Popover } from '../../../../shared/overlays/popover/Popover'
import { TableFilterButton } from '../../../../shared/table/table-filter-button/TableFilterButton'

import {
  TableNavigationFooter,
  type TableNavigationFooterItem,
} from '../../../../shared/table/table-navigation-footer/TableNavigationFooter'

import {
  TableSortDropdownButton,
  type TableSortCriterion,
  type TableSortSelection,
} from '../../../../shared/table/table-sort-dropdown-button/TableSortDropdownButton'

import './content-overview-section.css'

type ContentOverviewSectionProps = {
  storedContentCount: number
  storedContentSynchronizedAt: string | null
  veratagDashboardBaseUrl: string
}

type FilterId = 'contentType' | 'disclosureType' | 'pageUrl' | 'isDisclosed' | 'issueImpact'

type BadgeColor = 'brand' | 'red' | 'green' | 'blue' | 'yellow' | 'orange' | 'violet' | 'gray'

type ConfigurationTier = 'system' | 'user' | 'custom' | 'unknown'
type IssueImpact = 'action_required' | 'action_recommended'
type DisclosureType =
  | 'AiGenerated'
  | 'AiAssisted'
  | 'HumanCreated'
  | 'AiGeneratedFactChecked'
  | 'AiAssistedFactChecked'
type ContentType = 'Heading' | 'Paragraph' | 'Image' | 'ContentArea'

type DisclosureTypeDefinition = {
  backendValue: string
  label: string
}

type ContentTypeDefinition = {
  backendValue: string
  label: string
}

type GroupedContent = {
  pageUrl: string
  items: ContentOverviewItem[]
}

type MaskIconProps = {
  src: string
  className: string
}

const CONTENT_TYPES: Readonly<Record<ContentType, ContentTypeDefinition>> = Object.freeze({
  Heading: { backendValue: 'heading', label: 'Heading' },
  Paragraph: { backendValue: 'paragraph', label: 'Paragraph' },
  Image: { backendValue: 'image', label: 'Image' },
  ContentArea: { backendValue: 'content-area', label: 'Content area' },
})

const CONTENT_TYPE_OPTIONS: readonly string[] = Object.values(CONTENT_TYPES).map(
  (definition) => definition.backendValue,
)

const DISCLOSURE_TYPES: Readonly<Record<DisclosureType, DisclosureTypeDefinition>> = Object.freeze({
  AiGenerated: {
    backendValue: 'disclosure:type:ai_generated',
    label: 'AI-generated',
  },
  AiAssisted: {
    backendValue: 'disclosure:type:ai_assisted',
    label: 'AI-assisted',
  },
  HumanCreated: {
    backendValue: 'disclosure:type:human_created',
    label: 'Human-created',
  },
  AiGeneratedFactChecked: {
    backendValue: 'disclosure:type:ai_generated_fact_checked',
    label: 'AI-generated (fact-checked)',
  },
  AiAssistedFactChecked: {
    backendValue: 'disclosure:type:ai_assisted_fact_checked',
    label: 'AI-assisted (fact-checked)',
  },
})

const DISCLOSURE_TYPE_OPTIONS: readonly string[] = Object.values(DISCLOSURE_TYPES).map(
  (definition) => definition.backendValue,
)

const IS_DISCLOSED_OPTIONS: readonly string[] = ['true', 'false']

const ISSUE_IMPACT_OPTIONS: readonly string[] = ['action_required', 'action_recommended', 'none']

const LINES_PER_PAGE_OPTIONS: readonly number[] = [5, 15, 25, 50]
const DEFAULT_LINES_PER_PAGE = 15

const SORT_CRITERIA: readonly TableSortCriterion[] = [
  { field: 'ADDED_ORDER', label: 'Added order' },
  { field: 'CONTENT_TYPE', label: 'Content type' },
  { field: 'DISCLOSURE_TYPE', label: 'Disclosure type' },
  { field: 'PAGE_URL', label: 'Page URL' },
]

function MaskIcon({ src, className }: MaskIconProps) {
  const style: CSSProperties = {
    WebkitMaskImage: `url("${src}")`,
    maskImage: `url("${src}")`,
  }

  return <span aria-hidden="true" className={className} style={style} />
}

function configurationTier(configurationRef: string | null): ConfigurationTier {
  if (configurationRef === null || configurationRef.trim() === '') {
    return 'unknown'
  }

  const separatorIndex = configurationRef.indexOf(':')
  if (separatorIndex < 0) return 'unknown'

  const tier = configurationRef.slice(0, separatorIndex)
  if (tier === 'system' || tier === 'user' || tier === 'custom') {
    return tier
  }

  return 'unknown'
}

function configurationTierBadgeColor(tier: ConfigurationTier): BadgeColor {
  switch (tier) {
    case 'system':
      return 'blue'
    case 'user':
      return 'violet'
    case 'custom':
      return 'orange'
    default:
      return 'gray'
  }
}

function configurationTierIconUrl(
  tier: ConfigurationTier,
  iconsBaseUrl: string,
): string | undefined {
  switch (tier) {
    case 'system':
      return `${iconsBaseUrl}l/file-lock.svg`
    case 'user':
      return `${iconsBaseUrl}l/file-cog.svg`
    case 'custom':
      return `${iconsBaseUrl}l/file-pen.svg`
    default:
      return undefined
  }
}

function issueImpactIconUrl(impact: IssueImpact, iconsBaseUrl: string): string {
  return impact === 'action_required'
    ? `${iconsBaseUrl}l/octagon-alert.svg`
    : `${iconsBaseUrl}l/triangle-alert.svg`
}

function configurationDashboardUrl(
  configurationRef: string,
  channelDisclosureConfigurationsPath: string | undefined,
  veratagDashboardBaseUrl: string | undefined,
): string | null {
  if (!channelDisclosureConfigurationsPath || !veratagDashboardBaseUrl) {
    return null
  }

  const separatorIndex = configurationRef.indexOf(':')
  if (separatorIndex < 0) return null

  const tier = configurationRef.slice(0, separatorIndex)
  const id = configurationRef.slice(separatorIndex + 1)

  if ((tier !== 'system' && tier !== 'user') || id === '') {
    return null
  }

  const base = veratagDashboardBaseUrl.replace(/\/+$/, '')
  const path = channelDisclosureConfigurationsPath.replace(/^\/+|\/+$/g, '')

  if (base === '' || path === '') return null
  return `${base}/${path}/${tier}/${id}`
}

function formatDateTime(value: string | null): string {
  if (value === null || value.trim() === '') return 'Not available'

  const parsedDate = new Date(value)
  if (Number.isNaN(parsedDate.getTime())) return value

  return new Intl.DateTimeFormat(undefined, {
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    month: 'short',
    second: '2-digit',
    year: 'numeric',
  }).format(parsedDate)
}

function contentDisplaySummary(contentDisplay: unknown): string {
  if (typeof contentDisplay !== 'object' || contentDisplay === null) return ''

  const record = contentDisplay as Record<string, unknown>
  for (const key of ['text', 'caption', 'alt', 'title']) {
    const value = record[key]
    if (typeof value === 'string' && value.trim() !== '') return value
  }

  return ''
}

function contentDisplayImageSrc(contentDisplay: unknown): string | null {
  if (typeof contentDisplay !== 'object' || contentDisplay === null) return null

  const src = (contentDisplay as Record<string, unknown>).src
  return typeof src === 'string' && src.trim() !== '' ? src : null
}

function toTitleLabel(value: string): string {
  return value
    .replaceAll(':', ' ')
    .replaceAll('_', ' ')
    .replaceAll('-', ' ')
    .split(' ')
    .filter((part) => part.length > 0)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ')
}

function disclosureTypeLabel(disclosureType: string): string {
  for (const definition of Object.values(DISCLOSURE_TYPES)) {
    if (definition.backendValue === disclosureType) {
      return definition.label
    }
  }

  return 'Unknown disclosure type'
}

function contentTypeLabel(contentType: string): string {
  for (const definition of Object.values(CONTENT_TYPES)) {
    if (definition.backendValue === contentType) {
      return definition.label
    }
  }

  return 'Unknown content type'
}

function toggleMultiSelection(selectedValues: readonly string[], value: string): readonly string[] {
  return selectedValues.includes(value)
    ? selectedValues.filter((selectedValue) => selectedValue !== value)
    : [...selectedValues, value]
}

function buildPaginationItems(
  currentPage: number,
  totalPages: number,
): readonly TableNavigationFooterItem[] {
  if (totalPages <= 7) {
    return Array.from({ length: Math.max(totalPages, 1) }, (_, index) => ({
      type: 'page',
      value: index + 1,
    }))
  }

  const windowStart = Math.max(2, currentPage - 1)
  const windowEnd = Math.min(totalPages - 1, currentPage + 1)
  const items: TableNavigationFooterItem[] = [{ type: 'page', value: 1 }]

  if (windowStart > 2) {
    items.push({ type: 'ellipsis', id: 'ellipsis-left' })
  }

  for (let page = windowStart; page <= windowEnd; page += 1) {
    items.push({ type: 'page', value: page })
  }

  if (windowEnd < totalPages - 1) {
    items.push({ type: 'ellipsis', id: 'ellipsis-right' })
  }

  items.push({ type: 'page', value: totalPages })
  return items
}

function groupByPageUrl(content: readonly ContentOverviewItem[]): readonly GroupedContent[] {
  const groups = new Map<string, ContentOverviewItem[]>()

  for (const item of content) {
    groups.set(item.pageUrl, [...(groups.get(item.pageUrl) ?? []), item])
  }

  return [...groups.entries()].map(([pageUrl, items]) => ({ pageUrl, items }))
}

function configurationLabel(item: ContentOverviewItem): string {
  if (item.configurationName !== null && item.configurationName.trim() !== '') {
    return item.configurationName
  }

  return item.configurationRef ?? 'Not configured'
}

function getDisclosureStatus(item: ContentOverviewItem): {
  text: string
  color: BadgeColor
} {
  if (item.disclosureType === null) {
    return { text: 'No disclosure', color: 'gray' }
  }

  if (item.configurationRef === null) {
    return { text: 'Unconfigured', color: 'gray' }
  }

  if (item.isDisclosed === true) {
    return { text: 'Disclosed', color: 'green' }
  }

  if (item.issues.length > 0) {
    return { text: 'Not Disclosed (Issues)', color: 'red' }
  }

  return { text: 'Not Disclosed (Disabled)', color: 'red' }
}

function hasRequiredIssue(issues: ContentOverviewItem['issues']): boolean {
  return issues.some((issue) => issue.impact === 'action_required')
}

function normalizeIssueImpact(impact: string): IssueImpact {
  return impact === 'action_required' ? 'action_required' : 'action_recommended'
}

function FiltersIcon() {
  return (
    <svg
      aria-hidden="true"
      className="veratag-content-overview-section__toolbar-action-icon"
      fill="none"
      viewBox="0 0 24 24"
    >
      <path
        d="M4 6h16M7 12h10M10 18h4"
        stroke="currentColor"
        strokeLinecap="round"
        strokeWidth="2"
      />
    </svg>
  )
}

function IssueIcon({ impact, iconsBaseUrl }: { impact: IssueImpact; iconsBaseUrl: string }) {
  return (
    <MaskIcon
      className="veratag-content-overview-section__issue-icon"
      src={issueImpactIconUrl(impact, iconsBaseUrl)}
    />
  )
}

function IssuesPopover({
  issues,
  iconsBaseUrl,
}: {
  issues: ContentOverviewItem['issues']
  iconsBaseUrl: string
}) {
  const [visible, setVisible] = useState(false)
  const [triggerElement, setTriggerElement] = useState<HTMLButtonElement | null>(null)

  const required = hasRequiredIssue(issues)
  const triggerImpact: IssueImpact = required ? 'action_required' : 'action_recommended'

  const issuesLabel = `Issues (${issues.length})`
  const popoverTitle = `Open issues (${issues.length})`

  return (
    <span className="veratag-content-overview-section__issues-anchor">
      <button
        ref={setTriggerElement}
        className={[
          'veratag-content-overview-section__issues-trigger',
          required
            ? 'veratag-content-overview-section__issues-trigger--required'
            : 'veratag-content-overview-section__issues-trigger--recommended',
        ].join(' ')}
        onClick={() => setVisible((current) => !current)}
        type="button"
      >
        <IssueIcon iconsBaseUrl={iconsBaseUrl} impact={triggerImpact} />
        <span className="veratag-content-overview-section__issues-label">{issuesLabel}</span>
      </button>

      <Popover
        anchor={triggerElement}
        maxHeight="420px"
        maxWidth="480px"
        minWidth="280px"
        onVisibleChange={setVisible}
        visible={visible}
      >
        <div className="veratag-content-overview-section__issues-popover">
          <div className="veratag-content-overview-section__issues-popover-header">
            <span className="veratag-content-overview-section__issues-popover-title">
              {popoverTitle}
            </span>
          </div>

          <div className="veratag-content-overview-section__issues-popover-list">
            {issues.map((issue, index) => {
              const impact = normalizeIssueImpact(issue.impact)
              const isRequired = impact === 'action_required'

              const headline =
                typeof issue.reason === 'string' && issue.reason.trim() !== ''
                  ? toTitleLabel(issue.reason)
                  : isRequired
                    ? 'Action required'
                    : 'Action recommended'

              return (
                <div
                  className={[
                    'veratag-content-overview-section__issue',
                    isRequired
                      ? 'veratag-content-overview-section__issue--required'
                      : 'veratag-content-overview-section__issue--recommended',
                  ].join(' ')}
                  key={`${String(issue.id)}-${issue.reason}-${String(index)}`}
                >
                  <span className="veratag-content-overview-section__issue-icon-wrapper">
                    <IssueIcon iconsBaseUrl={iconsBaseUrl} impact={impact} />
                  </span>

                  <span className="veratag-content-overview-section__issue-copy">
                    <span className="veratag-content-overview-section__issue-headline">
                      {headline}
                    </span>
                    <span className="veratag-content-overview-section__issue-message">
                      {issue.message}
                    </span>
                  </span>
                </div>
              )
            })}
          </div>
        </div>
      </Popover>
    </span>
  )
}

function DisclosureStatusCell({
  item,
  channelDisclosureConfigurationsPath,
  veratagDashboardBaseUrl,
  iconsBaseUrl,
}: {
  item: ContentOverviewItem
  channelDisclosureConfigurationsPath: string | undefined
  veratagDashboardBaseUrl: string
  iconsBaseUrl: string
}) {
  const status = getDisclosureStatus(item)

  if (item.disclosureType === null) {
    return (
      <div className="veratag-content-overview-section__status-cell-body">
        <span className="veratag-content-overview-section__disclosure-badge">
          <Badge color="gray" rounded size="sm" text="No disclosure" />
        </span>
      </div>
    )
  }

  const configurationText = configurationLabel(item)
  const tier = configurationTier(item.configurationRef)
  const configurationColor = configurationTierBadgeColor(tier)
  const configurationIconUrl = configurationTierIconUrl(tier, iconsBaseUrl)

  const configurationUrl =
    item.configurationRef === null
      ? null
      : configurationDashboardUrl(
          item.configurationRef,
          channelDisclosureConfigurationsPath,
          veratagDashboardBaseUrl,
        )

  const configurationBadge = (
    <Badge
      color={configurationColor}
      leftSvgUrl={configurationIconUrl}
      rounded={false}
      size="sm"
      text={configurationText}
    />
  )

  return (
    <div className="veratag-content-overview-section__status-cell-body">
      <div className="veratag-content-overview-section__disclosure-tree">
        <div className="veratag-content-overview-section__disclosure-root">
          <span className="veratag-content-overview-section__disclosure-root-label">
            {disclosureTypeLabel(item.disclosureType)}
          </span>
        </div>

        <div className="veratag-content-overview-section__disclosure-children">
          <div className="veratag-content-overview-section__disclosure-node veratag-content-overview-section__disclosure-badge">
            <Badge color={status.color} rounded size="sm" text={status.text} />
          </div>

          {item.configurationRef !== null ? (
            <div className="veratag-content-overview-section__disclosure-node veratag-content-overview-section__configuration-badge">
              {configurationUrl !== null ? (
                <a
                  className="veratag-content-overview-section__configuration-link"
                  href={configurationUrl}
                  rel="noreferrer"
                  target="_blank"
                  title={`${toTitleLabel(tier)} configuration: ${configurationText}`}
                >
                  {configurationBadge}
                </a>
              ) : (
                <span title={`${toTitleLabel(tier)} configuration: ${configurationText}`}>
                  {configurationBadge}
                </span>
              )}
            </div>
          ) : null}

          {item.issues.length > 0 ? (
            <div className="veratag-content-overview-section__disclosure-node">
              <IssuesPopover iconsBaseUrl={iconsBaseUrl} issues={item.issues} />
            </div>
          ) : null}
        </div>
      </div>
    </div>
  )
}

function ContentRow({
  item,
  channelDisclosureConfigurationsPath,
  veratagDashboardBaseUrl,
  iconsBaseUrl,
}: {
  item: ContentOverviewItem
  channelDisclosureConfigurationsPath: string | undefined
  veratagDashboardBaseUrl: string
  iconsBaseUrl: string
}) {
  const summary = contentDisplaySummary(item.contentDisplay)
  const imageSrc = contentDisplayImageSrc(item.contentDisplay)
  const contentTypeIconSrc = getContentTypeIconUrl(item.contentType)

  return (
    <tr>
      <td>
        <div className="veratag-content-overview-section__content-cell-body">
          <div className="veratag-content-overview-section__content-meta">
            <span className="veratag-content-overview-section__content-type">
              <MaskIcon
                className="veratag-content-overview-section__content-type-icon"
                src={contentTypeIconSrc}
              />
              <span>{contentTypeLabel(item.contentType)}</span>
            </span>

            <span className="veratag-content-overview-section__html-tag">
              &lt;{item.contentHtmlTag}&gt;
            </span>

            <span className="veratag-content-overview-section__content-id" title={item.id}>
              ({item.id})
            </span>
          </div>

          {imageSrc !== null ? (
            <figure className="veratag-content-overview-section__image-preview">
              <img alt={summary} loading="lazy" src={imageSrc} />
              {summary !== '' ? <figcaption>{summary}</figcaption> : null}
            </figure>
          ) : summary !== '' ? (
            <p className="veratag-content-overview-section__content-text">{summary}</p>
          ) : null}
        </div>
      </td>

      <td>
        <DisclosureStatusCell
          channelDisclosureConfigurationsPath={channelDisclosureConfigurationsPath}
          iconsBaseUrl={iconsBaseUrl}
          item={item}
          veratagDashboardBaseUrl={veratagDashboardBaseUrl}
        />
      </td>
    </tr>
  )
}

export function ContentOverviewSection({
  storedContentCount,
  storedContentSynchronizedAt,
  veratagDashboardBaseUrl,
}: ContentOverviewSectionProps) {
  const { iconsBaseUrl } = loadVeratagAdminConfig()
  const pageUrlIconSrc = `${iconsBaseUrl}l/globe.svg`

  const [channelDisclosureConfigurationsPath, setChannelDisclosureConfigurationsPath] = useState<
    string | undefined
  >(undefined)

  const [pageUrlOptions, setPageUrlOptions] = useState<readonly ContentOverviewPageUrlOption[]>([])

  const [searchText, setSearchText] = useState('')
  const [debouncedSearchText, setDebouncedSearchText] = useState('')
  const [selectedContentTypes, setSelectedContentTypes] = useState<readonly string[]>([])
  const [selectedDisclosureTypes, setSelectedDisclosureTypes] = useState<readonly string[]>([])
  const [selectedPageUrls, setSelectedPageUrls] = useState<readonly string[]>([])
  const [selectedIsDisclosed, setSelectedIsDisclosed] = useState<boolean | null>(null)
  const [selectedIssueImpacts, setSelectedIssueImpacts] = useState<readonly string[]>([])
  const [sortSelection, setSortSelection] = useState<TableSortSelection | null>(null)
  const [currentPage, setCurrentPage] = useState(1)
  const [linesPerPage, setLinesPerPage] = useState(DEFAULT_LINES_PER_PAGE)
  const [areFiltersVisible, setAreFiltersVisible] = useState(false)
  const [openFilterId, setOpenFilterId] = useState<FilterId | null>(null)
  const [content, setContent] = useState<readonly ContentOverviewItem[]>([])
  const [totalElements, setTotalElements] = useState(0)
  const [totalPages, setTotalPages] = useState(1)
  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState<string | null>(null)
  const searchDebounceRef = useRef<number | null>(null)

  useEffect(() => {
    let isMounted = true

    async function hydrateStaticState() {
      try {
        const [rawDisclosures, pageUrlsResponse] = await Promise.all([
          loadRawDisclosures(),
          loadContentOverviewPageUrls(),
        ])

        if (!isMounted) return

        setChannelDisclosureConfigurationsPath(
          rawDisclosures.channelDetails.paths.channelDisclosureConfigurationsPath,
        )
        setPageUrlOptions(pageUrlsResponse.pageUrls)
      } catch {
        /* Non-fatal. */
      }
    }

    void hydrateStaticState()

    return () => {
      isMounted = false
    }
  }, [])

  useEffect(() => {
    if (searchDebounceRef.current !== null) {
      window.clearTimeout(searchDebounceRef.current)
    }

    searchDebounceRef.current = window.setTimeout(() => {
      setDebouncedSearchText(searchText)
      setCurrentPage(1)
    }, 300)

    return () => {
      if (searchDebounceRef.current !== null) {
        window.clearTimeout(searchDebounceRef.current)
      }
    }
  }, [searchText])

  useEffect(() => {
    let isMounted = true

    async function fetchContentPage() {
      setIsLoading(true)

      try {
        const query: ContentOverviewQuery = {
          searchText: debouncedSearchText,
          contentTypes: selectedContentTypes,
          disclosureTypes: selectedDisclosureTypes,
          pageUrls: selectedPageUrls,
          isDisclosed: selectedIsDisclosed,
          issueImpacts: selectedIssueImpacts,
          sortBy: (sortSelection?.field as ContentOverviewSortField | undefined) ?? null,
          sortDir: (sortSelection?.direction as ContentOverviewSortDirection | undefined) ?? 'desc',
          pageNumber: currentPage,
          pageSize: linesPerPage,
        }

        const page = await loadContentOverviewPage(query)
        if (!isMounted) return

        setContent(page.content)
        setTotalElements(page.totalElements)
        setTotalPages(Math.max(page.totalPages, 1))
        setLoadError(null)
      } catch (error) {
        if (!isMounted) return

        setLoadError(error instanceof Error ? error.message : 'Failed to load content overview.')
      } finally {
        if (isMounted) setIsLoading(false)
      }
    }

    void fetchContentPage()

    return () => {
      isMounted = false
    }
  }, [
    debouncedSearchText,
    selectedContentTypes,
    selectedDisclosureTypes,
    selectedPageUrls,
    selectedIsDisclosed,
    selectedIssueImpacts,
    sortSelection,
    currentPage,
    linesPerPage,
  ])

  const groupedContent = useMemo(() => groupByPageUrl(content), [content])
  const paginationItems = useMemo(
    () => buildPaginationItems(currentPage, totalPages),
    [currentPage, totalPages],
  )

  function toggleFilterPanel(id: FilterId, shouldOpen: boolean) {
    if (shouldOpen) {
      setOpenFilterId(id)
      return
    }

    setOpenFilterId((current) => (current === id ? null : current))
  }

  function toggleFiltersVisibility() {
    setAreFiltersVisible((current) => {
      const next = !current
      if (!next) setOpenFilterId(null)
      return next
    })
  }

  return (
    <section className="veratag-panel veratag-content-overview-section">
      <header className="veratag-content-overview-section__header">
        <h2 className="veratag-content-overview-section__title">Content Overview</h2>
        <p className="veratag-content-overview-section__description">
          Below you can see the content items and their disclosure configurations currently
          registered in the local database of the vera[tag] WordPress plugin. These items are kept
          up to date through pull and push synchronization with the vera[tag] platform, while their
          AI content disclosures are managed in the vera[tag] dashboard.
        </p>

        <p className="veratag-content-overview-section__description">
          The local database of the vera[tag] WordPress plugin currently contains{' '}
          <strong>{String(storedContentCount)}</strong> content items. Last synchronized:{' '}
          <strong>{formatDateTime(storedContentSynchronizedAt)}</strong>.
        </p>
      </header>

      <div className="veratag-content-overview-section__filter-card">
        <div className="veratag-content-overview-section__toolbar-search-row">
          <div className="veratag-content-overview-section__toolbar-search-input">
            <InputText
              id="veratag-content-overview-search"
              onChange={(event) => setSearchText(event.target.value)}
              placeholder="Search content items"
              value={searchText}
            />
          </div>

          <div className="veratag-content-overview-section__toolbar-search-actions">
            <button
              aria-controls="veratag-content-overview-filters"
              aria-expanded={areFiltersVisible}
              className="veratag-content-overview-section__toolbar-action-button"
              onClick={toggleFiltersVisibility}
              type="button"
            >
              <FiltersIcon />
              <span>Filters</span>
            </button>

            <TableSortDropdownButton
              criteria={SORT_CRITERIA}
              onClearClicked={() => {
                setSortSelection(null)
                setCurrentPage(1)
              }}
              onSelectionChange={(selection) => {
                setSortSelection(selection)
                setCurrentPage(1)
              }}
              selection={sortSelection}
            />
          </div>
        </div>

        <div
          aria-hidden={!areFiltersVisible}
          className={[
            'veratag-content-overview-section__filter-region',
            areFiltersVisible ? 'veratag-content-overview-section__filter-region--visible' : '',
            openFilterId !== null
              ? 'veratag-content-overview-section__filter-region--panel-open'
              : '',
          ]
            .filter(Boolean)
            .join(' ')}
          id="veratag-content-overview-filters"
        >
          <div className="veratag-content-overview-section__filter-region-inner">
            <div className="veratag-content-overview-section__filter-buttons">
              <div className="veratag-content-overview-section__filter-heading">
                <span className="veratag-content-overview-section__filter-heading-label">
                  Filters
                </span>
                <span className="veratag-content-overview-section__filter-heading-line" />
              </div>

              <TableFilterButton
                clearAriaLabel="Clear content type filter"
                filterTitle="Content type"
                isOpen={openFilterId === 'contentType'}
                onClearClicked={() => {
                  setSelectedContentTypes([])
                  setCurrentPage(1)
                }}
                onOptionToggled={(option) => {
                  setSelectedContentTypes(toggleMultiSelection(selectedContentTypes, option))
                  setCurrentPage(1)
                }}
                onPanelToggled={(isOpen) => toggleFilterPanel('contentType', isOpen)}
                openAriaLabel="Open content type filter"
                options={CONTENT_TYPE_OPTIONS}
                renderOption={({ option }) => {
                  const label = contentTypeLabel(option)

                  return (
                    <span className="veratag-content-overview-section__filter-option">
                      <MaskIcon
                        className="veratag-content-overview-section__filter-option-mask-icon"
                        src={getContentTypeIconUrl(option)}
                      />
                      <span title={label}>{label}</span>
                    </span>
                  )
                }}
                selectedCount={selectedContentTypes.length}
                selectedOptions={selectedContentTypes}
                selectionMode="multiple"
              />

              <TableFilterButton
                clearAriaLabel="Clear disclosure type filter"
                filterTitle="Disclosure type"
                isOpen={openFilterId === 'disclosureType'}
                onClearClicked={() => {
                  setSelectedDisclosureTypes([])
                  setCurrentPage(1)
                }}
                onOptionToggled={(option) => {
                  setSelectedDisclosureTypes(toggleMultiSelection(selectedDisclosureTypes, option))
                  setCurrentPage(1)
                }}
                onPanelToggled={(isOpen) => toggleFilterPanel('disclosureType', isOpen)}
                openAriaLabel="Open disclosure type filter"
                options={DISCLOSURE_TYPE_OPTIONS}
                renderOption={({ option }) => {
                  const label = disclosureTypeLabel(option)

                  return (
                    <span className="veratag-content-overview-section__filter-option">
                      <span title={label}>{label}</span>
                    </span>
                  )
                }}
                selectedCount={selectedDisclosureTypes.length}
                selectedOptions={selectedDisclosureTypes}
                selectionMode="multiple"
              />

              <TableFilterButton
                clearAriaLabel="Clear page URL filter"
                filterTitle="Page URL"
                isOpen={openFilterId === 'pageUrl'}
                onClearClicked={() => {
                  setSelectedPageUrls([])
                  setCurrentPage(1)
                }}
                onOptionToggled={(option) => {
                  setSelectedPageUrls(toggleMultiSelection(selectedPageUrls, option))
                  setCurrentPage(1)
                }}
                onPanelToggled={(isOpen) => toggleFilterPanel('pageUrl', isOpen)}
                openAriaLabel="Open page URL filter"
                options={pageUrlOptions.map((option) => option.pageUrl)}
                renderOption={({ option }) => (
                  <span className="veratag-content-overview-section__filter-option veratag-content-overview-section__filter-option--page-url">
                    <MaskIcon
                      className="veratag-content-overview-section__filter-option-mask-icon"
                      src={pageUrlIconSrc}
                    />
                    <span
                      className="veratag-content-overview-section__filter-option-page-url-text"
                      title={option}
                    >
                      {option}
                    </span>
                  </span>
                )}
                selectedCount={selectedPageUrls.length}
                selectedOptions={selectedPageUrls}
                selectionMode="multiple"
              />

              <TableFilterButton
                clearAriaLabel="Clear currently served filter"
                filterTitle="Currently served"
                isOpen={openFilterId === 'isDisclosed'}
                onClearClicked={() => {
                  setSelectedIsDisclosed(null)
                  setCurrentPage(1)
                }}
                onOptionToggled={(option) => {
                  setSelectedIsDisclosed(option === 'true')
                  setCurrentPage(1)
                }}
                onPanelToggled={(isOpen) => toggleFilterPanel('isDisclosed', isOpen)}
                openAriaLabel="Open currently served filter"
                options={IS_DISCLOSED_OPTIONS}
                renderOption={({ option }) => <span>{option === 'true' ? 'Yes' : 'No'}</span>}
                selectedCount={selectedIsDisclosed === null ? 0 : 1}
                selectedOptions={selectedIsDisclosed === null ? [] : [String(selectedIsDisclosed)]}
                selectionMode="single"
              />

              <TableFilterButton
                clearAriaLabel="Clear issue impact filter"
                filterTitle="Issue impact"
                isOpen={openFilterId === 'issueImpact'}
                onClearClicked={() => {
                  setSelectedIssueImpacts([])
                  setCurrentPage(1)
                }}
                onOptionToggled={(option) => {
                  setSelectedIssueImpacts(toggleMultiSelection(selectedIssueImpacts, option))
                  setCurrentPage(1)
                }}
                onPanelToggled={(isOpen) => toggleFilterPanel('issueImpact', isOpen)}
                openAriaLabel="Open issue impact filter"
                options={ISSUE_IMPACT_OPTIONS}
                renderOption={({ option }) => {
                  if (option === 'none') return <span>No issues</span>

                  const impact = normalizeIssueImpact(option)
                  const required = impact === 'action_required'

                  return (
                    <span
                      className={[
                        'veratag-content-overview-section__filter-option',
                        required
                          ? 'veratag-content-overview-section__filter-option--required'
                          : 'veratag-content-overview-section__filter-option--recommended',
                      ].join(' ')}
                    >
                      <IssueIcon iconsBaseUrl={iconsBaseUrl} impact={impact} />
                      <span>{toTitleLabel(option)}</span>
                    </span>
                  )
                }}
                selectedCount={selectedIssueImpacts.length}
                selectedOptions={selectedIssueImpacts}
                selectionMode="multiple"
              />
            </div>
          </div>
        </div>
      </div>

      <div aria-busy={isLoading} className="veratag-content-overview-section__content-card">
        {!isLoading && loadError !== null ? (
          <div className="veratag-content-overview-section__content-message">
            <Alert label="Error:" tone="error">
              {loadError}
            </Alert>
          </div>
        ) : (
          <>
            <div
              className={[
                'veratag-content-overview-section__table-scroll',
                isLoading ? 'veratag-content-overview-section__table-scroll--loading' : '',
              ]
                .filter(Boolean)
                .join(' ')}
            >
              {isLoading ? (
                <div
                  aria-live="polite"
                  className="veratag-content-overview-section__loading-overlay"
                >
                  <Spinner ariaLabel="Loading content" color="brand" size="md" />
                </div>
              ) : null}

              <table className="veratag-content-overview-section__content-table">
                <thead>
                  <tr>
                    <th className="veratag-content-overview-section__content-column" scope="col">
                      Content
                    </th>
                    <th className="veratag-content-overview-section__status-column" scope="col">
                      Disclosure
                    </th>
                  </tr>
                </thead>

                <tbody>
                  {groupedContent.map((group) => (
                    <Fragment key={group.pageUrl}>
                      <tr className="veratag-content-overview-section__group-row">
                        <td colSpan={2}>
                          <span
                            className="veratag-content-overview-section__group-url"
                            title={group.pageUrl}
                          >
                            <MaskIcon
                              className="veratag-content-overview-section__page-url-icon"
                              src={pageUrlIconSrc}
                            />
                            <span className="veratag-content-overview-section__group-url-text">
                              {group.pageUrl}
                            </span>
                          </span>
                        </td>
                      </tr>

                      {group.items.map((item) => (
                        <ContentRow
                          channelDisclosureConfigurationsPath={channelDisclosureConfigurationsPath}
                          iconsBaseUrl={iconsBaseUrl}
                          item={item}
                          key={item.id}
                          veratagDashboardBaseUrl={veratagDashboardBaseUrl}
                        />
                      ))}
                    </Fragment>
                  ))}

                  {!isLoading && groupedContent.length === 0 ? (
                    <tr>
                      <td className="veratag-content-overview-section__empty-state" colSpan={2}>
                        No content items found.
                      </td>
                    </tr>
                  ) : null}
                </tbody>
              </table>
            </div>

            <div className="veratag-content-overview-section__footer">
              <TableNavigationFooter
                currentPage={currentPage}
                hasNextPage={currentPage < totalPages}
                hasPreviousPage={currentPage > 1}
                linesPerPage={linesPerPage}
                linesPerPageOptions={LINES_PER_PAGE_OPTIONS}
                onLinesPerPageChange={(value) => {
                  setLinesPerPage(value)
                  setCurrentPage(1)
                }}
                onNextPageClicked={() => setCurrentPage((page) => Math.min(totalPages, page + 1))}
                onPageSelected={(page) => setCurrentPage(page)}
                onPreviousPageClicked={() => setCurrentPage((page) => Math.max(1, page - 1))}
                paginationItems={paginationItems}
                totalCount={totalElements}
              />
            </div>
          </>
        )}
      </div>
    </section>
  )
}
