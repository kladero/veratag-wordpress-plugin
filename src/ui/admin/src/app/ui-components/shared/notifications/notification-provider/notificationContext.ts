import { createContext, useContext } from 'react'

export type NotificationTone = 'success' | 'error'

export type NotificationContextValue = {
  showError: (message: string) => void
  showSuccess: (message: string) => void
}

export const NotificationContext = createContext<NotificationContextValue | null>(null)

export function useNotifications(): NotificationContextValue {
  const notificationContext = useContext(NotificationContext)

  if (notificationContext === null) {
    throw new Error('useNotifications must be used inside NotificationProvider.')
  }

  return notificationContext
}
