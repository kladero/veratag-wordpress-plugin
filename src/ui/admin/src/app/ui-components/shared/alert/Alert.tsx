import './alert.css'

type AlertTone = 'info' | 'warning' | 'error' | 'success'

type AlertProps = {
  children: React.ReactNode
  label?: string
  tone: AlertTone
}

export function Alert({ children, label, tone }: AlertProps) {
  return (
    <div className={`veratag-alert veratag-alert--${tone}`} role="alert">
      {label ? <span className="veratag-alert__label">{label}</span> : null}
      <span className="veratag-alert__content">{children}</span>
    </div>
  )
}
