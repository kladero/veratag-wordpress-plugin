type OverviewVeratagChannelSectionProps = {
  channelDashboardUrl: string
  channelDisplayName: string
}

export function OverviewVeratagChannelSection({
  channelDashboardUrl,
  channelDisplayName,
}: OverviewVeratagChannelSectionProps) {
  const hasChannelDashboardUrl = channelDashboardUrl.trim() !== ''

  return (
    <section className="veratag-panel">
      <div className="veratag-panel__header">
        <h2 className="veratag-panel__title">Channel Details</h2>
      </div>

      <div className="veratag-panel__body">
        <p className="veratag-panel__text">
          Below are the details registered on the vera[tag] platform for this WordPress site running
          the vera[tag] plugin.
        </p>

        <div className="veratag-stat-list">
          <div className="veratag-stat-list__item">
            <span className="veratag-stat-list__label">Channel Name</span>
            <strong className="veratag-stat-list__value">
              {channelDisplayName.trim() !== '' ? channelDisplayName : 'Not configured'}
            </strong>
          </div>

          <div className="veratag-stat-list__item">
            <span className="veratag-stat-list__label">Channel Link</span>
            {hasChannelDashboardUrl ? (
              <a
                className="veratag-stat-list__value"
                href={channelDashboardUrl}
                rel="noreferrer"
                target="_blank"
              >
                View in vera[tag] Dashboard
              </a>
            ) : (
              <strong className="veratag-stat-list__value">Not configured</strong>
            )}
          </div>
        </div>
      </div>
    </section>
  )
}
