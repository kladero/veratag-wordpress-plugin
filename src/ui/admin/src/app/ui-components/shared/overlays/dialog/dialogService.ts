import type { ReactNode } from 'react'

/** Promise-based confirmation dialogs. Use plain text from .ts or JSX from .tsx.
 * Raw HTML strings are intentionally not interpreted as HTML. */
export type DialogOptions = {
  title: string
  content: ReactNode
  confirmLabel?: string
  cancelLabel?: string
  tone?: 'default' | 'danger'
  dismissOnBackdrop?: boolean
}

export type DialogRequest = {
  id: number
  options: DialogOptions
  resolve: (confirmed: boolean) => void
}

class DialogService {
  private nextId = 0
  private queued: DialogRequest[] = []
  private active: DialogRequest | null = null
  private notify: ((request: DialogRequest | null) => void) | null = null

  /** Resolves true on confirm, false on cancel or dismissal.
   * Concurrent calls are queued, never stacked. */
  openDialog(options: DialogOptions): Promise<boolean> {
    return new Promise<boolean>((resolve) => {
      this.queued.push({ id: ++this.nextId, options, resolve })
      this.showNext()
    })
  }

  confirm(title: string, content: ReactNode): Promise<boolean> {
    return this.openDialog({ title, content })
  }

  /** Internal: mount exactly one provider. */
  subscribe(listener: (request: DialogRequest | null) => void): () => void {
    if (this.notify !== null) {
      throw new Error('Only one VeraTag DialogProvider can be mounted.')
    }
    this.notify = listener
    if (this.active !== null) listener(this.active)
    else this.showNext()
    return () => {
      if (this.notify === listener) this.notify = null
    }
  }

  /** Internal: complete the current dialog. */
  respond(id: number, confirmed: boolean): void {
    if (this.active?.id !== id) return
    const current = this.active
    this.active = null
    this.notify?.(null)
    current.resolve(confirmed)
    this.showNext()
  }

  private showNext(): void {
    if (this.active !== null || this.notify === null) return
    this.active = this.queued.shift() ?? null
    if (this.active !== null) this.notify(this.active)
  }
}

export const dialogService = new DialogService()
