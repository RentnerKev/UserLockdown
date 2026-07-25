import { translate as t } from '@nextcloud/l10n'
import { useRef, useState } from 'react'

import { useRemoveRestrictedUserMutation } from '../queries/users'
import type { RestrictedUser } from '../types/user'
import { Avatar } from './Avatar'
import { ConfirmDialog } from './ConfirmDialog'

type RestrictedUserListProps = {
  users: RestrictedUser[]
}

const readableError = (error: unknown): string =>
  error instanceof Error
    ? error.message
    : t('user_lockdown', 'The request could not be completed. Please try again.')

export const RestrictedUserList = ({ users }: RestrictedUserListProps) => {
  const [dialogUser, setDialogUser] = useState<RestrictedUser | null>(null)
  const triggerButtonRef = useRef<HTMLButtonElement | null>(null)
  const removeMutation = useRemoveRestrictedUserMutation()

  const closeDialog = () => {
    if (removeMutation.isPending) {
      return
    }

    setDialogUser(null)
    window.requestAnimationFrame(() => triggerButtonRef.current?.focus())
  }

  const openDialog = (user: RestrictedUser, trigger: HTMLButtonElement) => {
    removeMutation.reset()
    triggerButtonRef.current = trigger
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
          <h2 id="user-lockdown-list-title">{t('user_lockdown', 'Restricted users')}</h2>
          <p>{t('user_lockdown', 'These users can only view and download existing files.')}</p>
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
          {users.map((user) => (
            <li key={user.id} className="user-lockdown-user-row">
              <Avatar user={user} />
              <span className="user-lockdown-user-identity">
                <strong>{user.displayName}</strong>
                <span>{user.id}</span>
              </span>
              <span className="user-lockdown-status">
                <span aria-hidden="true" />
                {t('user_lockdown', 'Restricted')}
              </span>
              <button
                className="user-lockdown-button user-lockdown-button--secondary user-lockdown-user-row__remove"
                type="button"
                onClick={(event) => openDialog(user, event.currentTarget)}
              >
                {t('user_lockdown', 'Remove')}
                <span className="user-lockdown-visually-hidden"> {user.displayName}</span>
              </button>
            </li>
          ))}
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
    </section>
  )
}
