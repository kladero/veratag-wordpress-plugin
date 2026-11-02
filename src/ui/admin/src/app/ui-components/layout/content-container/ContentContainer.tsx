import type { ReactNode } from 'react'
import './content-container.css'

type ContentContainerProps = {
  children: ReactNode
}

export function ContentContainer({ children }: ContentContainerProps) {
  return <div className="veratag-content-container">{children}</div>
}
