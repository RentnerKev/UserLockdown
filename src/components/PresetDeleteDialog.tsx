import { translate as t } from '@nextcloud/l10n'

import { useRemovePresetMutation } from '../queries/permissions'
import type { PermissionPreset } from '../types/permissions'
import { ModalDialog } from './ModalDialog'

type PresetDeleteDialogProps = {
  preset: PermissionPreset
  onClose: () => void
}

const readableError = (error: unknown): string =>
  error instanceof Error
    ? error.message
    : t('user_lockdown', 'The request could not be completed. Please try again.')

export const PresetDeleteDialog = ({ preset, onClose }: PresetDeleteDialogProps) => {
  const removeMutation = useRemovePresetMutation()

  const closeDialog = () => {
    if (!removeMutation.isPending) {
      onClose()
    }
  }

  return (
    <ModalDialog
      titleId="user-lockdown-delete-preset-title"
      descriptionId="user-lockdown-delete-preset-description"
      closeDisabled={removeMutation.isPending}
      role="alertdialog"
      onClose={closeDialog}
    >
      <h2 id="user-lockdown-delete-preset-title">{t('user_lockdown', 'Delete preset?')}</h2>
      <p id="user-lockdown-delete-preset-description">
        {t(
          'user_lockdown',
          'The preset “{presetName}” will be removed. Permissions already copied to users remain unchanged.',
          { presetName: preset.name },
        )}
      </p>

      {removeMutation.isError && (
        <p className="user-lockdown-message user-lockdown-message--error" role="alert">
          {t('user_lockdown', 'The preset could not be deleted.')}{' '}
          {readableError(removeMutation.error)}
        </p>
      )}

      <div className="user-lockdown-dialog__actions">
        <button
          className="user-lockdown-button user-lockdown-button--secondary"
          type="button"
          disabled={removeMutation.isPending}
          onClick={closeDialog}
        >
          {t('user_lockdown', 'Cancel')}
        </button>
        <button
          className="user-lockdown-button user-lockdown-button--danger"
          type="button"
          disabled={removeMutation.isPending}
          onClick={() => removeMutation.mutate(preset.id, { onSuccess: onClose })}
        >
          {removeMutation.isPending
            ? t('user_lockdown', 'Deleting…')
            : t('user_lockdown', 'Delete preset')}
        </button>
      </div>
    </ModalDialog>
  )
}
