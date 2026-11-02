import {
  useCallback,
  useEffect,
  useId,
  useLayoutEffect,
  useRef,
  useState,
  type CSSProperties,
  type ReactNode,
} from 'react'
import { createPortal } from 'react-dom'

import './popover.css'

type HSide = 'left' | 'right'
type VSide = 'top' | 'bottom'

type PopoverProps = {
  visible: boolean
  anchor?: HTMLElement | null

  width?: string
  height?: string

  minWidth?: string
  minHeight?: string

  maxWidth?: string
  maxHeight?: string

  children: ReactNode

  onVisibleChange?: (visible: boolean) => void
}

type PopoverStyle = CSSProperties & {
  '--veratag-arrow-left'?: string
}

const GAP = 10
const VIEWPORT_PADDING = 8
const ARROW_MARGIN = 20

export function Popover({
  visible,
  anchor,

  width,
  height,

  minWidth,
  minHeight,

  maxWidth,
  maxHeight,

  children,

  onVisibleChange,
}: PopoverProps) {
  const reactId = useId()

  const popoverId = `veratag-popover-${reactId.replaceAll(':', '')}`

  const panelRef = useRef<HTMLDivElement>(null)

  const innerRef = useRef<HTMLDivElement>(null)

  const contentRef = useRef<HTMLDivElement>(null)

  const positionFrameRef = useRef<number | null>(null)

  const focusFrameRef = useRef<number | null>(null)

  const [visibleInternal, setVisibleInternal] = useState(false)

  const [hSide, setHSide] = useState<HSide>('right')

  const [vSide, setVSide] = useState<VSide>('bottom')

  function parsePx(value?: string): number | undefined {
    if (!value) {
      return undefined
    }

    const match = /^-?\d+(?:\.\d+)?px$/.exec(value.trim())

    if (!match) {
      return undefined
    }

    return Number.parseFloat(value)
  }

  const position = useCallback(() => {
    if (!anchor || !panelRef.current || !innerRef.current) {
      return
    }

    const panel = panelRef.current

    const inner = innerRef.current

    const anchorRect = anchor.getBoundingClientRect()

    const viewportWidth = window.innerWidth

    const viewportHeight = window.innerHeight

    const spaceRight = Math.max(0, viewportWidth - anchorRect.right - VIEWPORT_PADDING)

    const spaceLeft = Math.max(0, anchorRect.left - VIEWPORT_PADDING)

    const spaceBottom = Math.max(0, viewportHeight - anchorRect.bottom - GAP - VIEWPORT_PADDING)

    const spaceTop = Math.max(0, anchorRect.top - GAP - VIEWPORT_PADDING)

    const nextHSide: HSide = spaceRight >= spaceLeft ? 'right' : 'left'

    const nextVSide: VSide = spaceBottom >= spaceTop ? 'bottom' : 'top'

    setHSide(nextHSide)
    setVSide(nextVSide)

    const maxViewportWidth = Math.max(0, viewportWidth - VIEWPORT_PADDING * 2)

    /*
     * Because the popover is aligned to one side
     * of the anchor, use the actual available
     * width from that anchor edge.
     */
    const availableWidth =
      nextHSide === 'right'
        ? Math.max(0, viewportWidth - anchorRect.left - VIEWPORT_PADDING)
        : Math.max(0, anchorRect.right - VIEWPORT_PADDING)

    const availableHeight = nextVSide === 'bottom' ? spaceBottom : spaceTop

    const userMaxWidth = parsePx(maxWidth)

    const userMaxHeight = parsePx(maxHeight)

    const userMinWidthCss = minWidth ?? '200px'

    const userMinWidth = parsePx(userMinWidthCss) ?? 200

    const effectiveMaxWidth = Math.max(
      0,
      Math.min(userMaxWidth ?? Number.POSITIVE_INFINITY, maxViewportWidth, availableWidth),
    )

    const effectiveMaxHeight = Math.max(
      0,
      Math.min(userMaxHeight ?? Number.POSITIVE_INFINITY, availableHeight),
    )

    const effectiveMinWidth = Math.max(0, Math.min(userMinWidth, effectiveMaxWidth))

    const effectiveMinWidthCss =
      parsePx(userMinWidthCss) !== undefined
        ? `${effectiveMinWidth}px`
        : `min(${userMinWidthCss}, ${effectiveMaxWidth}px)`

    /*
     * Apply the constraints before measuring.
     * getBoundingClientRect() then forces layout
     * with the final dimensions.
     */
    inner.style.width = width ?? ''

    inner.style.height = height ?? ''

    inner.style.minWidth = effectiveMinWidthCss

    inner.style.minHeight = minHeight ?? ''

    inner.style.maxWidth = `${effectiveMaxWidth}px`

    inner.style.maxHeight = `${effectiveMaxHeight}px`

    /*
     * Temporarily move it into the viewport so
     * the measurement isn't affected by some
     * previous position.
     */
    panel.style.left = '0px'
    panel.style.top = '0px'

    const panelRect = panel.getBoundingClientRect()

    const panelWidth = Math.min(panelRect.width, effectiveMaxWidth)

    const panelHeight = Math.min(panelRect.height, effectiveMaxHeight)

    /*
     * Horizontal placement.
     *
     * right:
     *   left edges align
     *
     * left:
     *   right edges align
     */
    const preferredLeft = nextHSide === 'right' ? anchorRect.left : anchorRect.right - panelWidth

    const minimumLeft = VIEWPORT_PADDING

    const maximumLeft = Math.max(minimumLeft, viewportWidth - panelWidth - VIEWPORT_PADDING)

    const finalLeft = Math.max(minimumLeft, Math.min(preferredLeft, maximumLeft))

    /*
     * Vertical placement.
     */
    const preferredTop =
      nextVSide === 'bottom' ? anchorRect.bottom + GAP : anchorRect.top - GAP - panelHeight

    const minimumTop = VIEWPORT_PADDING

    const maximumTop = Math.max(minimumTop, viewportHeight - panelHeight - VIEWPORT_PADDING)

    const finalTop = Math.max(minimumTop, Math.min(preferredTop, maximumTop))

    panel.style.left = `${Math.round(finalLeft)}px`

    panel.style.top = `${Math.round(finalTop)}px`

    /*
     * Arrow should point toward the centre
     * of the anchor, while staying away from
     * rounded panel corners.
     */
    const anchorCenterX = anchorRect.left + anchorRect.width / 2

    const arrowMinimum = Math.min(ARROW_MARGIN, panelWidth / 2)

    const arrowMaximum = Math.max(arrowMinimum, panelWidth - ARROW_MARGIN)

    const arrowLeft = Math.max(arrowMinimum, Math.min(anchorCenterX - finalLeft, arrowMaximum))

    panel.style.setProperty('--veratag-arrow-left', `${Math.round(arrowLeft)}px`)
  }, [anchor, width, height, minWidth, minHeight, maxWidth, maxHeight])

  /*
   * Keep ARIA attributes synchronized with
   * the trigger element, like the Angular
   * component does.
   */
  useEffect(() => {
    if (!anchor) {
      return
    }

    anchor.setAttribute('aria-haspopup', 'dialog')

    anchor.setAttribute('aria-expanded', String(visible))

    anchor.setAttribute('aria-controls', popoverId)

    return () => {
      /*
       * Only remove aria-controls when it still
       * belongs to this popover.
       */
      if (anchor.getAttribute('aria-controls') === popoverId) {
        anchor.removeAttribute('aria-controls')

        anchor.removeAttribute('aria-haspopup')

        anchor.removeAttribute('aria-expanded')
      }
    }
  }, [anchor, visible, popoverId])

  /*
   * Initial positioning.
   */
  useLayoutEffect(() => {
    if (!visible || !anchor) {
      focusFrameRef.current = window.requestAnimationFrame(() => {
        setVisibleInternal(false)
        focusFrameRef.current = null
      })

      return
    }

    focusFrameRef.current = window.requestAnimationFrame(() => {
      setVisibleInternal(false)

      position()

      focusFrameRef.current = window.requestAnimationFrame(() => {
        /*
         * Reposition one more time after the
         * browser has completed layout.
         */
        position()

        setVisibleInternal(true)

        contentRef.current?.focus({
          preventScroll: true,
        })

        focusFrameRef.current = null
      })
    })

    return () => {
      if (focusFrameRef.current !== null) {
        window.cancelAnimationFrame(focusFrameRef.current)
      }
    }
  }, [visible, anchor, position])

  /*
   * Close when clicking outside.
   */
  useEffect(() => {
    if (!visible) {
      return
    }

    function handleDocumentPointerDown(event: PointerEvent) {
      const target = event.target as Node | null

      if (!target) {
        return
      }

      if (anchor?.contains(target)) {
        return
      }

      if (panelRef.current?.contains(target)) {
        return
      }

      onVisibleChange?.(false)
    }

    document.addEventListener('pointerdown', handleDocumentPointerDown)

    return () => {
      document.removeEventListener('pointerdown', handleDocumentPointerDown)
    }
  }, [visible, anchor, onVisibleChange])

  /*
   * Escape closes and returns focus to
   * the trigger.
   */
  useEffect(() => {
    if (!visible) {
      return
    }

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key !== 'Escape') {
        return
      }

      event.preventDefault()

      onVisibleChange?.(false)

      anchor?.focus()
    }

    document.addEventListener('keydown', handleKeyDown)

    return () => {
      document.removeEventListener('keydown', handleKeyDown)
    }
  }, [visible, anchor, onVisibleChange])

  /*
   * Reposition on viewport changes and
   * scrolling of any ancestor.
   */
  useEffect(() => {
    if (!visible) {
      return
    }

    function schedulePosition() {
      if (positionFrameRef.current !== null) {
        window.cancelAnimationFrame(positionFrameRef.current)
      }

      positionFrameRef.current = window.requestAnimationFrame(() => {
        position()
      })
    }

    window.addEventListener('resize', schedulePosition)

    document.addEventListener('scroll', schedulePosition, true)

    return () => {
      window.removeEventListener('resize', schedulePosition)

      document.removeEventListener('scroll', schedulePosition, true)

      if (positionFrameRef.current !== null) {
        window.cancelAnimationFrame(positionFrameRef.current)
      }
    }
  }, [visible, position])

  /*
   * Recalculate when either the trigger or
   * popover content changes dimensions.
   */
  useEffect(() => {
    if (!visible || !anchor || typeof ResizeObserver === 'undefined') {
      return
    }

    const observer = new ResizeObserver(() => {
      position()
    })

    observer.observe(anchor)

    if (innerRef.current) {
      observer.observe(innerRef.current)
    }

    return () => {
      observer.disconnect()
    }
  }, [visible, anchor, position])

  if (!visible || !anchor || typeof document === 'undefined') {
    return null
  }

  const panelStyle: PopoverStyle = {
    '--veratag-arrow-left': '24px',
  }

  return createPortal(
    <div
      className={['veratag-popover__anim', visibleInternal ? 'veratag-popover__anim--visible' : '']
        .filter(Boolean)
        .join(' ')}
    >
      <div
        ref={panelRef}
        aria-hidden={!visibleInternal}
        aria-modal="false"
        className={[
          'veratag-popover',
          `veratag-popover--${hSide}`,
          `veratag-popover--${vSide}`,
          visibleInternal ? 'veratag-popover--ready' : '',
        ]
          .filter(Boolean)
          .join(' ')}
        id={popoverId}
        role="dialog"
        style={panelStyle}
      >
        <div className="veratag-popover__arrow" />

        <div ref={innerRef} className="veratag-popover__inner">
          <div ref={contentRef} className="veratag-popover__scroll" tabIndex={-1}>
            {children}
          </div>
        </div>
      </div>
    </div>,
    document.body,
  )
}
