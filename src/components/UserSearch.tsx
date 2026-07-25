import { translate as t } from '@nextcloud/l10n'
import { type KeyboardEvent as ReactKeyboardEvent, useEffect, useRef, useState } from 'react'

import { useAddRestrictedUserMutation, useUserSearchQuery } from '../queries/users'
import type { AdminConfig } from '../schemas/api'
import type { SearchUser } from '../types/user'
import { Avatar } from './Avatar'

type UserSearchProps = {
  config: AdminConfig
  restrictedUserIds: ReadonlySet<string>
}

const readableError = (error: unknown): string =>
  error instanceof Error
    ? error.message
    : t('user_lockdown', 'The request could not be completed. Please try again.')

const useDebouncedValue = (value: string, delay: number): string => {
  const [debouncedValue, setDebouncedValue] = useState(value)

  useEffect(() => {
    const timeout = window.setTimeout(() => setDebouncedValue(value), delay)
    return () => window.clearTimeout(timeout)
  }, [delay, value])

  return debouncedValue
}

export const UserSearch = ({ config, restrictedUserIds }: UserSearchProps) => {
  const [searchText, setSearchText] = useState('')
  const [selectedUser, setSelectedUser] = useState<SearchUser | null>(null)
  const inputRef = useRef<HTMLInputElement>(null)
  const resultButtonRefs = useRef<Map<string, HTMLButtonElement>>(new Map())
  const normalizedSearchText = searchText.trim()
  const debouncedSearchText = useDebouncedValue(normalizedSearchText, config.searchDebounceMs)
  const searchEnabled = debouncedSearchText.length >= config.minimumSearchLength
  const searchQuery = useUserSearchQuery(debouncedSearchText, searchEnabled)
  const addMutation = useAddRestrictedUserMutation()
  const users = searchQuery.data ?? []

  const isRestricted = (user: SearchUser): boolean =>
    user.restricted || restrictedUserIds.has(user.id)

  const addUser = (user: SearchUser) => {
    if (isRestricted(user) || addMutation.isPending) {
      return
    }

    setSelectedUser(user)
    addMutation.mutate(user.id, {
      onSuccess: () => setSearchText(''),
      onSettled: () => setSelectedUser(null),
    })
  }

  const focusResult = (index: number) => {
    const user = users[index]
    if (user === undefined) {
      return
    }

    resultButtonRefs.current.get(user.id)?.focus()
  }

  const handleInputKeyDown = (event: ReactKeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'ArrowDown' && users.length > 0) {
      event.preventDefault()
      focusResult(0)
    }
  }

  const handleResultKeyDown = (
    event: ReactKeyboardEvent<HTMLButtonElement>,
    resultIndex: number,
  ) => {
    if (event.key === 'ArrowDown') {
      event.preventDefault()
      focusResult(Math.min(resultIndex + 1, users.length - 1))
    } else if (event.key === 'ArrowUp') {
      event.preventDefault()
      if (resultIndex === 0) {
        inputRef.current?.focus()
      } else {
        focusResult(resultIndex - 1)
      }
    } else if (event.key === 'Escape') {
      event.preventDefault()
      inputRef.current?.focus()
    }
  }

  const showMinimumHint =
    normalizedSearchText.length > 0 && normalizedSearchText.length < config.minimumSearchLength
  const showEmptyResults = searchEnabled && searchQuery.isSuccess && users.length === 0

  return (
    <section className="user-lockdown-card" aria-labelledby="user-lockdown-search-title">
      <div className="user-lockdown-section-heading">
        <div>
          <h2 id="user-lockdown-search-title">{t('user_lockdown', 'Add users')}</h2>
          <p>
            {t(
              'user_lockdown',
              'Search by display name or user ID. Administrators cannot be restricted.',
            )}
          </p>
        </div>
      </div>

      <label className="user-lockdown-label" htmlFor="user-lockdown-user-search">
        {t('user_lockdown', 'Search users')}
      </label>
      <div className="user-lockdown-search-field">
        <span className="user-lockdown-search-field__icon" aria-hidden="true" />
        <input
          ref={inputRef}
          id="user-lockdown-user-search"
          className="user-lockdown-input"
          type="search"
          value={searchText}
          autoComplete="off"
          aria-controls="user-lockdown-search-results"
          aria-describedby="user-lockdown-search-help"
          placeholder={t('user_lockdown', 'Name or user ID')}
          onChange={(event) => setSearchText(event.currentTarget.value)}
          onKeyDown={handleInputKeyDown}
        />
        {searchQuery.isFetching && (
          <span
            className="user-lockdown-spinner user-lockdown-search-field__spinner"
            role="status"
            aria-label={t('user_lockdown', 'Searching users')}
          />
        )}
      </div>
      <p id="user-lockdown-search-help" className="user-lockdown-help">
        {t(
          'user_lockdown',
          'Enter at least {count} characters. Use the arrow keys to move through results.',
          { count: config.minimumSearchLength },
        )}
      </p>

      {showMinimumHint && (
        <p className="user-lockdown-message" role="status">
          {t('user_lockdown', 'Enter more characters to start searching.')}
        </p>
      )}

      {searchQuery.isError && (
        <div className="user-lockdown-message user-lockdown-message--error" role="alert">
          <span>
            {t('user_lockdown', 'Users could not be searched.')} {readableError(searchQuery.error)}
          </span>
          <button
            className="user-lockdown-link-button"
            type="button"
            onClick={() => searchQuery.refetch()}
          >
            {t('user_lockdown', 'Try again')}
          </button>
        </div>
      )}

      {addMutation.isError && (
        <p className="user-lockdown-message user-lockdown-message--error" role="alert">
          {t('user_lockdown', 'The user could not be restricted.')}{' '}
          {readableError(addMutation.error)}
        </p>
      )}

      {showEmptyResults && (
        <p className="user-lockdown-message" role="status">
          {t('user_lockdown', 'No eligible users found.')}
        </p>
      )}

      <ul
        id="user-lockdown-search-results"
        className="user-lockdown-search-results"
        aria-label={t('user_lockdown', 'Search results')}
      >
        {users.map((user, index) => {
          const alreadyRestricted = isRestricted(user)
          const addingThisUser = addMutation.isPending && selectedUser?.id === user.id

          return (
            <li key={user.id} className="user-lockdown-search-result">
              <Avatar user={user} size="small" />
              <span className="user-lockdown-user-identity">
                <strong>{user.displayName}</strong>
                <span>{user.id}</span>
              </span>
              <button
                ref={(element) => {
                  if (element === null) {
                    resultButtonRefs.current.delete(user.id)
                  } else {
                    resultButtonRefs.current.set(user.id, element)
                  }
                }}
                className="user-lockdown-button user-lockdown-button--secondary user-lockdown-search-result__action"
                type="button"
                disabled={alreadyRestricted || addMutation.isPending}
                aria-label={
                  alreadyRestricted
                    ? t('user_lockdown', '{userName} is already restricted', {
                        userName: user.displayName,
                      })
                    : t('user_lockdown', 'Restrict {userName}', { userName: user.displayName })
                }
                onClick={() => addUser(user)}
                onKeyDown={(event) => handleResultKeyDown(event, index)}
              >
                {alreadyRestricted
                  ? t('user_lockdown', 'Already restricted')
                  : addingThisUser
                    ? t('user_lockdown', 'Adding…')
                    : t('user_lockdown', 'Add')}
              </button>
            </li>
          )
        })}
      </ul>
    </section>
  )
}
