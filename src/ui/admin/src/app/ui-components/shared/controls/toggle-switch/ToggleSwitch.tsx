import { useState, type ChangeEvent, type InputHTMLAttributes } from 'react'

import './toggle-switch.css'

type ToggleSwitchSize = 'sm' | 'md' | 'lg'

type ToggleSwitchProps = {
  inputId?: string
  ariaLabel?: string
  labelledBy?: string
  checked: boolean
  disabled?: boolean
  size?: ToggleSwitchSize
  onCheckedChange?: (checked: boolean) => void
  onFocusChange?: (focused: boolean) => void
} & Omit<
  InputHTMLAttributes<HTMLInputElement>,
  | 'type'
  | 'id'
  | 'checked'
  | 'disabled'
  | 'size'
  | 'onChange'
  | 'onFocus'
  | 'onBlur'
  | 'aria-label'
  | 'aria-labelledby'
>

export function ToggleSwitch({
  inputId,
  ariaLabel = 'Toggle switch',
  labelledBy,
  checked,
  disabled = false,
  size = 'md',
  onCheckedChange,
  onFocusChange,
  ...inputProps
}: ToggleSwitchProps) {
  const [isFocused, setIsFocused] = useState(false)

  function handleChange(event: ChangeEvent<HTMLInputElement>) {
    if (disabled) {
      return
    }

    onCheckedChange?.(event.target.checked)
  }

  function handleFocus() {
    setIsFocused(true)
    onFocusChange?.(true)
  }

  function handleBlur() {
    setIsFocused(false)
    onFocusChange?.(false)
  }

  const className = [
    'veratag-toggle-switch',
    `veratag-toggle-switch--${size}`,
    checked ? 'veratag-toggle-switch--checked' : null,
    disabled ? 'veratag-toggle-switch--disabled' : null,
    isFocused ? 'veratag-toggle-switch--focus' : null,
  ]
    .filter(Boolean)
    .join(' ')

  return (
    <span className={className}>
      <input
        {...inputProps}
        aria-checked={checked}
        aria-label={!labelledBy ? ariaLabel : undefined}
        aria-labelledby={labelledBy}
        checked={checked}
        className="veratag-toggle-switch__input"
        disabled={disabled}
        id={inputId}
        onBlur={handleBlur}
        onChange={handleChange}
        onFocus={handleFocus}
        role="switch"
        type="checkbox"
      />

      <span aria-hidden="true" className="veratag-toggle-switch__slider" />
    </span>
  )
}
