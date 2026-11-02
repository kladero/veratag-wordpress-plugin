import {
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
  type KeyboardEvent,
  type MouseEvent,
  type ReactNode,
} from 'react'

import { loadVeratagAdminConfig } from '../../../../config/loadVeratagAdminConfig'
import { Checkbox } from '../../controls/checkbox-base/Checkbox'
import { Badge, type BadgeColor } from '../../misc/badge/Badge'

import './table-filter-button.css'

export type TableFilterOptionRenderContext = {
  option: string
  selected: boolean
}

export type TableFilterSelectionMode = 'multiple' | 'single'

type TableFilterButtonProps = {
  filterTitle: string
  openAriaLabel: string
  clearAriaLabel: string

  selectedCount: number
  isOpen: boolean

  options: readonly string[]
  selectedOptions: readonly string[]

  fullWidth?: boolean

  /*
   * multiple -> checkbox
   * single   -> radio
   */
  selectionMode?: TableFilterSelectionMode

  optionBadgeColor?: BadgeColor

  renderOption?: (context: TableFilterOptionRenderContext) => ReactNode

  onPanelToggled?: (isOpen: boolean) => void

  onClearClicked?: () => void

  onOptionToggled?: (option: string) => void
}

const VIEWPORT_GAP_PX = 12
const PANEL_OFFSET_FROM_TRIGGER_PX = 8
const DEFAULT_PANEL_MAX_HEIGHT_PX = 352

const PANEL_LEAVE_DURATION_MS = 170

export function TableFilterButton({
  filterTitle,
  openAriaLabel,
  clearAriaLabel,

  selectedCount,
  isOpen,

  options,
  selectedOptions,

  fullWidth = false,
  selectionMode = 'multiple',

  optionBadgeColor = 'green',

  renderOption,

  onPanelToggled,
  onClearClicked,
  onOptionToggled,
}: TableFilterButtonProps) {
  const dropdownRootRef = useRef<HTMLDivElement | null>(null)

  const dropdownPanelRef = useRef<HTMLDivElement | null>(null)

  const dropdownOptionsRef = useRef<HTMLDivElement | null>(null)

  const layoutFrameRef = useRef<number | null>(null)

  const animationFrameRef = useRef<number | null>(null)

  const closeTimerRef = useRef<number | null>(null)

  const [searchTerm, setSearchTerm] = useState('')

  const [panelOffsetLeftPx, setPanelOffsetLeftPx] = useState(0)

  const [panelOpensUpward, setPanelOpensUpward] = useState(false)

  const [panelMaxHeight, setPanelMaxHeight] = useState(`${DEFAULT_PANEL_MAX_HEIGHT_PX}px`)

  const [optionsRequireScroll, setOptionsRequireScroll] = useState(false)

  const [shouldRenderPanel, setShouldRenderPanel] = useState(isOpen)

  const [panelVisible, setPanelVisible] = useState(false)

  const { iconsBaseUrl } = loadVeratagAdminConfig()

  /*
   * Replace these filenames with your
   * final SVG assets.
   */
  const chevronIconSrc = `${iconsBaseUrl}l/chevron-down.svg`

  const clearIconSrc = `${iconsBaseUrl}l/x.svg`

  const searchIconSrc = `${iconsBaseUrl}l/search.svg`

  const filteredOptions = useMemo(() => {
    const normalizedSearchTerm = searchTerm.trim().toLowerCase()

    if (normalizedSearchTerm.length === 0) {
      return options
    }

    return options.filter((option) => option.toLowerCase().includes(normalizedSearchTerm))
  }, [options, searchTerm])

  const hasNoMatchingOptions = filteredOptions.length === 0

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
    setPanelOffsetLeftPx(0)

    setPanelOpensUpward(false)

    setPanelMaxHeight(`${DEFAULT_PANEL_MAX_HEIGHT_PX}px`)
  }, [])

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
    const rootElement = dropdownRootRef.current

    if (!rootElement) {
      return
    }

    applyVerticalLayoutForRootRect(rootElement.getBoundingClientRect())
  }, [applyVerticalLayoutForRootRect])

  const updateOptionsOverflowState = useCallback(() => {
    if (!isOpen) {
      setOptionsRequireScroll(false)

      return
    }

    const optionsElement = dropdownOptionsRef.current

    if (!optionsElement) {
      setOptionsRequireScroll(false)

      return
    }

    setOptionsRequireScroll(optionsElement.scrollHeight - optionsElement.clientHeight > 1)
  }, [isOpen])

  const updatePanelLayout = useCallback(() => {
    if (!isOpen) {
      return
    }

    const rootElement = dropdownRootRef.current

    const panelElement = dropdownPanelRef.current

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

    setPanelOffsetLeftPx(desiredLeft - rootRect.left)

    applyVerticalLayoutForRootRect(rootRect)
  }, [applyVerticalLayoutForRootRect, clamp, isOpen])

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

      updateOptionsOverflowState()
    })
  }, [cancelScheduledLayoutUpdate, updateOptionsOverflowState, updatePanelLayout])

  function isOptionSelected(option: string) {
    return selectedOptions.includes(option)
  }

  function normalizeIdPart(value: string) {
    const normalized = value
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')

    return normalized.length > 0 ? normalized : 'option'
  }

  function getOptionInputId(option: string, index: number) {
    const normalizedTitle = normalizeIdPart(filterTitle)

    const normalizedOption = normalizeIdPart(option)

    return `table-filter-${normalizedTitle}-${normalizedOption}-${index}`
  }

  function getRadioGroupName() {
    return `table-filter-${normalizeIdPart(filterTitle)}-group`
  }

  function getOptionAriaLabel(option: string) {
    if (selectionMode === 'single') {
      return `Select ${filterTitle} filter option ${option}`
    }

    return `Toggle ${filterTitle} filter option ${option}`
  }

  function selectOption(option: string) {
    /*
     * A checked radio should not behave like
     * a checkbox and unselect itself.
     *
     * The clear button is used to clear a
     * single-selection filter.
     */
    if (selectionMode === 'single' && isOptionSelected(option)) {
      return
    }

    onOptionToggled?.(option)
  }

  function handlePanelToggle() {
    const nextIsOpen = !isOpen

    if (nextIsOpen) {
      preparePanelLayoutForOpen()
    }

    onPanelToggled?.(nextIsOpen)
  }

  function handleClearClick(event: MouseEvent<HTMLButtonElement>) {
    event.stopPropagation()

    onClearClicked?.()
  }

  function handleOptionKeyDown(event: KeyboardEvent<HTMLDivElement>, option: string) {
    if (event.key !== 'Enter' && event.key !== ' ') {
      return
    }

    event.preventDefault()

    selectOption(option)
  }

  /*
   * Enter / leave animation.
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

    if (isOpen) {
      preparePanelLayoutForOpen()

      animationFrameRef.current = window.requestAnimationFrame(() => {
        setShouldRenderPanel(true)

        animationFrameRef.current = window.requestAnimationFrame(() => {
          setPanelVisible(true)

          schedulePanelLayoutUpdate()

          animationFrameRef.current = null
        })
      })

      return
    }

    animationFrameRef.current = window.requestAnimationFrame(() => {
      setPanelVisible(false)

      setSearchTerm('')

      setOptionsRequireScroll(false)

      resetPanelLayout()

      closeTimerRef.current = window.setTimeout(() => {
        setShouldRenderPanel(false)

        closeTimerRef.current = null
      }, PANEL_LEAVE_DURATION_MS)

      animationFrameRef.current = null
    })
  }, [isOpen, preparePanelLayoutForOpen, resetPanelLayout, schedulePanelLayoutUpdate])

  /*
   * Content changes can affect height.
   */
  useEffect(() => {
    if (!isOpen) {
      return
    }

    schedulePanelLayoutUpdate()
  }, [isOpen, options, selectedOptions, searchTerm, schedulePanelLayoutUpdate])

  /*
   * Outside click.
   */
  useEffect(() => {
    function handleDocumentClick(event: globalThis.MouseEvent) {
      if (!isOpen) {
        return
      }

      const rootElement = dropdownRootRef.current

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

      onPanelToggled?.(false)
    }

    document.addEventListener('click', handleDocumentClick)

    return () => {
      document.removeEventListener('click', handleDocumentClick)
    }
  }, [isOpen, onPanelToggled])

  /*
   * Escape closes.
   */
  useEffect(() => {
    if (!isOpen) {
      return
    }

    function handleKeyDown(event: globalThis.KeyboardEvent) {
      if (event.key !== 'Escape') {
        return
      }

      event.preventDefault()

      onPanelToggled?.(false)

      dropdownRootRef.current
        ?.querySelector<HTMLButtonElement>('.veratag-filter-dropdown__trigger')
        ?.focus()
    }

    document.addEventListener('keydown', handleKeyDown)

    return () => {
      document.removeEventListener('keydown', handleKeyDown)
    }
  }, [isOpen, onPanelToggled])

  /*
   * Reposition on window or nested-container scroll.
   */
  useEffect(() => {
    if (!isOpen) {
      return
    }

    function handleViewportChange() {
      schedulePanelLayoutUpdate()
    }

    window.addEventListener('resize', handleViewportChange)

    document.addEventListener('scroll', handleViewportChange, true)

    return () => {
      window.removeEventListener('resize', handleViewportChange)

      document.removeEventListener('scroll', handleViewportChange, true)
    }
  }, [isOpen, schedulePanelLayoutUpdate])

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
    'veratag-table-filter-button',

    fullWidth ? 'veratag-table-filter-button--full-width' : '',
  ]
    .filter(Boolean)
    .join(' ')

  const dropdownClassName = [
    'veratag-filter-dropdown',

    isOpen ? 'veratag-filter-dropdown--open' : '',

    fullWidth ? 'veratag-filter-dropdown--full-width' : '',
  ]
    .filter(Boolean)
    .join(' ')

  return (
    <div className={hostClassName}>
      <div className={dropdownClassName} ref={dropdownRootRef}>
        <button
          aria-expanded={isOpen}
          aria-haspopup="listbox"
          aria-label={openAriaLabel}
          className={[
            'veratag-filter-dropdown__trigger',

            fullWidth ? 'veratag-filter-dropdown__trigger--full-width' : '',
          ]
            .filter(Boolean)
            .join(' ')}
          onClick={handlePanelToggle}
          type="button"
        >
          <span className="veratag-filter-dropdown__title" title={filterTitle}>
            {filterTitle}
          </span>

          <span className="veratag-filter-dropdown__trailing">
            <span className="veratag-filter-dropdown__count">{selectedCount}</span>

            <span className="veratag-filter-dropdown__meta">selected</span>

            <img
              alt=""
              aria-hidden="true"
              className={[
                'veratag-filter-dropdown__chevron',

                isOpen ? 'veratag-filter-dropdown__chevron--open' : '',
              ]
                .filter(Boolean)
                .join(' ')}
              src={chevronIconSrc}
            />
          </span>
        </button>

        {selectedCount > 0 ? (
          <button
            aria-label={clearAriaLabel}
            className="veratag-filter-dropdown__clear"
            onClick={handleClearClick}
            type="button"
          >
            <img
              alt=""
              aria-hidden="true"
              className="veratag-filter-dropdown__clear-icon"
              src={clearIconSrc}
            />
          </button>
        ) : null}

        {shouldRenderPanel ? (
          <div
            aria-label={`${filterTitle} filter options`}
            aria-multiselectable={selectionMode === 'multiple' ? true : undefined}
            className={[
              'veratag-filter-dropdown__panel',

              panelVisible ? 'veratag-filter-dropdown__panel--visible' : '',

              !isOpen ? 'veratag-filter-dropdown__panel--closing' : '',

              panelOpensUpward ? 'veratag-filter-dropdown__panel--upward' : '',
            ]
              .filter(Boolean)
              .join(' ')}
            ref={dropdownPanelRef}
            role="listbox"
            style={{
              left: panelOffsetLeftPx,

              maxHeight: panelMaxHeight,
            }}
          >
            <div className="veratag-filter-dropdown__search">
              <div className="veratag-filter-dropdown__search-wrapper" role="presentation">
                <input
                  aria-label={`Search ${filterTitle} options`}
                  autoComplete="off"
                  className="veratag-filter-dropdown__search-input"
                  onChange={(event) => {
                    setSearchTerm(event.target.value)
                  }}
                  placeholder="Search..."
                  role="searchbox"
                  spellCheck={false}
                  type="text"
                  value={searchTerm}
                />

                <img
                  alt=""
                  aria-hidden="true"
                  className="veratag-filter-dropdown__search-icon"
                  src={searchIconSrc}
                />
              </div>
            </div>

            <div
              className={[
                'veratag-filter-dropdown__options',

                optionsRequireScroll ? 'veratag-filter-dropdown__options--scrollable' : '',
              ]
                .filter(Boolean)
                .join(' ')}
              ref={dropdownOptionsRef}
            >
              {hasNoMatchingOptions ? (
                <div className="veratag-filter-dropdown__no-results">No results found</div>
              ) : null}

              {filteredOptions.map((option, index) => {
                const selected = isOptionSelected(option)

                const inputId = getOptionInputId(option, index)

                return (
                  <div
                    aria-selected={selected}
                    className="veratag-filter-option"
                    key={option}
                    onClick={() => selectOption(option)}
                    onKeyDown={(event) => handleOptionKeyDown(event, option)}
                    role="option"
                    tabIndex={0}
                    title={option}
                  >
                    <div
                      className="veratag-filter-option__control"
                      onClick={(event) => event.stopPropagation()}
                      onKeyDown={(event) => event.stopPropagation()}
                    >
                      {selectionMode === 'single' ? (
                        <label
                          className={[
                            'veratag-filter-option__radio',

                            selected ? 'veratag-filter-option__radio--checked' : '',
                          ]
                            .filter(Boolean)
                            .join(' ')}
                        >
                          <input
                            aria-label={getOptionAriaLabel(option)}
                            checked={selected}
                            className="veratag-filter-option__radio-input"
                            id={inputId}
                            name={getRadioGroupName()}
                            onChange={() => selectOption(option)}
                            type="radio"
                            value={option}
                          />

                          <span
                            aria-hidden="true"
                            className="veratag-filter-option__radio-circle"
                          />
                        </label>
                      ) : (
                        <Checkbox
                          ariaLabel={getOptionAriaLabel(option)}
                          checked={selected}
                          inputId={inputId}
                          onCheckedChange={() => selectOption(option)}
                        />
                      )}
                    </div>

                    <div className="veratag-filter-option__content" title={option}>
                      {renderOption ? (
                        renderOption({
                          option,
                          selected,
                        })
                      ) : (
                        <Badge color={optionBadgeColor} rounded size="sm" text={option} />
                      )}
                    </div>
                  </div>
                )
              })}
            </div>
          </div>
        ) : null}
      </div>
    </div>
  )
}
