import type { CSSProperties } from 'react'
import './spinner.css'

type SpinnerSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl'
type SpinnerProps = {
  ariaLabel?: string
  /** @deprecated The spinner always uses --veratag-color-primary. */
  color?: 'brand'
  size?: SpinnerSize
  sizeRem?: number
  speed?: number
  thicknessRem?: number
}

function resolveSizeRem(size: SpinnerSize, sizeRem?: number): number {
  if (typeof sizeRem === 'number') {
    return sizeRem
  }

  switch (size) {
    case 'xs':
      return 0.75
    case 'sm':
      return 1.0
    case 'md':
      return 1.5
    case 'lg':
      return 2.25
    case 'xl':
      return 3.0
    default:
      return 1.5
  }
}

function resolveStrokeRem(finalSizeRem: number, thicknessRem?: number): number {
  if (typeof thicknessRem === 'number') {
    return thicknessRem
  }

  const proportionalStroke = finalSizeRem * 0.14
  return Math.min(Math.max(proportionalStroke, 0.12), 0.35)
}

export function Spinner({
  ariaLabel = 'Loading',
  size = 'md',
  sizeRem,
  speed = 1000,
  thicknessRem,
}: SpinnerProps) {
  const finalSizeRem = resolveSizeRem(size, sizeRem)
  const strokeRem = resolveStrokeRem(finalSizeRem, thicknessRem)

  const style: CSSProperties = {
    ['--veratag-spinner-size-rem' as string]: String(finalSizeRem),
    ['--veratag-spinner-speed' as string]: `${speed}ms`,
    ['--veratag-spinner-stroke-rem' as string]: String(strokeRem),
  }

  return <span aria-label={ariaLabel} className="veratag-spinner" role="status" style={style} />
}
