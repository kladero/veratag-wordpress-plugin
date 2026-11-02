import type { AnchorHTMLAttributes, ButtonHTMLAttributes, MouseEvent } from 'react'
import './button.css'

type ButtonVariant = 'solid' | 'outlined' | 'text'
type ButtonColor = 'brand' | 'gray' | 'green' | 'red'

type ButtonBaseProps = {
  buttonText: string
  color?: ButtonColor
  disabled?: boolean
  variant?: ButtonVariant
}

type ButtonElementProps = ButtonBaseProps & {
  href?: undefined
  onClick?: (event: MouseEvent<HTMLButtonElement>) => void
  type?: 'button' | 'submit' | 'reset'
} & Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'children' | 'type' | 'onClick' | 'disabled'>

type LinkElementProps = ButtonBaseProps & {
  href: string
  onClick?: (event: MouseEvent<HTMLAnchorElement>) => void
  rel?: string
  target?: AnchorHTMLAttributes<HTMLAnchorElement>['target']
} & Omit<AnchorHTMLAttributes<HTMLAnchorElement>, 'children' | 'href' | 'onClick'>

type ButtonProps = ButtonElementProps | LinkElementProps

export function Button({
  buttonText,
  color = 'brand',
  disabled = false,
  variant = 'solid',
  ...buttonProps
}: ButtonProps) {
  const className = `veratag-button veratag-button--${variant} veratag-button--${color}${
    disabled ? ' veratag-button--disabled' : ''
  }`

  if ('href' in buttonProps && typeof buttonProps.href === 'string') {
    const { href, onClick, rel, target, ...anchorProps } = buttonProps

    return (
      <a
        {...anchorProps}
        aria-disabled={disabled}
        className={className}
        href={disabled ? undefined : href}
        onClick={onClick}
        rel={rel}
        target={target}
      >
        <span className="veratag-button__text">{buttonText}</span>
      </a>
    )
  }

  const { onClick, type = 'button', ...nativeButtonProps } = buttonProps

  return (
    <button
      {...nativeButtonProps}
      className={className}
      disabled={disabled}
      onClick={onClick}
      type={type}
    >
      <span className="veratag-button__text">{buttonText}</span>
    </button>
  )
}
