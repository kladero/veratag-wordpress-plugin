import { useCallback, useEffect, useRef, useState } from 'react'
import { loadVeratagAdminConfig } from '../../../../config/loadVeratagAdminConfig'
import './table-navigation-footer.css'

export interface TableNavigationFooterPageItem {
  type: 'page'
  value: number
}

export interface TableNavigationFooterEllipsisItem {
  type: 'ellipsis'
  id: string
}

export type TableNavigationFooterItem =
  | TableNavigationFooterPageItem
  | TableNavigationFooterEllipsisItem

type TableNavigationFooterProps = {
  totalCount: number
  linesPerPage: number
  linesPerPageOptions: readonly number[]
  hasPreviousPage: boolean
  hasNextPage: boolean
  currentPage: number
  paginationItems: readonly TableNavigationFooterItem[]

  layout?: 'row' | 'column'

  onLinesPerPageChange?: (linesPerPage: number) => void
  onPreviousPageClicked?: () => void
  onNextPageClicked?: () => void
  onPageSelected?: (page: number) => void
}

const VIEWPORT_GAP_PX = 12
const PANEL_OFFSET_FROM_TRIGGER_PX = 8
const DEFAULT_PANEL_MAX_HEIGHT_PX = 264

const PANEL_LEAVE_DURATION_MS = 140

export function TableNavigationFooter({
  totalCount,
  linesPerPage,
  linesPerPageOptions,
  hasPreviousPage,
  hasNextPage,
  currentPage,
  paginationItems,
  layout = 'row',
  onLinesPerPageChange,
  onPreviousPageClicked,
  onNextPageClicked,
  onPageSelected,
}: TableNavigationFooterProps) {
  const rowsDropdownRootRef = useRef<HTMLDivElement | null>(null)
  const rowsDropdownPanelRef = useRef<HTMLDivElement | null>(null)

  const layoutFrameRef = useRef<number | null>(null)
  const animationFrameRef = useRef<number | null>(null)
  const closeTimerRef = useRef<number | null>(null)

  const [isRowsDropdownOpen, setIsRowsDropdownOpen] = useState(false)

  const [rowsPanelOffsetLeftPx, setRowsPanelOffsetLeftPx] = useState(0)

  const [rowsPanelOpensUpward, setRowsPanelOpensUpward] = useState(false)

  const [rowsPanelMaxHeight, setRowsPanelMaxHeight] = useState(`${DEFAULT_PANEL_MAX_HEIGHT_PX}px`)

  const [shouldRenderRowsPanel, setShouldRenderRowsPanel] = useState(false)

  const [rowsPanelVisible, setRowsPanelVisible] = useState(false)

  const { iconsBaseUrl } = loadVeratagAdminConfig()

  /*
   * Replace these filenames later.
   */
  const rowsChevronIconSrc = `${iconsBaseUrl}l/chevron-down.svg`
  const previousPageIconSrc = `${iconsBaseUrl}l/chevron-left.svg`
  const nextPageIconSrc = `${iconsBaseUrl}l/chevron-right.svg`

  const clamp = useCallback((value: number, min: number, max: number) => {
    if (value < min) {
      return min
    }

    if (value > max) {
      return max
    }

    return value
  }, [])

  const resetPanelLayout = useCallback(() => {
    setRowsPanelOffsetLeftPx(0)
    setRowsPanelOpensUpward(false)
    setRowsPanelMaxHeight(`${DEFAULT_PANEL_MAX_HEIGHT_PX}px`)
  }, [])

  const applyVerticalLayoutForRootRect = useCallback(
    (rootRect: DOMRect) => {
      const viewportHeight = window.innerHeight

      const availableBelow =
        viewportHeight - rootRect.bottom - VIEWPORT_GAP_PX - PANEL_OFFSET_FROM_TRIGGER_PX

      const availableAbove = rootRect.top - VIEWPORT_GAP_PX - PANEL_OFFSET_FROM_TRIGGER_PX

      const shouldOpenUpward = availableBelow < 120 && availableAbove > availableBelow

      setRowsPanelOpensUpward(shouldOpenUpward)

      const availableVerticalSpace = shouldOpenUpward ? availableAbove : availableBelow

      const maxHeight = clamp(Math.floor(availableVerticalSpace), 96, DEFAULT_PANEL_MAX_HEIGHT_PX)

      setRowsPanelMaxHeight(`${maxHeight}px`)
    },
    [clamp],
  )

  const preparePanelLayoutForOpen = useCallback(() => {
    const rootElement = rowsDropdownRootRef.current

    if (!rootElement) {
      return
    }

    applyVerticalLayoutForRootRect(rootElement.getBoundingClientRect())
  }, [applyVerticalLayoutForRootRect])

  const updatePanelLayout = useCallback(() => {
    if (!isRowsDropdownOpen) {
      return
    }

    const rootElement = rowsDropdownRootRef.current
    const panelElement = rowsDropdownPanelRef.current

    if (!rootElement || !panelElement) {
      return
    }

    const rootRect = rootElement.getBoundingClientRect()
    const panelRect = panelElement.getBoundingClientRect()

    const viewportWidth = window.innerWidth
    const panelWidth = panelRect.width

    const rightAlignedLeft = rootRect.right - panelWidth

    const leftAlignedLeft = rootRect.left

    const minLeft = VIEWPORT_GAP_PX

    const maxLeft = Math.max(minLeft, viewportWidth - VIEWPORT_GAP_PX - panelWidth)

    const leftAlignFits = leftAlignedLeft >= minLeft && leftAlignedLeft <= maxLeft

    const rightAlignFits = rightAlignedLeft >= minLeft && rightAlignedLeft <= maxLeft

    const desiredLeft = leftAlignFits
      ? leftAlignedLeft
      : rightAlignFits
        ? rightAlignedLeft
        : clamp(leftAlignedLeft, minLeft, maxLeft)

    setRowsPanelOffsetLeftPx(desiredLeft - rootRect.left)

    applyVerticalLayoutForRootRect(rootRect)
  }, [applyVerticalLayoutForRootRect, clamp, isRowsDropdownOpen])

  const cancelScheduledLayoutUpdate = useCallback(() => {
    if (layoutFrameRef.current === null) {
      return
    }

    window.cancelAnimationFrame(layoutFrameRef.current)

    layoutFrameRef.current = null
  }, [])

  const schedulePanelLayoutUpdate = useCallback(() => {
    cancelScheduledLayoutUpdate()

    layoutFrameRef.current = window.requestAnimationFrame(() => {
      layoutFrameRef.current = null
      updatePanelLayout()
    })
  }, [cancelScheduledLayoutUpdate, updatePanelLayout])

  const closeRowsDropdown = useCallback(() => {
    setIsRowsDropdownOpen(false)
    cancelScheduledLayoutUpdate()
    resetPanelLayout()
  }, [cancelScheduledLayoutUpdate, resetPanelLayout])

  function handleRowsDropdownToggle() {
    if (isRowsDropdownOpen) {
      closeRowsDropdown()
      return
    }

    preparePanelLayoutForOpen()
    setIsRowsDropdownOpen(true)
  }

  function handleRowsOptionSelected(option: number) {
    const selectedOption = linesPerPageOptions.find((candidate) => candidate === option)

    if (selectedOption === undefined) {
      throw new Error(`Unknown lines per page option: ${option}`)
    }

    closeRowsDropdown()

    if (selectedOption === linesPerPage) {
      return
    }

    onLinesPerPageChange?.(selectedOption)
  }

  function handlePreviousPage() {
    if (!hasPreviousPage) {
      return
    }

    onPreviousPageClicked?.()
  }

  function handleNextPage() {
    if (!hasNextPage) {
      return
    }

    onNextPageClicked?.()
  }

  function handleGoToPage(page: number) {
    if (page === currentPage) {
      return
    }

    onPageSelected?.(page)
  }

  /*
   * Mount/unmount panel while preserving the Angular
   * enter/leave animation.
   */
  useEffect(() => {
    if (closeTimerRef.current !== null) {
      window.clearTimeout(closeTimerRef.current)
      closeTimerRef.current = null
    }

    if (animationFrameRef.current !== null) {
      window.cancelAnimationFrame(animationFrameRef.current)
      animationFrameRef.current = null
    }

    if (isRowsDropdownOpen) {
      animationFrameRef.current = window.requestAnimationFrame(() => {
        setShouldRenderRowsPanel(true)

        animationFrameRef.current = window.requestAnimationFrame(() => {
          setRowsPanelVisible(true)
          schedulePanelLayoutUpdate()
          animationFrameRef.current = null
        })
      })

      return
    }

    animationFrameRef.current = window.requestAnimationFrame(() => {
      setRowsPanelVisible(false)

      closeTimerRef.current = window.setTimeout(() => {
        setShouldRenderRowsPanel(false)
        closeTimerRef.current = null
      }, PANEL_LEAVE_DURATION_MS)

      animationFrameRef.current = null
    })
  }, [isRowsDropdownOpen, schedulePanelLayoutUpdate])

  /*
   * Outside click.
   */
  useEffect(() => {
    function handleDocumentClick(event: globalThis.MouseEvent) {
      if (!isRowsDropdownOpen) {
        return
      }

      const rootElement = rowsDropdownRootRef.current

      if (!rootElement) {
        return
      }

      const target = event.target

      if (!(target instanceof Node)) {
        return
      }

      if (rootElement.contains(target)) {
        return
      }

      closeRowsDropdown()
    }

    document.addEventListener('click', handleDocumentClick)

    return () => {
      document.removeEventListener('click', handleDocumentClick)
    }
  }, [closeRowsDropdown, isRowsDropdownOpen])

  /*
   * Escape closes dropdown.
   */
  useEffect(() => {
    function handleKeyDown(event: globalThis.KeyboardEvent) {
      if (event.key !== 'Escape' || !isRowsDropdownOpen) {
        return
      }

      closeRowsDropdown()
    }

    document.addEventListener('keydown', handleKeyDown)

    return () => {
      document.removeEventListener('keydown', handleKeyDown)
    }
  }, [closeRowsDropdown, isRowsDropdownOpen])

  /*
   * Reposition on scroll and resize.
   */
  useEffect(() => {
    if (!isRowsDropdownOpen) {
      return
    }

    function handleViewportChange() {
      schedulePanelLayoutUpdate()
    }

    window.addEventListener('resize', handleViewportChange)

    window.addEventListener('scroll', handleViewportChange)

    return () => {
      window.removeEventListener('resize', handleViewportChange)

      window.removeEventListener('scroll', handleViewportChange)
    }
  }, [isRowsDropdownOpen, schedulePanelLayoutUpdate])

  /*
   * Cleanup requestAnimationFrame/timeouts.
   */
  useEffect(() => {
    return () => {
      cancelScheduledLayoutUpdate()

      if (animationFrameRef.current !== null) {
        window.cancelAnimationFrame(animationFrameRef.current)
      }

      if (closeTimerRef.current !== null) {
        window.clearTimeout(closeTimerRef.current)
      }
    }
  }, [cancelScheduledLayoutUpdate])

  const className = [
    'veratag-table-navigation-footer',
    layout === 'column'
      ? 'veratag-table-navigation-footer--column-layout'
      : 'veratag-table-navigation-footer--row-layout',
  ].join(' ')

  return (
    <div className={className}>
      <div className="veratag-table-navigation-footer__summary">
        <span>Total entries:</span>
        <strong>{totalCount}</strong>
      </div>

      <div className="veratag-table-navigation-footer__controls">
        <div className="veratag-table-navigation-footer__rows-control">
          <span className="veratag-table-navigation-footer__rows-label">Rows per page</span>

          <div className="veratag-table-navigation-footer__rows-dropdown" ref={rowsDropdownRootRef}>
            <button
              aria-expanded={isRowsDropdownOpen}
              aria-haspopup="listbox"
              aria-label="Select rows per page"
              className="veratag-table-navigation-footer__rows-trigger"
              onClick={handleRowsDropdownToggle}
              type="button"
            >
              <span className="veratag-table-navigation-footer__rows-trigger-value">
                {linesPerPage}
              </span>

              <img
                alt=""
                aria-hidden="true"
                className={[
                  'veratag-table-navigation-footer__rows-chevron',
                  isRowsDropdownOpen ? 'veratag-table-navigation-footer__rows-chevron--open' : '',
                ]
                  .filter(Boolean)
                  .join(' ')}
                src={rowsChevronIconSrc}
              />
            </button>

            {shouldRenderRowsPanel ? (
              <div
                className={[
                  'veratag-table-navigation-footer__rows-panel',
                  rowsPanelVisible ? 'veratag-table-navigation-footer__rows-panel--visible' : '',
                  !isRowsDropdownOpen ? 'veratag-table-navigation-footer__rows-panel--closing' : '',
                  rowsPanelOpensUpward ? 'veratag-table-navigation-footer__rows-panel--upward' : '',
                ]
                  .filter(Boolean)
                  .join(' ')}
                ref={rowsDropdownPanelRef}
                role="listbox"
                style={{
                  left: rowsPanelOffsetLeftPx,
                  maxHeight: rowsPanelMaxHeight,
                }}
              >
                <div className="veratag-table-navigation-footer__rows-options">
                  {linesPerPageOptions.map((option) => {
                    const isActive = option === linesPerPage

                    return (
                      <button
                        aria-selected={isActive}
                        className={[
                          'veratag-table-navigation-footer__rows-option',
                          isActive ? 'veratag-table-navigation-footer__rows-option--active' : '',
                        ]
                          .filter(Boolean)
                          .join(' ')}
                        key={option}
                        onClick={() => handleRowsOptionSelected(option)}
                        role="option"
                        type="button"
                      >
                        <span>{option}</span>
                      </button>
                    )
                  })}
                </div>
              </div>
            ) : null}
          </div>
        </div>

        <nav aria-label="Pagination" className="veratag-table-navigation-footer__pagination">
          <button
            aria-label="Previous page"
            className="veratag-table-navigation-footer__page-nav-button"
            disabled={!hasPreviousPage}
            onClick={handlePreviousPage}
            type="button"
          >
            <img
              alt=""
              aria-hidden="true"
              className="veratag-table-navigation-footer__page-nav-icon"
              src={previousPageIconSrc}
            />
          </button>

          {paginationItems.map((paginationItem) => {
            if (paginationItem.type === 'ellipsis') {
              return (
                <span
                  aria-hidden="true"
                  className="veratag-table-navigation-footer__page-ellipsis"
                  key={paginationItem.id}
                >
                  ...
                </span>
              )
            }

            const isActive = paginationItem.value === currentPage

            return (
              <button
                aria-current={isActive ? 'page' : undefined}
                aria-label={`Page ${paginationItem.value}`}
                className={[
                  'veratag-table-navigation-footer__page-button',
                  isActive
                    ? 'veratag-table-navigation-footer__page-button--active'
                    : 'veratag-table-navigation-footer__page-button--ghost',
                ].join(' ')}
                key={`page-${paginationItem.value}`}
                onClick={() => handleGoToPage(paginationItem.value)}
                type="button"
              >
                {paginationItem.value}
              </button>
            )
          })}

          <button
            aria-label="Next page"
            className="veratag-table-navigation-footer__page-nav-button"
            disabled={!hasNextPage}
            onClick={handleNextPage}
            type="button"
          >
            <img
              alt=""
              aria-hidden="true"
              className="veratag-table-navigation-footer__page-nav-icon"
              src={nextPageIconSrc}
            />
          </button>
        </nav>
      </div>
    </div>
  )
}
