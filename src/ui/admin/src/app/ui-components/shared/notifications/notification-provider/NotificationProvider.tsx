import { type PropsWithChildren, useCallback, useMemo, useState } from 'react'
import { loadVeratagAdminConfig } from '../../../../config/loadVeratagAdminConfig'
import {
  NotificationContext,
  type NotificationContextValue,
  type NotificationTone,
} from './notificationContext'
import './notification-provider.css'

type NotificationItem = {
  id: number
  message: string
  tone: NotificationTone
  title: string
}

export function NotificationProvider({ children }: PropsWithChildren) {
  const [notifications, setNotifications] = useState<NotificationItem[]>([])
  const config = loadVeratagAdminConfig()

  const removeNotification = useCallback((id: number) => {
    setNotifications((currentNotifications) =>
      currentNotifications.filter((notification) => notification.id !== id),
    )
  }, [])

  const showNotification = useCallback(
    (tone: NotificationTone, message: string) => {
      const notificationId = Date.now() + Math.floor(Math.random() * 1000)
      const notification: NotificationItem = {
        id: notificationId,
        message,
        tone,
        title: tone === 'success' ? 'Success' : 'Error',
      }

      setNotifications((currentNotifications) => [...currentNotifications, notification])

      // Errors remain until dismissed so users can read and act on them.
      // Success notifications are transient but not announced assertively.
      if (tone === 'success') {
        window.setTimeout(() => removeNotification(notificationId), 5000)
      }
    },
    [removeNotification],
  )

  const contextValue = useMemo<NotificationContextValue>(
    () => ({
      showError: (message: string) => showNotification('error', message),
      showSuccess: (message: string) => showNotification('success', message),
    }),
    [showNotification],
  )

  return (
    <NotificationContext.Provider value={contextValue}>
      {children}
      <div className="veratag-notification-stack">
        {notifications.map((notification) => (
          <div
            key={notification.id}
            className={`veratag-notification veratag-notification--${notification.tone}`}
            aria-atomic="true"
            role={notification.tone === 'error' ? 'alert' : 'status'}
          >
            <div className="veratag-notification__icon" aria-hidden="true">
              <img
                alt=""
                className="veratag-notification__icon-image"
                src={
                  notification.tone === 'success'
                    ? config.statusIcons.success
                    : config.statusIcons.failure
                }
              />
            </div>

            <div className="veratag-notification__content">
              <div className="veratag-notification__header">
                <strong className="veratag-notification__title">{notification.title}</strong>
                <button
                  aria-label={`Dismiss ${notification.tone} notification`}
                  className="veratag-notification__dismiss"
                  onClick={() => removeNotification(notification.id)}
                  type="button"
                >
                  <span className="veratag-notification__dismiss-glyph">×</span>
                </button>
              </div>

              <div className="veratag-notification__message-wrapper">
                <p className="veratag-notification__message">{notification.message}</p>
              </div>
            </div>
          </div>
        ))}
      </div>
    </NotificationContext.Provider>
  )
}
