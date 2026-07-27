import { translate as t } from '@nextcloud/l10n'

import { RestrictedUserList } from './components/RestrictedUserList'
import { UserSearch } from './components/UserSearch'
import { PermissionSettings } from './components/PermissionSettings'
import { usePermissionSettingsQuery } from './queries/permissions'
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
      aria-label={t('user_lockdown', 'Loading access settings')}
    >
      <span className="user-lockdown-spinner" aria-hidden="true" />
      <span>{t('user_lockdown', 'Loading access settings…')}</span>
    </div>
  </section>
)

export const AdminApp = ({ config }: AdminAppProps) => {
  const restrictedUsersQuery = useRestrictedUsersQuery()
  const permissionSettingsQuery = usePermissionSettingsQuery()
  const restrictedUsers = restrictedUsersQuery.data ?? []
  const restrictedUserIds = new Set(restrictedUsers.map((user) => user.id))
  const loading = restrictedUsersQuery.isPending || permissionSettingsQuery.isPending
  const loadError = restrictedUsersQuery.error ?? permissionSettingsQuery.error

  return (
    <main className="user-lockdown-admin">
      <header className="user-lockdown-page-header">
        <span className="user-lockdown-page-header__icon" aria-hidden="true" />
        <div>
          <h1>{t('user_lockdown', 'User Lockdown')}</h1>
          <p>{t('user_lockdown', 'Manage detailed access permissions for selected users.')}</p>
        </div>
      </header>

      {loading ? (
        <LoadingState />
      ) : restrictedUsersQuery.isError || permissionSettingsQuery.isError ? (
        <section className="user-lockdown-card">
          <div className="user-lockdown-message user-lockdown-message--error" role="alert">
            <span>
              {t('user_lockdown', 'Access settings could not be loaded.')}{' '}
              {readableError(loadError)}
            </span>
            <button
              className="user-lockdown-link-button"
              type="button"
              onClick={() => {
                void restrictedUsersQuery.refetch()
                void permissionSettingsQuery.refetch()
              }}
            >
              {t('user_lockdown', 'Try again')}
            </button>
          </div>
        </section>
      ) : permissionSettingsQuery.data !== undefined ? (
        <>
          <PermissionSettings settings={permissionSettingsQuery.data} />
          <UserSearch
            config={config}
            restrictedUserIds={restrictedUserIds}
            defaultPermissions={permissionSettingsQuery.data.defaultPermissions}
            presets={permissionSettingsQuery.data.presets}
          />
          <RestrictedUserList
            users={restrictedUsers}
            presets={permissionSettingsQuery.data.presets}
          />
        </>
      ) : null}
    </main>
  )
}
