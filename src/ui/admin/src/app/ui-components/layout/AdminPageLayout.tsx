import type { ReactNode } from 'react'
import { ContentContainer } from './content-container/ContentContainer'
import { TopBar } from './top-bar/TopBar'
import './admin-page-layout.css'

type AdminPageLayoutProps = {
  channelDashboardUrl: string
  children: ReactNode
  wordmarkUrl: string
}

export function AdminPageLayout({
  channelDashboardUrl,
  children,
  wordmarkUrl,
}: AdminPageLayoutProps) {
  return (
    <div className="veratag-admin-page-layout">
      <TopBar channelDashboardUrl={channelDashboardUrl} wordmarkUrl={wordmarkUrl} />
      <main className="veratag-admin-page-layout__main">
        <ContentContainer>{children}</ContentContainer>
      </main>
    </div>
  )
}
