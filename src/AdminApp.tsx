import { translate as t } from '@nextcloud/l10n'

import { RestrictedUserList } from './components/RestrictedUserList'
import { UserSearch } from './components/UserSearch'
import { useRestrictedUsersQuery } from './queries/users'
import type { AdminConfig } from './schemas/api'

type AdminAppProps = {
  config: AdminConfig
}

const readableError = (error: unknown): string =>
  error instanceof Error
    ? error.message
    : t('user_lockdown', 'The request could not be completed. Please try again.')

const LoadingState = () => (
  <section className="user-lockdown-card" aria-live="polite" aria-busy="true">
    <div
      className="user-lockdown-loading-state"
      role="status"
      aria-label={t('user_lockdown', 'Loading restricted users')}
    >
      <span className="user-lockdown-spinner" aria-hidden="true" />
      <span>{t('user_lockdown', 'Loading restricted users…')}</span>
    </div>
  </section>
)

export const AdminApp = ({ config }: AdminAppProps) => {
  const restrictedUsersQuery = useRestrictedUsersQuery()
  const restrictedUsers = restrictedUsersQuery.data ?? []
  const restrictedUserIds = new Set(restrictedUsers.map((user) => user.id))

  return (
    <main className="user-lockdown-admin">
      <header className="user-lockdown-page-header">
        <span className="user-lockdown-page-header__icon" aria-hidden="true" />
        <div>
          <h1>{t('user_lockdown', 'User Lockdown')}</h1>
          <p>
            {t(
              'user_lockdown',
              'Restrict selected users to viewing and downloading existing files.',
            )}
          </p>
        </div>
      </header>

      {restrictedUsersQuery.isPending ? (
        <LoadingState />
      ) : restrictedUsersQuery.isError ? (
        <section className="user-lockdown-card">
          <div className="user-lockdown-message user-lockdown-message--error" role="alert">
            <span>
              {t('user_lockdown', 'Restricted users could not be loaded.')}{' '}
              {readableError(restrictedUsersQuery.error)}
            </span>
            <button
              className="user-lockdown-link-button"
              type="button"
              onClick={() => restrictedUsersQuery.refetch()}
            >
              {t('user_lockdown', 'Try again')}
            </button>
          </div>
        </section>
      ) : (
        <>
          <UserSearch config={config} restrictedUserIds={restrictedUserIds} />
          <RestrictedUserList users={restrictedUsers} />
        </>
      )}
    </main>
  )
}
