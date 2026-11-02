import type {
  AnchorHTMLAttributes,
  ButtonHTMLAttributes,
  CSSProperties,
  MouseEvent,
  HTMLAttributes,
} from 'react'
import './badge.css'

export type BadgeColor =
  | 'brand'
  | 'red'
  | 'green'
  | 'blue'
  | 'yellow'
  | 'orange'
  | 'violet'
  | 'gray'

export type BadgeSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl'

type BadgeBaseProps = {
  text?: string
  color?: BadgeColor
  size?: BadgeSize
  clickable?: boolean
  ariaLabel?: string
  labelledBy?: string
  leftIcon?: string
  rightIcon?: string
  leftSvgUrl?: string
  rightSvgUrl?: string
  rounded?: boolean
  onBadgeClick?: (event: MouseEvent<HTMLAnchorElement> | MouseEvent<HTMLButtonElement>) => void
}

type BadgeLinkProps = BadgeBaseProps & {
  href: string
  target?: AnchorHTMLAttributes<HTMLAnchorElement>['target']
  rel?: string
} & Omit<
    AnchorHTMLAttributes<HTMLAnchorElement>,
    | 'children'
    | 'href'
    | 'target'
    | 'rel'
    | 'className'
    | 'aria-label'
    | 'aria-labelledby'
    | 'onClick'
  >

type BadgeNonLinkProps = BadgeBaseProps & {
  href?: undefined
} & Omit<
    HTMLAttributes<HTMLSpanElement>,
    'children' | 'className' | 'aria-label' | 'aria-labelledby' | 'onClick'
  >

export type BadgeProps = BadgeLinkProps | BadgeNonLinkProps

function getSvgMaskStyle(svgUrl?: string): CSSProperties | undefined {
  if (!svgUrl) {
    return undefined
  }

  return {
    WebkitMaskImage: `url("${svgUrl}")`,
    maskImage: `url("${svgUrl}")`,
  }
}

type BadgeContentProps = Pick<
  BadgeBaseProps,
  'text' | 'leftIcon' | 'rightIcon' | 'leftSvgUrl' | 'rightSvgUrl'
>

function BadgeContent({ text, leftIcon, rightIcon, leftSvgUrl, rightSvgUrl }: BadgeContentProps) {
  return (
    <>
      {leftSvgUrl ? (
        <span
          aria-hidden="true"
          className="veratag-badge__icon veratag-badge__icon--svg veratag-badge__icon--left"
          style={getSvgMaskStyle(leftSvgUrl)}
        />
      ) : leftIcon ? (
        <i
          aria-hidden="true"
          className={`${leftIcon} veratag-badge__icon veratag-badge__icon--left`}
        />
      ) : null}

      {text ? <span className="veratag-badge__text">{text}</span> : null}

      {rightSvgUrl ? (
        <span
          aria-hidden="true"
          className="veratag-badge__icon veratag-badge__icon--svg veratag-badge__icon--right"
          style={getSvgMaskStyle(rightSvgUrl)}
        />
      ) : rightIcon ? (
        <i
          aria-hidden="true"
          className={`${rightIcon} veratag-badge__icon veratag-badge__icon--right`}
        />
      ) : null}
    </>
  )
}

export function Badge({
  text = '',
  color = 'gray',
  size = 'md',
  clickable = false,
  ariaLabel,
  labelledBy,
  leftIcon,
  rightIcon,
  leftSvgUrl,
  rightSvgUrl,
  rounded = false,
  onBadgeClick,
  ...props
}: BadgeProps) {
  const isIconOnly = !text && Boolean(leftIcon || rightIcon || leftSvgUrl || rightSvgUrl)

  const className = [
    'veratag-badge',
    `veratag-badge--${color}`,
    `veratag-badge--${size}`,
    rounded ? 'veratag-badge--rounded' : '',
    isIconOnly && rounded ? 'veratag-badge--circle' : '',
  ]
    .filter(Boolean)
    .join(' ')

  const content = (
    <BadgeContent
      leftIcon={leftIcon}
      leftSvgUrl={leftSvgUrl}
      rightIcon={rightIcon}
      rightSvgUrl={rightSvgUrl}
      text={text}
    />
  )

  if ('href' in props && typeof props.href === 'string') {
    const { href, target = '_self', rel, ...anchorProps } = props

    const computedRel = rel ?? (target === '_blank' ? 'noopener noreferrer' : undefined)

    return (
      <a
        {...anchorProps}
        aria-label={ariaLabel}
        aria-labelledby={labelledBy}
        className={`${className} veratag-badge--clickable`}
        href={href}
        onClick={onBadgeClick}
        rel={computedRel}
        target={target}
      >
        {content}
      </a>
    )
  }

  if (clickable) {
    const buttonProps = props as Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'children'>

    return (
      <button
        {...buttonProps}
        aria-label={ariaLabel}
        aria-labelledby={labelledBy}
        className={`${className} veratag-badge--clickable veratag-badge--button`}
        onClick={onBadgeClick}
        type="button"
      >
        {content}
      </button>
    )
  }

  const spanProps = props as HTMLAttributes<HTMLSpanElement>

  return (
    <span {...spanProps} aria-label={ariaLabel} aria-labelledby={labelledBy} className={className}>
      {content}
    </span>
  )
}
