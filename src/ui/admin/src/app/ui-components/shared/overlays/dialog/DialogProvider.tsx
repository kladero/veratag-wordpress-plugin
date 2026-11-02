import {
  useEffect,
  useRef,
  useState,
  type KeyboardEvent,
  type PropsWithChildren,
  type MouseEvent,
} from 'react'
import { createPortal } from 'react-dom'
import { Button } from '../../form/button/Button'
import { dialogService, type DialogRequest } from './dialogService'
import './dialog.css'

const FOCUSABLE =
  'a[href], button:not(:disabled), input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])'

/** Mount once below App. A portal prevents WordPress containers from clipping
 * the modal; scoped VeraTag design tokens are also attached to the portal. */
export function DialogProvider({ children }: PropsWithChildren) {
  const [request, setRequest] = useState<DialogRequest | null>(null)
  const [portal, setPortal] = useState<HTMLDivElement | null>(null)
  const panelRef = useRef<HTMLDivElement | null>(null)

  useEffect(() => {
    const element = document.createElement('div')
    element.className = 'veratag-dialog-portal'
    document.body.appendChild(element)
    const portalFrame = window.requestAnimationFrame(() => {
      setPortal(element)
    })

    return () => {
      window.cancelAnimationFrame(portalFrame)
      element.remove()
    }
  }, [])

  useEffect(() => dialogService.subscribe(setRequest), [])

  useEffect(() => {
    if (request === null || portal === null) return

    const previouslyFocused = document.activeElement as HTMLElement | null
    const oldOverflow = document.body.style.overflow
    const backgrounds = Array.from(document.body.children).filter(
      (child): child is HTMLElement => child instanceof HTMLElement && child !== portal,
    )
    const oldInert = backgrounds.map((element) => element.inert)

    // Prevent pointer and assistive-technology interaction with the WordPress
    // admin behind a modal. Previous inert values are restored on close.
    backgrounds.forEach((element) => {
      element.inert = true
    })
    document.body.style.overflow = 'hidden'

    const panel = panelRef.current
    const initialFocus = panel?.querySelector<HTMLElement>('[data-veratag-dialog-initial-focus]')
    ;(initialFocus ?? panel)?.focus()

    function keepFocusInside(event: FocusEvent) {
      if (panel && event.target instanceof Node && !panel.contains(event.target)) {
        ;(panel.querySelector<HTMLElement>('[data-veratag-dialog-initial-focus]') ?? panel).focus()
      }
    }
    document.addEventListener('focusin', keepFocusInside)

    return () => {
      document.removeEventListener('focusin', keepFocusInside)
      backgrounds.forEach((element, index) => {
        element.inert = oldInert[index]
      })
      document.body.style.overflow = oldOverflow
      if (previouslyFocused?.isConnected) previouslyFocused.focus()
    }
  }, [request, portal])

  function handleKeyDown(event: KeyboardEvent<HTMLDivElement>) {
    if (request === null) return
    if (event.key === 'Escape') {
      event.preventDefault()
      event.stopPropagation()
      dialogService.respond(request.id, false)
      return
    }
    if (event.key !== 'Tab' || panelRef.current === null) return

    const focusable = Array.from(panelRef.current.querySelectorAll<HTMLElement>(FOCUSABLE)).filter(
      (element) => element.getClientRects().length > 0,
    )
    if (focusable.length === 0) {
      event.preventDefault()
      panelRef.current.focus()
      return
    }
    const first = focusable[0]
    const last = focusable[focusable.length - 1]
    if (
      event.shiftKey &&
      (document.activeElement === first || !panelRef.current.contains(document.activeElement))
    ) {
      event.preventDefault()
      last.focus()
    } else if (
      !event.shiftKey &&
      (document.activeElement === last || !panelRef.current.contains(document.activeElement))
    ) {
      event.preventDefault()
      first.focus()
    }
  }

  function handleBackdropClick(event: MouseEvent<HTMLDivElement>) {
    if (request?.options.dismissOnBackdrop && event.target === event.currentTarget) {
      dialogService.respond(request.id, false)
    }
  }

  const options = request?.options
  return (
    <>
      {children}
      {portal && request && options
        ? createPortal(
            <div
              className="veratag-dialog-backdrop"
              onClick={handleBackdropClick}
              onKeyDown={handleKeyDown}
            >
              <div
                aria-describedby={`veratag-dialog-description-${request.id}`}
                aria-labelledby={`veratag-dialog-title-${request.id}`}
                aria-modal="true"
                className="veratag-dialog"
                ref={panelRef}
                role={options.tone === 'danger' ? 'alertdialog' : 'dialog'}
                tabIndex={-1}
              >
                <h2 className="veratag-dialog__title" id={`veratag-dialog-title-${request.id}`}>
                  {options.title}
                </h2>
                <div
                  className="veratag-dialog__content"
                  id={`veratag-dialog-description-${request.id}`}
                >
                  {options.content}
                </div>
                <div className="veratag-dialog__actions">
                  <Button
                    buttonText={options.cancelLabel ?? 'Cancel'}
                    color="gray"
                    data-veratag-dialog-initial-focus="true"
                    onClick={() => dialogService.respond(request.id, false)}
                    variant="outlined"
                  />
                  <Button
                    buttonText={options.confirmLabel ?? 'OK'}
                    color={options.tone === 'danger' ? 'red' : 'brand'}
                    onClick={() => dialogService.respond(request.id, true)}
                    variant="solid"
                  />
                </div>
              </div>
            </div>,
            portal,
          )
        : null}
    </>
  )
}
