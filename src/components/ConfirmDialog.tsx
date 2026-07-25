import { translate as t } from '@nextcloud/l10n'
import { useEffect, useRef } from 'react'

type ConfirmDialogProps = {
  userName: string
  pending: boolean
  errorMessage: string | null
  onCancel: () => void
  onConfirm: () => void
}

export const ConfirmDialog = ({
  userName,
  pending,
  errorMessage,
  onCancel,
  onConfirm,
}: ConfirmDialogProps) => {
  const cancelButtonRef = useRef<HTMLButtonElement>(null)
  const confirmButtonRef = useRef<HTMLButtonElement>(null)

  useEffect(() => {
    cancelButtonRef.current?.focus()
  }, [])

  useEffect(() => {
    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape' && !pending) {
        event.preventDefault()
        onCancel()
        return
      }

      if (event.key !== 'Tab') {
        return
      }

      if (event.shiftKey && document.activeElement === cancelButtonRef.current) {
        event.preventDefault()
        confirmButtonRef.current?.focus()
      } else if (!event.shiftKey && document.activeElement === confirmButtonRef.current) {
        event.preventDefault()
        cancelButtonRef.current?.focus()
      }
    }

    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [onCancel, pending])

  return (
    <div className="user-lockdown-dialog-backdrop">
      <section
        className="user-lockdown-dialog"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="user-lockdown-dialog-title"
        aria-describedby="user-lockdown-dialog-description"
      >
        <h2 id="user-lockdown-dialog-title">{t('user_lockdown', 'Remove restriction?')}</h2>
        <p id="user-lockdown-dialog-description">
          {t('user_lockdown', '{userName} will regain access to all available apps and actions.', {
            userName,
          })}
        </p>

        {errorMessage !== null && (
          <p className="user-lockdown-message user-lockdown-message--error" role="alert">
            {errorMessage}
          </p>
        )}

        <div className="user-lockdown-dialog__actions">
          <button
            ref={cancelButtonRef}
            className="user-lockdown-button user-lockdown-button--secondary"
            type="button"
            disabled={pending}
            onClick={onCancel}
          >
            {t('user_lockdown', 'Cancel')}
          </button>
          <button
            ref={confirmButtonRef}
            className="user-lockdown-button user-lockdown-button--danger"
            type="button"
            disabled={pending}
            onClick={onConfirm}
          >
            {pending ? t('user_lockdown', 'Removing…') : t('user_lockdown', 'Remove restriction')}
          </button>
        </div>
      </section>
    </div>
  )
}
