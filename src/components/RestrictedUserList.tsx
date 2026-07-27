import { translate as t } from '@nextcloud/l10n'
import { useState } from 'react'

import { useRemoveRestrictedUserMutation } from '../queries/users'
import { matchingPreset, presetDisplayName, type PermissionPreset } from '../types/permissions'
import type { RestrictedUser } from '../types/user'
import { Avatar } from './Avatar'
import { ConfirmDialog } from './ConfirmDialog'
import { UserPermissionsDialog } from './UserPermissionsDialog'

type RestrictedUserListProps = {
  users: RestrictedUser[]
  presets: PermissionPreset[]
}

const readableError = (error: unknown): string =>
  error instanceof Error
    ? error.message
    : t('user_lockdown', 'The request could not be completed. Please try again.')

export const RestrictedUserList = ({ users, presets }: RestrictedUserListProps) => {
  const [dialogUser, setDialogUser] = useState<RestrictedUser | null>(null)
  const [editUser, setEditUser] = useState<RestrictedUser | null>(null)
  const removeMutation = useRemoveRestrictedUserMutation()

  const closeDialog = () => {
    if (removeMutation.isPending) {
      return
    }

    setDialogUser(null)
  }

  const openDialog = (user: RestrictedUser) => {
    removeMutation.reset()
    setDialogUser(user)
  }

  const confirmRemoval = () => {
    if (dialogUser === null) {
      return
    }

    removeMutation.mutate(dialogUser.id, {
      onSuccess: () => setDialogUser(null),
    })
  }

  return (
    <section className="user-lockdown-card" aria-labelledby="user-lockdown-list-title">
      <div className="user-lockdown-section-heading">
        <div>
          <h2 id="user-lockdown-list-title">{t('user_lockdown', 'Managed users')}</h2>
          <p>{t('user_lockdown', 'Review and edit the permissions assigned to each user.')}</p>
        </div>
        <span
          className="user-lockdown-count"
          aria-label={t('user_lockdown', '{count} users', { count: users.length })}
        >
          {users.length}
        </span>
      </div>

      {users.length === 0 ? (
        <div className="user-lockdown-empty-state">
          <span className="user-lockdown-empty-state__icon" aria-hidden="true" />
          <h3>{t('user_lockdown', 'No restricted users')}</h3>
          <p>{t('user_lockdown', 'Search for a user above to add the first restriction.')}</p>
        </div>
      ) : (
        <ul className="user-lockdown-user-list">
          {users.map((user) => {
            const preset = matchingPreset(user.permissions, presets)

            return (
              <li key={user.id} className="user-lockdown-user-row">
                <Avatar user={user} />
                <span className="user-lockdown-user-identity">
                  <strong>{user.displayName}</strong>
                  <span>{user.id}</span>
                </span>
                <span className="user-lockdown-status">
                  <span aria-hidden="true" />
                  {preset === null
                    ? t('user_lockdown', 'Custom permissions')
                    : presetDisplayName(preset)}
                </span>
                <span className="user-lockdown-user-row__actions">
                  <button
                    className="user-lockdown-button user-lockdown-button--secondary"
                    type="button"
                    onClick={() => setEditUser(user)}
                  >
                    {t('user_lockdown', 'Edit')}
                    <span className="user-lockdown-visually-hidden"> {user.displayName}</span>
                  </button>
                  <button
                    className="user-lockdown-button user-lockdown-button--secondary"
                    type="button"
                    onClick={() => openDialog(user)}
                  >
                    {t('user_lockdown', 'Remove')}
                    <span className="user-lockdown-visually-hidden"> {user.displayName}</span>
                  </button>
                </span>
              </li>
            )
          })}
        </ul>
      )}

      {dialogUser !== null && (
        <ConfirmDialog
          userName={dialogUser.displayName}
          pending={removeMutation.isPending}
          errorMessage={removeMutation.isError ? readableError(removeMutation.error) : null}
          onCancel={closeDialog}
          onConfirm={confirmRemoval}
        />
      )}
      {editUser !== null && (
        <UserPermissionsDialog
          user={editUser}
          presets={presets}
          onClose={() => setEditUser(null)}
        />
      )}
    </section>
  )
}
