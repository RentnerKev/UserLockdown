import { translate as t } from '@nextcloud/l10n'
import { type FormEvent, useState } from 'react'

import { useCreatePresetMutation, useUpdatePresetMutation } from '../queries/permissions'
import {
  canonicalizePermissions,
  permissionsEqual,
  presetDisplayName,
  type PermissionPreset,
  type PermissionSet,
} from '../types/permissions'
import { ModalDialog } from './ModalDialog'
import { PermissionEditor } from './PermissionEditor'

type PresetDialogProps = {
  preset: PermissionPreset | null
  presets: PermissionPreset[]
  defaultPermissions: PermissionSet
  onClose: () => void
}

const readableError = (error: unknown): string =>
  error instanceof Error
    ? error.message
    : t('user_lockdown', 'The request could not be completed. Please try again.')

export const PresetDialog = ({
  preset,
  presets,
  defaultPermissions,
  onClose,
}: PresetDialogProps) => {
  const [name, setName] = useState(preset?.name ?? '')
  const [permissions, setPermissions] = useState<PermissionSet>(() =>
    canonicalizePermissions(preset?.permissions ?? defaultPermissions),
  )
  const createMutation = useCreatePresetMutation()
  const updateMutation = useUpdatePresetMutation()
  const activeMutation = preset === null ? createMutation : updateMutation
  const normalizedName = name.trim()
  const duplicateName = presets.some(
    (candidate) =>
      candidate.id !== preset?.id &&
      presetDisplayName(candidate).trim().toLocaleLowerCase() ===
        normalizedName.toLocaleLowerCase(),
  )
  const validName = normalizedName.length > 0 && normalizedName.length <= 64 && !duplicateName
  const dirty =
    preset === null ||
    normalizedName !== preset.name ||
    !permissionsEqual(permissions, preset.permissions)

  const closeDialog = () => {
    if (!activeMutation.isPending) {
      onClose()
    }
  }

  const submitPreset = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (!validName || !dirty || activeMutation.isPending) {
      return
    }

    const input = {
      name: normalizedName,
      permissions: canonicalizePermissions(permissions),
    }

    if (preset === null) {
      createMutation.mutate(input, { onSuccess: onClose })
    } else {
      updateMutation.mutate({ presetId: preset.id, input }, { onSuccess: onClose })
    }
  }

  return (
    <ModalDialog
      titleId="user-lockdown-preset-dialog-title"
      descriptionId="user-lockdown-preset-dialog-description"
      closeDisabled={activeMutation.isPending}
      wide
      onClose={closeDialog}
    >
      <form onSubmit={submitPreset}>
        <h2 id="user-lockdown-preset-dialog-title">
          {preset === null
            ? t('user_lockdown', 'Create preset')
            : t('user_lockdown', 'Edit preset')}
        </h2>
        <p id="user-lockdown-preset-dialog-description">
          {t(
            'user_lockdown',
            'Presets copy a permission set to a user. Later preset changes do not change users automatically.',
          )}
        </p>

        <label className="user-lockdown-label" htmlFor="user-lockdown-preset-name">
          {t('user_lockdown', 'Preset name')}
        </label>
        <input
          id="user-lockdown-preset-name"
          className="user-lockdown-input"
          type="text"
          value={name}
          maxLength={64}
          autoComplete="off"
          disabled={activeMutation.isPending}
          aria-invalid={duplicateName}
          aria-describedby={duplicateName ? 'user-lockdown-preset-name-error' : undefined}
          onChange={(event) => {
            activeMutation.reset()
            setName(event.currentTarget.value)
          }}
        />
        {duplicateName && (
          <p id="user-lockdown-preset-name-error" className="user-lockdown-help" role="alert">
            {t('user_lockdown', 'A preset with this name already exists.')}
          </p>
        )}

        <PermissionEditor
          idPrefix="user-lockdown-preset-permission"
          permissions={permissions}
          disabled={activeMutation.isPending}
          onChange={(nextPermissions) => {
            activeMutation.reset()
            setPermissions(nextPermissions)
          }}
        />

        {!permissions.fullAccess && !permissions.viewFiles && !permissions.changePassword && (
          <p className="user-lockdown-message" role="status">
            {t('user_lockdown', 'Users with this preset can only sign out.')}
          </p>
        )}

        {activeMutation.isError && (
          <p className="user-lockdown-message user-lockdown-message--error" role="alert">
            {t('user_lockdown', 'The preset could not be saved.')}{' '}
            {readableError(activeMutation.error)}
          </p>
        )}

        <div className="user-lockdown-dialog__actions">
          <button
            className="user-lockdown-button user-lockdown-button--secondary"
            type="button"
            disabled={activeMutation.isPending}
            onClick={closeDialog}
          >
            {t('user_lockdown', 'Cancel')}
          </button>
          <button
            className="user-lockdown-button user-lockdown-button--primary"
            type="submit"
            disabled={!validName || !dirty || activeMutation.isPending}
          >
            {activeMutation.isPending
              ? t('user_lockdown', 'Saving…')
              : t('user_lockdown', 'Save preset')}
          </button>
        </div>
      </form>
    </ModalDialog>
  )
}
