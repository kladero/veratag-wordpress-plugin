import './error-page.css'
import { Button } from '../form/button/Button'

type ErrorPageProps = {
  code?: string
  title: string
  message: string
  onRetry?: () => void
  retryLabel?: string
}

/**
 * Full-page, vertically centered error state — replaces the entire admin
 * shell (not a banner/toast) when the backend reports this plugin's own
 * release is no longer supported. Visually mirrors kalgris's shared
 * `kalgris-error-page` component (status code, heading, muted message),
 * using this app's own design tokens (see assets/styles/) rather than
 * depending on that Angular library.
 */
export function ErrorPage({ code, title, message, onRetry, retryLabel = 'Retry' }: ErrorPageProps) {
  return (
    <div className="veratag-error-page">
      <h1>
        {code ? <div className="veratag-error-page__code">{code}</div> : null}
        <div className="veratag-error-page__heading">{title}</div>
      </h1>
      <div className="veratag-error-page__message">{message}</div>
      {onRetry ? (
        <div className="veratag-error-page__actions">
          <Button buttonText={retryLabel} onClick={() => onRetry()} />
        </div>
      ) : null}
    </div>
  )
}
