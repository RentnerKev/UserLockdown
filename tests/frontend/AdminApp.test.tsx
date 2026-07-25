import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { ReactNode } from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { AdminApp } from '../../src/AdminApp'
import {
  addRestrictedUser,
  fetchRestrictedUsers,
  removeRestrictedUser,
  searchUsers,
} from '../../src/api/client'
import type { RestrictedUser, SearchUser } from '../../src/types/user'

vi.mock('../../src/api/client', () => ({
  addRestrictedUser: vi.fn(),
  fetchRestrictedUsers: vi.fn(),
  removeRestrictedUser: vi.fn(),
  searchUsers: vi.fn(),
}))

const restrictedAlice: RestrictedUser = {
  id: 'alice',
  displayName: 'Alice Example',
  avatarUrl: null,
}

const searchableBob: SearchUser = {
  id: 'bob',
  displayName: 'Bob Example',
  avatarUrl: null,
  restricted: false,
}

const createWrapper = () => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false, gcTime: 0 },
      mutations: { retry: false },
    },
  })

  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
  )
}

const renderApp = () =>
  render(<AdminApp config={{ minimumSearchLength: 2, searchDebounceMs: 0 }} />, {
    wrapper: createWrapper(),
  })

describe('User Lockdown admin app', () => {
  beforeEach(() => {
    vi.mocked(fetchRestrictedUsers).mockResolvedValue([])
    vi.mocked(searchUsers).mockResolvedValue([])
    vi.mocked(addRestrictedUser).mockResolvedValue({
      id: searchableBob.id,
      displayName: searchableBob.displayName,
      avatarUrl: null,
    })
    vi.mocked(removeRestrictedUser).mockResolvedValue('alice')
  })

  it('shows a loading state and then the empty state', async () => {
    let resolveUsers: ((users: RestrictedUser[]) => void) | undefined
    vi.mocked(fetchRestrictedUsers).mockImplementation(
      () =>
        new Promise((resolve) => {
          resolveUsers = resolve
        }),
    )

    renderApp()

    expect(screen.getByRole('status', { name: /loading restricted users/i })).toBeInTheDocument()
    resolveUsers?.([])

    expect(await screen.findByRole('heading', { name: /no restricted users/i })).toBeVisible()
  })

  it('searches by user input and adds a result', async () => {
    vi.mocked(searchUsers).mockResolvedValue([searchableBob])
    const user = userEvent.setup()
    renderApp()

    const searchInput = await screen.findByRole('searchbox', { name: /search users/i })
    await user.type(searchInput, 'bob')

    const addButton = await screen.findByRole('button', { name: /restrict bob example/i })
    expect(searchUsers).toHaveBeenCalledWith('bob')
    await user.click(addButton)

    await waitFor(() => expect(addRestrictedUser).toHaveBeenCalled())
    expect(vi.mocked(addRestrictedUser).mock.calls[0]?.[0]).toBe('bob')
  })

  it('marks users that are already restricted', async () => {
    vi.mocked(fetchRestrictedUsers).mockResolvedValue([restrictedAlice])
    vi.mocked(searchUsers).mockResolvedValue([{ ...restrictedAlice, restricted: false }])
    const user = userEvent.setup()
    renderApp()

    await user.type(await screen.findByRole('searchbox', { name: /search users/i }), 'alice')

    expect(
      await screen.findByRole('button', { name: /alice example is already restricted/i }),
    ).toBeDisabled()
  })

  it('requires confirmation before removing a user', async () => {
    vi.mocked(fetchRestrictedUsers).mockResolvedValue([restrictedAlice])
    const user = userEvent.setup()
    renderApp()

    const removeButton = await screen.findByRole('button', { name: /remove alice example/i })
    await user.click(removeButton)

    const dialog = screen.getByRole('alertdialog', { name: /remove restriction/i })
    expect(dialog).toBeVisible()
    await user.click(screen.getByRole('button', { name: /^cancel$/i }))
    expect(removeRestrictedUser).not.toHaveBeenCalled()

    await user.click(removeButton)
    await user.click(screen.getByRole('button', { name: /^remove restriction$/i }))

    await waitFor(() => expect(removeRestrictedUser).toHaveBeenCalled())
    expect(vi.mocked(removeRestrictedUser).mock.calls[0]?.[0]).toBe('alice')
  })

  it('shows a load error and retries from the error state', async () => {
    vi.mocked(fetchRestrictedUsers)
      .mockRejectedValueOnce(new Error('Network unavailable'))
      .mockResolvedValueOnce([])
    const user = userEvent.setup()
    renderApp()

    expect(await screen.findByRole('alert')).toHaveTextContent('Network unavailable')
    await user.click(screen.getByRole('button', { name: /try again/i }))

    expect(await screen.findByRole('heading', { name: /no restricted users/i })).toBeVisible()
    expect(fetchRestrictedUsers).toHaveBeenCalledTimes(2)
  })

  it('supports arrow-key search navigation and Escape in the dialog', async () => {
    vi.mocked(fetchRestrictedUsers).mockResolvedValue([restrictedAlice])
    vi.mocked(searchUsers).mockResolvedValue([searchableBob])
    const user = userEvent.setup()
    renderApp()

    const searchInput = await screen.findByRole('searchbox', { name: /search users/i })
    await user.type(searchInput, 'bob')
    const addButton = await screen.findByRole('button', { name: /restrict bob example/i })

    searchInput.focus()
    await user.keyboard('{ArrowDown}')
    expect(addButton).toHaveFocus()

    const removeButton = screen.getByRole('button', { name: /remove alice example/i })
    removeButton.focus()
    await user.keyboard('{Enter}')
    expect(screen.getByRole('alertdialog')).toBeVisible()
    await user.keyboard('{Escape}')
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
  })
})
