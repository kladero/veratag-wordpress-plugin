import { Button } from '../../shared/form/button/Button'
import './top-bar.css'

type TopBarProps = {
  channelDashboardUrl: string
  wordmarkUrl: string
}

export function TopBar({ channelDashboardUrl, wordmarkUrl }: TopBarProps) {
  const hasChannelDashboardUrl = channelDashboardUrl.trim() !== ''

  return (
    <header className="veratag-top-bar">
      <div className="veratag-top-bar__inner">
        <div className="veratag-top-bar__brand-area">
          {wordmarkUrl === '' ? (
            <span className="veratag-top-bar__brand">vera[tag]</span>
          ) : (
            <img className="veratag-top-bar__wordmark" src={wordmarkUrl} alt="vera[tag]" />
          )}
        </div>

        {hasChannelDashboardUrl ? (
          <div className="veratag-top-bar__actions">
            <Button
              buttonText="View in vera[tag] Dashboard"
              href={channelDashboardUrl}
              rel="noreferrer"
              target="_blank"
              variant="solid"
            />
          </div>
        ) : null}
      </div>
    </header>
  )
}
