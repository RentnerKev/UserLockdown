import { translate as t } from '@nextcloud/l10n'
import { useState } from 'react'

import { useUpdateRestrictedUserMutation } from '../queries/users'
import {
  canonicalizePermissions,
  matchingPreset,
  permissionsEqual,
  presetDisplayName,
  type PermissionPreset,
  type PermissionSet,
} from '../types/permissions'
import type { RestrictedUser } from '../types/user'
import { Avatar } from './Avatar'
import { ModalDialog } from './ModalDialog'
import { PermissionEditor } from './PermissionEditor'

type UserPermissionsDialogProps = {
  user: RestrictedUser
  presets: PermissionPreset[]
  onClose: () => void
}

const customPresetValue = '__custom__'

const readableError = (error: unknown): string =>
  error instanceof Error
    ? error.message
    : t('user_lockdown', 'The request could not be completed. Please try again.')

export const UserPermissionsDialog = ({ user, presets, onClose }: UserPermissionsDialogProps) => {
  const [permissions, setPermissions] = useState<PermissionSet>(() =>
    canonicalizePermissions(user.permissions),
  )
  const updateMutation = useUpdateRestrictedUserMutation()
  const selectedPreset = matchingPreset(permissions, presets)
  const dirty = !permissionsEqual(permissions, user.permissions)

  const closeDialog = () => {
    if (!updateMutation.isPending) {
      onClose()
    }
  }

  const savePermissions = () => {
    if (!dirty || updateMutation.isPending) {
      return
    }

    updateMutation.mutate(
      { userId: user.id, permissions: canonicalizePermissions(permissions) },
      { onSuccess: onClose },
    )
  }

  return (
    <ModalDialog
      titleId="user-lockdown-user-permissions-title"
      descriptionId="user-lockdown-user-permissions-description"
      closeDisabled={updateMutation.isPending}
      wide
      onClose={closeDialog}
    >
      <div className="user-lockdown-dialog__user">
        <Avatar user={user} />
        <div>
          <h2 id="user-lockdown-user-permissions-title">
            {t('user_lockdown', 'Edit permissions')}
          </h2>
          <p id="user-lockdown-user-permissions-description">
            {t('user_lockdown', 'Choose what {userName} is allowed to do.', {
              userName: user.displayName,
            })}
          </p>
        </div>
      </div>

      <label className="user-lockdown-label" htmlFor="user-lockdown-user-preset">
        {t('user_lockdown', 'Apply preset')}
      </label>
      <select
        id="user-lockdown-user-preset"
        className="user-lockdown-select"
        value={selectedPreset?.id ?? customPresetValue}
        disabled={updateMutation.isPending}
        onChange={(event) => {
          const preset = presets.find((candidate) => candidate.id === event.currentTarget.value)
          if (preset !== undefined) {
            setPermissions(canonicalizePermissions(preset.permissions))
          }
        }}
      >
        <option value={customPresetValue} disabled>
          {t('user_lockdown', 'Custom permissions')}
        </option>
        {presets.map((preset) => (
          <option key={preset.id} value={preset.id}>
            {presetDisplayName(preset)}
          </option>
        ))}
      </select>

      <PermissionEditor
        idPrefix="user-lockdown-user-permission"
        permissions={permissions}
        disabled={updateMutation.isPending}
        onChange={(nextPermissions) => {
          updateMutation.reset()
          setPermissions(nextPermissions)
        }}
      />

      {updateMutation.isError && (
        <p className="user-lockdown-message user-lockdown-message--error" role="alert">
          {t('user_lockdown', 'The permissions could not be saved.')}{' '}
          {readableError(updateMutation.error)}
        </p>
      )}

      <div className="user-lockdown-dialog__actions">
        <button
          className="user-lockdown-button user-lockdown-button--secondary"
          type="button"
          disabled={updateMutation.isPending}
          onClick={closeDialog}
        >
          {t('user_lockdown', 'Cancel')}
        </button>
        <button
          className="user-lockdown-button user-lockdown-button--primary"
          type="button"
          disabled={!dirty || updateMutation.isPending}
          onClick={savePermissions}
        >
          {updateMutation.isPending
            ? t('user_lockdown', 'Saving…')
            : t('user_lockdown', 'Save permissions')}
        </button>
      </div>
    </ModalDialog>
  )
}
