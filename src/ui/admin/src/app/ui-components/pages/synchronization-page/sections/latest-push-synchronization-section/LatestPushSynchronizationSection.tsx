import { useEffect, useMemo, useState } from 'react'
import {
  loadPushReceivedLogPage,
  type PushApiStatus,
  type PushReceivedLogEntry,
} from '../../../../../api/adminUiBridge'
import { loadVeratagAdminConfig } from '../../../../../config/loadVeratagAdminConfig'
import {
  TableNavigationFooter,
  type TableNavigationFooterItem,
} from '../../../../shared/table/table-navigation-footer/TableNavigationFooter'
import { Alert } from '../../../../shared/alert/Alert'
import { useNotifications } from '../../../../shared/notifications/notification-provider/notificationContext'
import '../../../../shared/status-badge/status-badge.css'
import './latest-push-synchronization-section.css'

const DEFAULT_PAGE_SIZE = 5
const PAGE_SIZE_OPTIONS: readonly number[] = [5, 10, 25, 50]

function formatDateTime(value: string): string {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return new Intl.DateTimeFormat(undefined, {
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(date)
}

type PushApiStatusBadge = {
  tone: 'warning' | 'error' | 'success'
  text: string
}

function resolvePushApiStatusBadge(status: PushApiStatus): PushApiStatusBadge {
  if (!status.configured) return { tone: 'warning', text: 'Not configured' }
  if (!status.enabled) return { tone: 'error', text: 'Disabled' }
  if (status.lastPushAttemptStatus === 'FAILURE') return { tone: 'error', text: 'Error' }
  return { tone: 'success', text: 'Healthy' }
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
  if (windowStart > 2) items.push({ type: 'ellipsis', id: 'ellipsis-left' })
  for (let page = windowStart; page <= windowEnd; page += 1)
    items.push({ type: 'page', value: page })
  if (windowEnd < totalPages - 1) items.push({ type: 'ellipsis', id: 'ellipsis-right' })
  items.push({ type: 'page', value: totalPages })
  return items
}

type LatestPushSynchronizationSectionProps = {
  status: PushApiStatus
}

export function LatestPushSynchronizationSection({
  status,
}: LatestPushSynchronizationSectionProps) {
  const { showError } = useNotifications()
  const config = loadVeratagAdminConfig()
  const [entries, setEntries] = useState<readonly PushReceivedLogEntry[]>([])
  const [currentPage, setCurrentPage] = useState(1)
  const [pageSize, setPageSize] = useState(DEFAULT_PAGE_SIZE)
  const [totalElements, setTotalElements] = useState(0)
  const [totalPages, setTotalPages] = useState(1)
  const [isLoading, setIsLoading] = useState(true)
  const isConfigured = status.configured

  useEffect(() => {
    let isMounted = true
    async function fetchPage() {
      // Nothing to show (and nothing to fetch) until push synchronization
      // is actually configured — avoids an unnecessary request and keeps
      // the loading state from flashing in behind the "not configured"
      // message below.
      if (!isConfigured) {
        setEntries([])
        setTotalElements(0)
        setTotalPages(1)
        setIsLoading(false)
        return
      }

      setIsLoading(true)
      try {
        const page = await loadPushReceivedLogPage(currentPage, pageSize)
        if (!isMounted) return
        setEntries(page.content)
        setTotalElements(page.totalElements)
        setTotalPages(Math.max(page.totalPages, 1))
      } catch (error) {
        if (isMounted)
          showError(
            error instanceof Error ? error.message : 'Failed to load recently received pushes.',
          )
      } finally {
        if (isMounted) setIsLoading(false)
      }
    }
    void fetchPage()
    return () => {
      isMounted = false
    }
  }, [currentPage, isConfigured, pageSize, showError])

  const paginationItems = useMemo(
    () => buildPaginationItems(currentPage, totalPages),
    [currentPage, totalPages],
  )
  const statusBadge = resolvePushApiStatusBadge(status)
  const pushErrorMessage =
    status.lastPushAttemptStatus === 'FAILURE'
      ? (status.lastPushAttemptStatusMessage ??
        'The most recent push synchronization attempt failed.')
      : null

  return (
    <section className="veratag-panel veratag-latest-push-synchronization-section">
      <div className="veratag-panel__header">
        <h2 className="veratag-panel__title">Latest Push Synchronization History</h2>
        <span className={`veratag-status-badge veratag-status-badge--${statusBadge.tone}`}>
          {statusBadge.text}
        </span>
      </div>
      <div className="veratag-panel__body">
        <p className="veratag-panel__text">
          Once configured, the vera[tag] platform pushes live data updates to the vera[tag]
          WordPress plugin, which updates its local database to keep the synchronized data up to
          date.
        </p>

        {!isConfigured ? (
          <p className="veratag-panel__text">
            Push synchronization is not configured yet. You can configure it in the{' '}
            <a href={config.settingsPageUrl}>Settings</a> tab.
          </p>
        ) : (
          <>
            <p className="veratag-panel__text">
              The table below shows the push synchronization history since the most recent pull
              synchronization. This history is cleared whenever a new pull synchronization occurs.
            </p>

            <div className="veratag-latest-push-synchronization-section__table-shell">
              <table className="veratag-latest-push-synchronization-section__table">
                <thead>
                  <tr>
                    <th>Received</th>
                    <th>Instruction</th>
                    <th>Reference</th>
                    <th>Applied</th>
                  </tr>
                </thead>
                <tbody>
                  {isLoading ? (
                    <tr>
                      <td colSpan={4}>Loading...</td>
                    </tr>
                  ) : entries.length === 0 ? (
                    <tr>
                      <td colSpan={4}>
                        No push updates have been received from the vera[tag] platform yet.
                      </td>
                    </tr>
                  ) : (
                    entries.map((entry) => (
                      <tr key={entry.id}>
                        <td>{formatDateTime(entry.receivedAt)}</td>
                        <td>{entry.instruction}</td>
                        <td>{entry.ref ?? '—'}</td>
                        <td>
                          <span
                            className={`veratag-status-badge veratag-status-badge--${entry.appliedSuccessfully ? 'success' : 'error'}`}
                          >
                            {entry.appliedSuccessfully
                              ? 'Applied'
                              : (entry.errorMessage ?? 'Failed')}
                          </span>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
            <TableNavigationFooter
              totalCount={totalElements}
              linesPerPage={pageSize}
              linesPerPageOptions={PAGE_SIZE_OPTIONS}
              hasPreviousPage={currentPage > 1}
              hasNextPage={currentPage < totalPages}
              currentPage={currentPage}
              paginationItems={paginationItems}
              onLinesPerPageChange={(value) => {
                setPageSize(value)
                setCurrentPage(1)
              }}
              onPreviousPageClicked={() => setCurrentPage((page) => Math.max(1, page - 1))}
              onNextPageClicked={() => setCurrentPage((page) => Math.min(totalPages, page + 1))}
              onPageSelected={(page) => setCurrentPage(page)}
            />
            {pushErrorMessage === null ? null : (
              <Alert label="Error" tone="error">
                {pushErrorMessage}
              </Alert>
            )}
          </>
        )}
      </div>
    </section>
  )
}
