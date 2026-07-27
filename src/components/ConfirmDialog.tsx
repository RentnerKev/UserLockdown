import { translate as t } from '@nextcloud/l10n'

import { ModalDialog } from './ModalDialog'

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
  return (
    <ModalDialog
      titleId="user-lockdown-dialog-title"
      descriptionId="user-lockdown-dialog-description"
      closeDisabled={pending}
      role="alertdialog"
      onClose={onCancel}
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
          className="user-lockdown-button user-lockdown-button--secondary"
          type="button"
          disabled={pending}
          onClick={onCancel}
        >
          {t('user_lockdown', 'Cancel')}
        </button>
        <button
          className="user-lockdown-button user-lockdown-button--danger"
          type="button"
          disabled={pending}
          onClick={onConfirm}
        >
          {pending ? t('user_lockdown', 'Removing…') : t('user_lockdown', 'Remove restriction')}
        </button>
      </div>
    </ModalDialog>
  )
}
