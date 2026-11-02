import type { ChangeEvent, InputHTMLAttributes } from 'react'
import './input-text.css'

type InputTextProps = {
  errorText?: string
  hintText?: string
  id: string
  label?: string
} & Omit<InputHTMLAttributes<HTMLInputElement>, 'id'>

export function InputText({
  errorText,
  hintText,
  id,
  label,
  disabled = false,
  readOnly = false,
  ...inputProps
}: InputTextProps) {
  const hasError = typeof errorText === 'string' && errorText.trim() !== ''

  const hintId = hintText ? `${id}-hint` : undefined
  const errorId = hasError ? `${id}-error` : undefined

  const describedBy = [hintId, errorId].filter(Boolean).join(' ') || undefined

  const className = [
    'veratag-input-text',
    hasError ? 'veratag-input-text--error' : null,
    disabled ? 'veratag-input-text--disabled' : null,
    readOnly ? 'veratag-input-text--readonly' : null,
  ]
    .filter(Boolean)
    .join(' ')

  const handleChange = (event: ChangeEvent<HTMLInputElement>) => {
    inputProps.onChange?.(event)
  }

  return (
    <div className={className}>
      {label ? (
        <label className="veratag-input-text__label" htmlFor={id}>
          {label}
        </label>
      ) : null}

      <div className="veratag-input-text__wrapper">
        <input
          {...inputProps}
          aria-describedby={describedBy}
          aria-invalid={hasError}
          className="veratag-input-text__field"
          disabled={disabled}
          id={id}
          onChange={handleChange}
          readOnly={readOnly}
          tabIndex={readOnly ? -1 : inputProps.tabIndex}
        />
      </div>

      {hasError ? (
        <p className="veratag-input-text__error" id={errorId} role="alert">
          {errorText}
        </p>
      ) : null}

      {!hasError && hintText ? (
        <p className="veratag-input-text__hint" id={hintId}>
          {hintText}
        </p>
      ) : null}
    </div>
  )
}
