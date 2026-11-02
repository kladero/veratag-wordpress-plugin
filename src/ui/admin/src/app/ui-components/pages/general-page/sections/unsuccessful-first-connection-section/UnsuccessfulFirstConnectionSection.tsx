import { Alert } from '../../../../shared/alert/Alert'

type UnsuccessfulFirstConnectionSectionProps = {
  errorMessage: string
}

export function UnsuccessfulFirstConnectionSection({
  errorMessage,
}: UnsuccessfulFirstConnectionSectionProps) {
  return (
    <section className="veratag-panel">
      <div className="veratag-panel__header">
        <h2 className="veratag-panel__title">
          Failed to Establish Initial Connection to vera[tag] Platform
        </h2>
      </div>

      <div className="veratag-panel__body">
        <Alert label="Error" tone="error">
          Could not establish the initial connection to the vera[tag] platform. Please verify that
          your API key is correct and that your server can reach the platform (Error: {errorMessage}
          ).
        </Alert>
      </div>
    </section>
  )
}
