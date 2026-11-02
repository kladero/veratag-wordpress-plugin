import {
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
  type CSSProperties,
  type MouseEvent,
} from 'react'

import { loadVeratagAdminConfig } from '../../../../config/loadVeratagAdminConfig'

import './table-sort-dropdown-button.css'

export type TableSortDirection = 'asc' | 'desc'

export interface TableSortCriterion {
  field: string
  label: string
}

export interface TableSortDirectionOption {
  direction: TableSortDirection
  label: string
  iconSrc?: string
}

export interface TableSortSelection {
  field: string
  direction: TableSortDirection
}

export type SortTriggerAlignment = 'start' | 'center'

type PanelAnchor = 'left' | 'right'

type TableSortDropdownButtonProps = {
  criteria: readonly TableSortCriterion[]
  selection?: TableSortSelection | null
  defaultLabel?: string
  openAriaLabel?: string
  clearAriaLabel?: string
  menuAriaLabel?: string
  fullWidth?: boolean
  triggerAlignment?: SortTriggerAlignment
  directionOptions?: readonly TableSortDirectionOption[]
  onSelectionChange?: (selection: TableSortSelection) => void
  onClearClicked?: () => void
}

const DEFAULT_DIRECTION_OPTIONS: readonly TableSortDirectionOption[] = [
  {
    direction: 'asc',
    label: 'Asc',
  },
  {
    direction: 'desc',
    label: 'Desc',
  },
]

const VIEWPORT_GAP_PX = 12
const PANEL_OFFSET_FROM_TRIGGER_PX = 7

const DEFAULT_PANEL_MAX_HEIGHT_PX = 352
const DEFAULT_PANEL_WIDTH_REM = 15
const DEFAULT_PANEL_WIDTH_PX = 240

const SUBMENU_WIDTH_PX = 176
const SUBMENU_OFFSET_FROM_ROW_PX = 6
const SUBMENU_OPTION_HEIGHT_PX = 38
const SUBMENU_PADDING_PX = 16

const NARROW_VIEWPORT_BREAKPOINT_PX = 700

const PANEL_DOWNWARD_LEAVE_DURATION_MS = 170
const PANEL_UPWARD_LEAVE_DURATION_MS = 120

export function TableSortDropdownButton({
  criteria,
  selection = null,
  defaultLabel = 'Sort',
  openAriaLabel = 'Open sorting',
  clearAriaLabel = 'Clear sorting',
  menuAriaLabel = 'Sort options',
  fullWidth = false,
  triggerAlignment = 'start',
  directionOptions = DEFAULT_DIRECTION_OPTIONS,
  onSelectionChange,
  onClearClicked,
}: TableSortDropdownButtonProps) {
  const sortDropdownRootRef = useRef<HTMLDivElement | null>(null)
  const sortRowRefs = useRef<Map<string, HTMLDivElement>>(new Map())

  const layoutFrameRef = useRef<number | null>(null)
  const animationFrameRef = useRef<number | null>(null)
  const closeTimerRef = useRef<number | null>(null)

  const [isMenuOpen, setIsMenuOpen] = useState(false)
  const [shouldRenderMenu, setShouldRenderMenu] = useState(false)
  const [menuVisible, setMenuVisible] = useState(false)

  const [hoveredField, setHoveredFieldState] = useState<string | null>(null)

  const [panelAnchor, setPanelAnchor] = useState<PanelAnchor>('left')
  const [panelOpensUpward, setPanelOpensUpward] = useState(false)

  const [panelMaxWidthPx, setPanelMaxWidthPx] = useState(DEFAULT_PANEL_WIDTH_PX)

  const [panelMaxHeight, setPanelMaxHeight] = useState(`${DEFAULT_PANEL_MAX_HEIGHT_PX}px`)

  const [submenuOffsetTopPx, setSubmenuOffsetTopPx] = useState(0)
  const [submenuOpensLeft, setSubmenuOpensLeft] = useState(false)
  const [submenuIsStacked, setSubmenuIsStacked] = useState(false)

  const { iconsBaseUrl } = loadVeratagAdminConfig()

  /*
   * Replace these x.svg filenames individually later.
   */
  const sortIconSrc = `${iconsBaseUrl}l/arrow-up-down.svg`
  const sortAscendingIconSrc = `${iconsBaseUrl}l/arrow-up-narrow-wide.svg`
  const sortDescendingIconSrc = `${iconsBaseUrl}l/arrow-down-wide-narrow.svg`

  const triggerChevronIconSrc = `${iconsBaseUrl}l/chevron-down.svg`
  const clearIconSrc = `${iconsBaseUrl}l/x.svg`

  const rowChevronRightIconSrc = `${iconsBaseUrl}l/chevron-right.svg`
  const rowChevronLeftIconSrc = `${iconsBaseUrl}l/chevron-left.svg`
  const rowChevronDownIconSrc = `${iconsBaseUrl}l/chevron-down.svg`

  const clamp = useCallback((value: number, min: number, max: number) => {
    if (value < min) {
      return min
    }

    if (value > max) {
      return max
    }

    return value
  }, [])

  const stackedSubmenuMaxHeight = useMemo(() => {
    const optionCount = directionOptions.length

    const optionSpacingPx = optionCount > 1 ? (optionCount - 1) * 3 : 0

    const estimatedHeightPx =
      SUBMENU_PADDING_PX + optionCount * SUBMENU_OPTION_HEIGHT_PX + optionSpacingPx

    return `${estimatedHeightPx}px`
  }, [directionOptions])

  const sortButtonLabel = useMemo(() => {
    if (selection === null) {
      return defaultLabel
    }

    const criterion = criteria.find((item) => item.field === selection.field)

    const criterionLabel = criterion?.label ?? defaultLabel

    const selectedDirectionOption = directionOptions.find(
      (option) => option.direction === selection.direction,
    )

    const directionLabel =
      selectedDirectionOption?.label ?? (selection.direction === 'asc' ? 'Asc' : 'Desc')

    return `${criterionLabel}: ${directionLabel}`
  }, [criteria, defaultLabel, directionOptions, selection])

  const triggerIconSrc = useMemo(() => {
    if (selection === null) {
      return sortIconSrc
    }

    const selectedDirectionOption = directionOptions.find(
      (option) => option.direction === selection.direction,
    )

    if (selectedDirectionOption?.iconSrc) {
      return selectedDirectionOption.iconSrc
    }

    return selection.direction === 'asc' ? sortAscendingIconSrc : sortDescendingIconSrc
  }, [directionOptions, selection, sortAscendingIconSrc, sortDescendingIconSrc, sortIconSrc])

  const rowChevronIconSrc = submenuIsStacked
    ? rowChevronDownIconSrc
    : submenuOpensLeft
      ? rowChevronLeftIconSrc
      : rowChevronRightIconSrc

  function getDirectionIconSrc(option: TableSortDirectionOption) {
    if (option.iconSrc) {
      return option.iconSrc
    }

    return option.direction === 'asc' ? sortAscendingIconSrc : sortDescendingIconSrc
  }

  function isFieldSelected(field: string) {
    return selection !== null && selection.field === field
  }

  function isDirectionSelected(field: string, direction: TableSortDirection) {
    return selection !== null && selection.field === field && selection.direction === direction
  }

  const resetPanelLayout = useCallback(() => {
    setPanelAnchor('left')
    setPanelOpensUpward(false)
    setPanelMaxWidthPx(DEFAULT_PANEL_WIDTH_PX)
    setPanelMaxHeight(`${DEFAULT_PANEL_MAX_HEIGHT_PX}px`)
  }, [])

  const resetSubmenuLayout = useCallback(() => {
    setSubmenuOffsetTopPx(0)
    setSubmenuOpensLeft(false)
    setSubmenuIsStacked(false)
  }, [])

  const getPreferredPanelWidthPx = useCallback(() => {
    const documentElement = document.documentElement

    if (!documentElement) {
      return DEFAULT_PANEL_WIDTH_PX
    }

    const rootFontSizePx = Number.parseFloat(window.getComputedStyle(documentElement).fontSize)

    if (!Number.isFinite(rootFontSizePx) || rootFontSizePx <= 0) {
      return DEFAULT_PANEL_WIDTH_PX
    }

    return DEFAULT_PANEL_WIDTH_REM * rootFontSizePx
  }, [])

  const getPanelAnchorForRootRect = useCallback(
    (rootRect: DOMRect, panelWidth: number): PanelAnchor => {
      const viewportWidth = window.innerWidth

      const spaceIfOpenRight = Math.max(0, viewportWidth - VIEWPORT_GAP_PX - rootRect.left)

      const spaceIfOpenLeft = Math.max(0, rootRect.right - VIEWPORT_GAP_PX)

      const openRightFits = spaceIfOpenRight >= panelWidth

      const openLeftFits = spaceIfOpenLeft >= panelWidth

      if (openRightFits && !openLeftFits) {
        return 'left'
      }

      if (!openRightFits && openLeftFits) {
        return 'right'
      }

      return spaceIfOpenRight >= spaceIfOpenLeft ? 'left' : 'right'
    },
    [],
  )

  const getPanelMaxWidth = useCallback(
    (rootRect: DOMRect, anchor: PanelAnchor) => {
      const viewportWidth = window.innerWidth

      const preferredPanelWidthPx = getPreferredPanelWidthPx()

      const availableWidthByAnchor =
        anchor === 'left'
          ? viewportWidth - VIEWPORT_GAP_PX - rootRect.left
          : rootRect.right - VIEWPORT_GAP_PX

      return Math.max(80, Math.min(preferredPanelWidthPx, Math.floor(availableWidthByAnchor)))
    },
    [getPreferredPanelWidthPx],
  )

  const applyVerticalLayoutForRootRect = useCallback(
    (rootRect: DOMRect) => {
      const viewportHeight = window.innerHeight

      const availableBelow =
        viewportHeight - rootRect.bottom - VIEWPORT_GAP_PX - PANEL_OFFSET_FROM_TRIGGER_PX

      const availableAbove = rootRect.top - VIEWPORT_GAP_PX - PANEL_OFFSET_FROM_TRIGGER_PX

      const shouldOpenUpward = availableBelow < 180 && availableAbove > availableBelow

      setPanelOpensUpward(shouldOpenUpward)

      const availableVerticalSpace = shouldOpenUpward ? availableAbove : availableBelow

      const maxHeight = clamp(Math.floor(availableVerticalSpace), 72, DEFAULT_PANEL_MAX_HEIGHT_PX)

      setPanelMaxHeight(`${maxHeight}px`)
    },
    [clamp],
  )

  const preparePanelLayoutForOpen = useCallback(() => {
    const rootElement = sortDropdownRootRef.current

    if (!rootElement) {
      return
    }

    const rootRect = rootElement.getBoundingClientRect()

    const preferredPanelWidthPx = getPreferredPanelWidthPx()

    const estimatedPanelWidth = Math.max(
      120,
      Math.min(preferredPanelWidthPx, window.innerWidth - VIEWPORT_GAP_PX * 2),
    )

    const anchor = getPanelAnchorForRootRect(rootRect, estimatedPanelWidth)

    setPanelAnchor(anchor)

    setPanelMaxWidthPx(getPanelMaxWidth(rootRect, anchor))

    applyVerticalLayoutForRootRect(rootRect)

    resetSubmenuLayout()
  }, [
    applyVerticalLayoutForRootRect,
    getPanelAnchorForRootRect,
    getPanelMaxWidth,
    getPreferredPanelWidthPx,
    resetSubmenuLayout,
  ])

  const updatePanelLayout = useCallback(() => {
    if (!isMenuOpen) {
      return
    }

    const rootElement = sortDropdownRootRef.current

    if (!rootElement) {
      return
    }

    const rootRect = rootElement.getBoundingClientRect()

    setPanelMaxWidthPx(getPanelMaxWidth(rootRect, panelAnchor))

    applyVerticalLayoutForRootRect(rootRect)
  }, [applyVerticalLayoutForRootRect, getPanelMaxWidth, isMenuOpen, panelAnchor])

  const updateSubmenuLayoutForRow = useCallback(
    (rowElement: HTMLElement) => {
      const viewportWidth = window.innerWidth

      const viewportHeight = window.innerHeight

      const rowRect = rowElement.getBoundingClientRect()

      const effectiveSubmenuWidth = Math.min(SUBMENU_WIDTH_PX, viewportWidth - VIEWPORT_GAP_PX * 2)

      const availableRight =
        viewportWidth - rowRect.right - VIEWPORT_GAP_PX - SUBMENU_OFFSET_FROM_ROW_PX

      const availableLeft = rowRect.left - VIEWPORT_GAP_PX - SUBMENU_OFFSET_FROM_ROW_PX

      const isNarrowViewport = viewportWidth <= NARROW_VIEWPORT_BREAKPOINT_PX

      const hasHorizontalRoom =
        availableRight >= effectiveSubmenuWidth || availableLeft >= effectiveSubmenuWidth

      if (isNarrowViewport || !hasHorizontalRoom) {
        setSubmenuIsStacked(true)
        setSubmenuOpensLeft(false)
        setSubmenuOffsetTopPx(0)

        return
      }

      const opensLeft = availableRight < effectiveSubmenuWidth && availableLeft > availableRight

      setSubmenuIsStacked(false)
      setSubmenuOpensLeft(opensLeft)

      const estimatedSubmenuHeight =
        SUBMENU_PADDING_PX + directionOptions.length * SUBMENU_OPTION_HEIGHT_PX

      const desiredTop = rowRect.top

      const minTop = VIEWPORT_GAP_PX

      const maxTop = Math.max(minTop, viewportHeight - VIEWPORT_GAP_PX - estimatedSubmenuHeight)

      const clampedTop = clamp(desiredTop, minTop, maxTop)

      setSubmenuOffsetTopPx(clampedTop - rowRect.top)
    },
    [clamp, directionOptions.length],
  )

  const updateHoveredSubmenuLayout = useCallback(() => {
    if (hoveredField === null) {
      return
    }

    const rowElement = sortRowRefs.current.get(hoveredField)

    if (!rowElement) {
      return
    }

    updateSubmenuLayoutForRow(rowElement)
  }, [hoveredField, updateSubmenuLayoutForRow])

  const cancelScheduledLayoutUpdate = useCallback(() => {
    if (layoutFrameRef.current === null) {
      return
    }

    window.cancelAnimationFrame(layoutFrameRef.current)

    layoutFrameRef.current = null
  }, [])

  const scheduleLayoutUpdate = useCallback(() => {
    cancelScheduledLayoutUpdate()

    layoutFrameRef.current = window.requestAnimationFrame(() => {
      layoutFrameRef.current = null

      updatePanelLayout()
      updateHoveredSubmenuLayout()
    })
  }, [cancelScheduledLayoutUpdate, updateHoveredSubmenuLayout, updatePanelLayout])

  const setHoveredField = useCallback(
    (field: string, rowElement?: HTMLElement) => {
      setHoveredFieldState(field)

      if (rowElement) {
        updateSubmenuLayoutForRow(rowElement)

        return
      }

      scheduleLayoutUpdate()
    },
    [scheduleLayoutUpdate, updateSubmenuLayoutForRow],
  )

  const closeMenu = useCallback(() => {
    cancelScheduledLayoutUpdate()
    setIsMenuOpen(false)
    setHoveredFieldState(null)
  }, [cancelScheduledLayoutUpdate])

  function handleTriggerClick(event: MouseEvent<HTMLButtonElement>) {
    event.stopPropagation()

    if (isMenuOpen) {
      closeMenu()
      return
    }

    setHoveredFieldState(null)
    preparePanelLayoutForOpen()
    setIsMenuOpen(true)
  }

  function handleMenuClick(event: MouseEvent<HTMLDivElement>) {
    event.stopPropagation()
  }

  function handleClearClick(event: MouseEvent<HTMLButtonElement>) {
    event.stopPropagation()

    onClearClicked?.()

    closeMenu()
  }

  function handleRowTriggerClick(event: MouseEvent<HTMLButtonElement>, field: string) {
    event.stopPropagation()

    const rowElement = sortRowRefs.current.get(field)

    if (!rowElement) {
      return
    }

    /*
     * In stacked mode, clicking the row behaves
     * like an accordion toggle.
     */
    if (submenuIsStacked && hoveredField === field) {
      setHoveredFieldState(null)

      return
    }

    /*
     * On desktop keep/open the side submenu.
     */
    setHoveredField(field, rowElement)
  }

  function handleRowMouseEnter(field: string) {
    if (submenuIsStacked) {
      return
    }

    const rowElement = sortRowRefs.current.get(field)

    if (!rowElement) {
      return
    }

    setHoveredField(field, rowElement)
  }

  function handleRowTriggerFocus(field: string) {
    if (submenuIsStacked) {
      return
    }

    const rowElement = sortRowRefs.current.get(field)

    if (!rowElement) {
      return
    }

    setHoveredField(field, rowElement)
  }

  function handleSortSelected(field: string, direction: TableSortDirection) {
    onSelectionChange?.({
      field,
      direction,
    })

    closeMenu()
  }

  /*
   * Mount/unmount menu while keeping the
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

    if (isMenuOpen) {
      animationFrameRef.current = window.requestAnimationFrame(() => {
        setShouldRenderMenu(true)

        animationFrameRef.current = window.requestAnimationFrame(() => {
          setMenuVisible(true)

          scheduleLayoutUpdate()

          animationFrameRef.current = null
        })
      })

      return
    }

    animationFrameRef.current = window.requestAnimationFrame(() => {
      setMenuVisible(false)

      const leaveDuration = panelOpensUpward
        ? PANEL_UPWARD_LEAVE_DURATION_MS
        : PANEL_DOWNWARD_LEAVE_DURATION_MS

      closeTimerRef.current = window.setTimeout(() => {
        setShouldRenderMenu(false)

        resetPanelLayout()
        resetSubmenuLayout()

        closeTimerRef.current = null
      }, leaveDuration)

      animationFrameRef.current = null
    })
  }, [isMenuOpen, panelOpensUpward, resetPanelLayout, resetSubmenuLayout, scheduleLayoutUpdate])

  /*
   * Outside click.
   */
  useEffect(() => {
    function handleDocumentClick(event: globalThis.MouseEvent) {
      if (!isMenuOpen) {
        return
      }

      const target = event.target

      if (!(target instanceof Node)) {
        return
      }

      if (sortDropdownRootRef.current?.contains(target)) {
        return
      }

      closeMenu()
    }

    document.addEventListener('click', handleDocumentClick)

    return () => {
      document.removeEventListener('click', handleDocumentClick)
    }
  }, [closeMenu, isMenuOpen])

  /*
   * Escape closes the menu.
   */
  useEffect(() => {
    if (!isMenuOpen) {
      return
    }

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key !== 'Escape') {
        return
      }

      event.preventDefault()

      closeMenu()

      sortDropdownRootRef.current
        ?.querySelector<HTMLButtonElement>('.veratag-sort-dropdown__trigger')
        ?.focus()
    }

    document.addEventListener('keydown', handleKeyDown)

    return () => {
      document.removeEventListener('keydown', handleKeyDown)
    }
  }, [closeMenu, isMenuOpen])

  /*
   * Resize / scroll recalculates layout.
   */
  useEffect(() => {
    if (!isMenuOpen) {
      return
    }

    function handleResize() {
      preparePanelLayoutForOpen()
      scheduleLayoutUpdate()
    }

    function handleScroll() {
      scheduleLayoutUpdate()
    }

    window.addEventListener('resize', handleResize)

    /*
     * Capture allows scrolls of nested containers
     * to reposition the dropdown as well.
     */
    document.addEventListener('scroll', handleScroll, true)

    return () => {
      window.removeEventListener('resize', handleResize)

      document.removeEventListener('scroll', handleScroll, true)
    }
  }, [isMenuOpen, preparePanelLayoutForOpen, scheduleLayoutUpdate])

  /*
   * Recalculate if data changes while open.
   */
  useEffect(() => {
    if (!isMenuOpen) {
      return
    }

    scheduleLayoutUpdate()
  }, [criteria, directionOptions, isMenuOpen, scheduleLayoutUpdate, selection])

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

  const hostClassName = [
    'veratag-table-sort-dropdown-button',
    fullWidth ? 'veratag-table-sort-dropdown-button--full-width' : '',
  ]
    .filter(Boolean)
    .join(' ')

  const dropdownClassName = [
    'veratag-sort-dropdown',
    isMenuOpen ? 'veratag-sort-dropdown--open' : '',
    fullWidth ? 'veratag-sort-dropdown--full-width' : '',
  ]
    .filter(Boolean)
    .join(' ')

  const menuStyle = {
    maxWidth: panelMaxWidthPx,

    '--veratag-sort-dropdown-menu-max-height': panelMaxHeight,
  } as CSSProperties

  return (
    <div className={hostClassName}>
      <div className={dropdownClassName} ref={sortDropdownRootRef}>
        <button
          aria-expanded={isMenuOpen}
          aria-haspopup="menu"
          aria-label={openAriaLabel}
          className={[
            'veratag-sort-dropdown__trigger',
            selection !== null ? 'veratag-sort-dropdown__trigger--with-clear' : '',
            fullWidth ? 'veratag-sort-dropdown__trigger--full-width' : '',
            triggerAlignment === 'center' ? 'veratag-sort-dropdown__trigger--centered' : '',
          ]
            .filter(Boolean)
            .join(' ')}
          onClick={handleTriggerClick}
          type="button"
        >
          <img
            alt=""
            aria-hidden="true"
            className="veratag-sort-dropdown__trigger-icon"
            src={triggerIconSrc}
          />

          <span>{sortButtonLabel}</span>

          <img
            alt=""
            aria-hidden="true"
            className={[
              'veratag-sort-dropdown__trigger-chevron',
              isMenuOpen ? 'veratag-sort-dropdown__trigger-chevron--expanded' : '',
            ]
              .filter(Boolean)
              .join(' ')}
            src={triggerChevronIconSrc}
          />
        </button>

        {selection !== null ? (
          <button
            aria-label={clearAriaLabel}
            className="veratag-sort-dropdown__clear"
            onClick={handleClearClick}
            type="button"
          >
            <img
              alt=""
              aria-hidden="true"
              className="veratag-sort-dropdown__clear-icon"
              src={clearIconSrc}
            />
          </button>
        ) : null}

        {shouldRenderMenu ? (
          <div
            aria-label={menuAriaLabel}
            className={[
              'veratag-sort-dropdown__menu',

              panelAnchor === 'right' ? 'veratag-sort-dropdown__menu--anchor-right' : '',

              panelOpensUpward
                ? 'veratag-sort-dropdown__menu--upward'
                : 'veratag-sort-dropdown__menu--downward',

              menuVisible ? 'veratag-sort-dropdown__menu--visible' : '',

              !isMenuOpen ? 'veratag-sort-dropdown__menu--closing' : '',
            ]
              .filter(Boolean)
              .join(' ')}
            onClick={handleMenuClick}
            role="menu"
            style={menuStyle}
          >
            {criteria.map((criterion) => {
              const isHovered = hoveredField === criterion.field

              return (
                <div
                  className="veratag-sort-dropdown__row"
                  key={criterion.field}
                  onMouseEnter={() => handleRowMouseEnter(criterion.field)}
                  ref={(element) => {
                    if (element) {
                      sortRowRefs.current.set(criterion.field, element)
                    } else {
                      sortRowRefs.current.delete(criterion.field)
                    }
                  }}
                >
                  <button
                    aria-expanded={isHovered}
                    aria-haspopup="menu"
                    aria-label={`Sort by ${criterion.label}`}
                    className={[
                      'veratag-sort-dropdown__row-trigger',

                      isFieldSelected(criterion.field)
                        ? 'veratag-sort-dropdown__row-trigger--active'
                        : '',

                      submenuOpensLeft && !submenuIsStacked
                        ? 'veratag-sort-dropdown__row-trigger--icon-leading'
                        : '',
                    ]
                      .filter(Boolean)
                      .join(' ')}
                    onClick={(event) => handleRowTriggerClick(event, criterion.field)}
                    onFocus={() => handleRowTriggerFocus(criterion.field)}
                    type="button"
                  >
                    <span>{criterion.label}</span>

                    <img
                      alt=""
                      aria-hidden="true"
                      className={[
                        'veratag-sort-dropdown__row-chevron',

                        submenuIsStacked ? 'veratag-sort-dropdown__row-chevron--stacked' : '',

                        isHovered ? 'veratag-sort-dropdown__row-chevron--expanded' : '',
                      ]
                        .filter(Boolean)
                        .join(' ')}
                      src={rowChevronIconSrc}
                    />
                  </button>

                  {isHovered ? (
                    <div
                      aria-label={`Sort order for ${criterion.label}`}
                      className={[
                        'veratag-sort-dropdown__submenu',

                        submenuOpensLeft && !submenuIsStacked
                          ? 'veratag-sort-dropdown__submenu--left'
                          : '',

                        submenuIsStacked ? 'veratag-sort-dropdown__submenu--stacked' : '',

                        !submenuIsStacked && submenuOpensLeft
                          ? 'veratag-sort-dropdown__submenu--overlay-left'
                          : '',

                        !submenuIsStacked && !submenuOpensLeft
                          ? 'veratag-sort-dropdown__submenu--overlay-right'
                          : '',
                      ]
                        .filter(Boolean)
                        .join(' ')}
                      onMouseEnter={() => setHoveredFieldState(criterion.field)}
                      role="menu"
                      style={
                        {
                          top: submenuIsStacked ? undefined : submenuOffsetTopPx,

                          '--veratag-sort-dropdown-submenu-max-height': stackedSubmenuMaxHeight,
                        } as CSSProperties
                      }
                    >
                      {directionOptions.map((option) => {
                        const active = isDirectionSelected(criterion.field, option.direction)

                        return (
                          <button
                            className={[
                              'veratag-sort-dropdown__submenu-option',

                              active ? 'veratag-sort-dropdown__submenu-option--active' : '',
                            ]
                              .filter(Boolean)
                              .join(' ')}
                            key={option.direction}
                            onClick={() => handleSortSelected(criterion.field, option.direction)}
                            type="button"
                          >
                            <img
                              alt=""
                              aria-hidden="true"
                              className="veratag-sort-dropdown__submenu-option-icon"
                              src={getDirectionIconSrc(option)}
                            />

                            <span>{option.label}</span>
                          </button>
                        )
                      })}
                    </div>
                  ) : null}
                </div>
              )
            })}
          </div>
        ) : null}
      </div>
    </div>
  )
}
