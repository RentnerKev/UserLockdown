import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { ReactNode } from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'

import { AdminApp } from '../../src/AdminApp'
import {
  addRestrictedUser,
  createPreset,
  fetchPermissionSettings,
  fetchRestrictedUsers,
  removePreset,
  removeRestrictedUser,
  searchUsers,
  updateDefaultPermissions,
  updatePreset,
  updateRestrictedUser,
} from '../../src/api/client'
import type { PermissionPreset, PermissionSet } from '../../src/types/permissions'
import type { RestrictedUser, SearchUser } from '../../src/types/user'

vi.mock('../../src/api/client', () => ({
  addRestrictedUser: vi.fn(),
  createPreset: vi.fn(),
  fetchPermissionSettings: vi.fn(),
  fetchRestrictedUsers: vi.fn(),
  removePreset: vi.fn(),
  removeRestrictedUser: vi.fn(),
  searchUsers: vi.fn(),
  updateDefaultPermissions: vi.fn(),
  updatePreset: vi.fn(),
  updateRestrictedUser: vi.fn(),
}))

const blockedPermissions: PermissionSet = {
  viewFiles: false,
  writeFiles: false,
  deleteFiles: false,
  shareFiles: false,
  changePassword: false,
  hideSideNavigation: false,
  fullAccess: false,
}

const readOnlyPermissions: PermissionSet = {
  ...blockedPermissions,
  viewFiles: true,
}

const fileEditorPermissions: PermissionSet = {
  ...readOnlyPermissions,
  writeFiles: true,
}

const fullAccessPermissions: PermissionSet = {
  viewFiles: true,
  writeFiles: true,
  deleteFiles: true,
  shareFiles: true,
  changePassword: true,
  hideSideNavigation: false,
  fullAccess: true,
}

const builtInPresets: PermissionPreset[] = [
  {
    id: 'builtin:blocked',
    name: 'Blocked',
    builtIn: true,
    permissions: blockedPermissions,
  },
  {
    id: 'builtin:read-only',
    name: 'Read only',
    builtIn: true,
    permissions: readOnlyPermissions,
  },
  {
    id: 'builtin:file-editor',
    name: 'File editor',
    builtIn: true,
    permissions: fileEditorPermissions,
  },
  {
    id: 'builtin:normal-user',
    name: 'Normal user',
    builtIn: true,
    permissions: fullAccessPermissions,
  },
]

const customPreset: PermissionPreset = {
  id: 'custom:team',
  name: 'Team preset',
  builtIn: false,
  permissions: { ...readOnlyPermissions, changePassword: true },
}

const permissionSettings = {
  defaultPermissions: readOnlyPermissions,
  presets: [...builtInPresets, customPreset],
}

const restrictedAlice: RestrictedUser = {
  id: 'alice',
  displayName: 'Alice Example',
  avatarUrl: null,
  permissions: readOnlyPermissions,
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
    vi.mocked(fetchPermissionSettings).mockResolvedValue(permissionSettings)
    vi.mocked(fetchRestrictedUsers).mockResolvedValue([])
    vi.mocked(searchUsers).mockResolvedValue([])
    vi.mocked(addRestrictedUser).mockResolvedValue({
      id: searchableBob.id,
      displayName: searchableBob.displayName,
      avatarUrl: null,
      permissions: readOnlyPermissions,
    })
    vi.mocked(removeRestrictedUser).mockResolvedValue('alice')
    vi.mocked(updateRestrictedUser).mockImplementation(async (userId, permissions) => ({
      ...restrictedAlice,
      id: userId,
      permissions,
    }))
    vi.mocked(updateDefaultPermissions).mockImplementation(async (permissions) => ({
      ...permissionSettings,
      defaultPermissions: permissions,
    }))
    vi.mocked(createPreset).mockImplementation(async (input) => ({
      id: 'custom:new',
      builtIn: false,
      ...input,
    }))
    vi.mocked(updatePreset).mockImplementation(async (presetId, input) => ({
      id: presetId,
      builtIn: false,
      ...input,
    }))
    vi.mocked(removePreset).mockResolvedValue(customPreset.id)
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

    expect(screen.getByRole('status', { name: /loading access settings/i })).toBeInTheDocument()
    resolveUsers?.([])

    expect(await screen.findByRole('heading', { name: /no restricted users/i })).toBeVisible()
  })

  it('searches by user input and adds a result with the saved defaults', async () => {
    vi.mocked(searchUsers).mockResolvedValue([searchableBob])
    const user = userEvent.setup()
    renderApp()

    const searchInput = await screen.findByRole('searchbox', { name: /search users/i })
    expect(screen.getByText(/new users receive the “read only” permissions/i)).toBeVisible()
    await user.type(searchInput, 'bob')

    const addButton = await screen.findByRole('button', { name: /restrict bob example/i })
    expect(searchUsers).toHaveBeenCalledWith('bob')
    await user.click(addButton)

    await waitFor(() => expect(addRestrictedUser).toHaveBeenCalledWith('bob'))
  })

  it('marks users that are already restricted', async () => {
    vi.mocked(fetchRestrictedUsers).mockResolvedValue([restrictedAlice])
    vi.mocked(searchUsers).mockResolvedValue([
      { ...searchableBob, id: restrictedAlice.id, displayName: restrictedAlice.displayName },
    ])
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

    expect(screen.getByRole('alertdialog', { name: /remove restriction/i })).toBeVisible()
    await user.click(screen.getByRole('button', { name: /^cancel$/i }))
    expect(removeRestrictedUser).not.toHaveBeenCalled()

    await user.click(removeButton)
    await user.click(screen.getByRole('button', { name: /^remove restriction$/i }))

    await waitFor(() => expect(removeRestrictedUser).toHaveBeenCalledWith('alice'))
  })

  it('shows a load error and retries both resources', async () => {
    vi.mocked(fetchRestrictedUsers)
      .mockRejectedValueOnce(new Error('Network unavailable'))
      .mockResolvedValueOnce([])
    const user = userEvent.setup()
    renderApp()

    expect(await screen.findByRole('alert')).toHaveTextContent('Network unavailable')
    await user.click(screen.getByRole('button', { name: /try again/i }))

    expect(await screen.findByRole('heading', { name: /no restricted users/i })).toBeVisible()
    expect(fetchRestrictedUsers).toHaveBeenCalledTimes(2)
    expect(fetchPermissionSettings).toHaveBeenCalledTimes(2)
  })

  it('supports arrow-key search navigation and Escape in the remove dialog', async () => {
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

  it('applies a preset and explicitly saves the default permissions', async () => {
    const user = userEvent.setup()
    renderApp()

    const presetSelect = await screen.findByRole('combobox', { name: /apply preset/i })
    await user.selectOptions(presetSelect, 'builtin:file-editor')

    expect(screen.getByRole('checkbox', { name: /create and edit files/i })).toBeChecked()
    await user.click(screen.getByRole('button', { name: /save defaults/i }))

    await waitFor(() =>
      expect(updateDefaultPermissions).toHaveBeenCalledWith(fileEditorPermissions),
    )
    expect(await screen.findByText(/default permissions saved/i)).toBeVisible()
  })

  it('saves hidden Files navigation as part of the default permissions', async () => {
    const user = userEvent.setup()
    renderApp()

    await user.click(
      await screen.findByRole('checkbox', {
        name: /hide Files navigation/i,
      }),
    )
    await user.click(screen.getByRole('button', { name: /save defaults/i }))

    await waitFor(() =>
      expect(updateDefaultPermissions).toHaveBeenCalledWith({
        ...readOnlyPermissions,
        hideSideNavigation: true,
      }),
    )
  })

  it('canonicalizes dependent and full-access permissions in the editor', async () => {
    const user = userEvent.setup()
    renderApp()

    const viewFiles = await screen.findByRole('checkbox', { name: /view and download files/i })
    const writeFiles = screen.getByRole('checkbox', { name: /create and edit files/i })
    const hideSideNavigation = screen.getByRole('checkbox', { name: /hide Files navigation/i })
    await user.click(hideSideNavigation)
    expect(hideSideNavigation).toBeChecked()
    await user.click(viewFiles)
    expect(writeFiles).toBeDisabled()
    expect(hideSideNavigation).not.toBeChecked()
    expect(hideSideNavigation).toBeDisabled()

    await user.click(screen.getByRole('checkbox', { name: /normal user.*full access/i }))
    expect(viewFiles).toBeChecked()
    expect(viewFiles).toBeDisabled()
    expect(writeFiles).toBeChecked()

    await user.click(screen.getByRole('button', { name: /save defaults/i }))
    await waitFor(() =>
      expect(updateDefaultPermissions).toHaveBeenCalledWith(fullAccessPermissions),
    )
  })

  it('edits a user from a preset and saves a permission snapshot', async () => {
    vi.mocked(fetchRestrictedUsers).mockResolvedValue([restrictedAlice])
    const user = userEvent.setup()
    renderApp()

    await user.click(await screen.findByRole('button', { name: /edit alice example/i }))
    const dialog = screen.getByRole('dialog', { name: /edit permissions/i })
    await user.selectOptions(
      within(dialog).getByRole('combobox', { name: /apply preset/i }),
      'builtin:file-editor',
    )
    await user.click(within(dialog).getByRole('checkbox', { name: /hide Files navigation/i }))
    await user.click(within(dialog).getByRole('button', { name: /save permissions/i }))

    await waitFor(() =>
      expect(updateRestrictedUser).toHaveBeenCalledWith('alice', {
        ...fileEditorPermissions,
        hideSideNavigation: true,
      }),
    )
    expect(screen.queryByRole('dialog', { name: /edit permissions/i })).not.toBeInTheDocument()
  })

  it('keeps user edits local until save and restores focus after cancel', async () => {
    vi.mocked(fetchRestrictedUsers).mockResolvedValue([restrictedAlice])
    const user = userEvent.setup()
    renderApp()

    const editButton = await screen.findByRole('button', { name: /edit alice example/i })
    await user.click(editButton)
    const dialog = screen.getByRole('dialog', { name: /edit permissions/i })
    await user.click(within(dialog).getByRole('checkbox', { name: /delete files/i }))
    expect(within(dialog).getByRole('combobox', { name: /apply preset/i })).toHaveValue(
      '__custom__',
    )
    await user.click(within(dialog).getByRole('button', { name: /^cancel$/i }))

    expect(updateRestrictedUser).not.toHaveBeenCalled()
    expect(editButton).toHaveFocus()
  })

  it('shows update errors inside the user permission dialog', async () => {
    vi.mocked(fetchRestrictedUsers).mockResolvedValue([restrictedAlice])
    vi.mocked(updateRestrictedUser).mockRejectedValue(new Error('Save failed'))
    const user = userEvent.setup()
    renderApp()

    await user.click(await screen.findByRole('button', { name: /edit alice example/i }))
    const dialog = screen.getByRole('dialog', { name: /edit permissions/i })
    await user.click(within(dialog).getByRole('checkbox', { name: /change own password/i }))
    await user.click(within(dialog).getByRole('button', { name: /save permissions/i }))

    expect(await within(dialog).findByRole('alert')).toHaveTextContent('Save failed')
  })

  it('creates a custom preset and rejects a duplicate visible built-in name', async () => {
    const user = userEvent.setup()
    renderApp()

    await user.click(await screen.findByRole('button', { name: /^create preset$/i }))
    const dialog = screen.getByRole('dialog', { name: /create preset/i })
    const nameInput = within(dialog).getByRole('textbox', { name: /preset name/i })
    await user.type(nameInput, 'Read only')
    expect(within(dialog).getByRole('button', { name: /save preset/i })).toBeDisabled()
    expect(within(dialog).getByRole('alert')).toHaveTextContent(/already exists/i)

    await user.clear(nameInput)
    await user.type(nameInput, 'Guest access')
    await user.click(within(dialog).getByRole('checkbox', { name: /change own password/i }))
    await user.click(within(dialog).getByRole('checkbox', { name: /hide Files navigation/i }))
    await user.click(within(dialog).getByRole('button', { name: /save preset/i }))

    await waitFor(() =>
      expect(createPreset).toHaveBeenCalledWith({
        name: 'Guest access',
        permissions: {
          ...readOnlyPermissions,
          changePassword: true,
          hideSideNavigation: true,
        },
      }),
    )
    expect(screen.queryByRole('dialog', { name: /create preset/i })).not.toBeInTheDocument()
  })

  it('edits and deletes custom presets while built-ins remain immutable', async () => {
    const user = userEvent.setup()
    renderApp()

    expect(await screen.findByRole('button', { name: /edit team preset/i })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /edit read only/i })).not.toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: /edit team preset/i }))
    const editDialog = screen.getByRole('dialog', { name: /edit preset/i })
    const nameInput = within(editDialog).getByRole('textbox', { name: /preset name/i })
    await user.clear(nameInput)
    await user.type(nameInput, 'Updated team')
    await user.click(within(editDialog).getByRole('button', { name: /save preset/i }))

    await waitFor(() =>
      expect(updatePreset).toHaveBeenCalledWith(customPreset.id, {
        name: 'Updated team',
        permissions: customPreset.permissions,
      }),
    )

    await user.click(screen.getByRole('button', { name: /delete team preset/i }))
    const deleteDialog = screen.getByRole('alertdialog', { name: /delete preset/i })
    await user.click(within(deleteDialog).getByRole('button', { name: /^delete preset$/i }))
    await waitFor(() => expect(removePreset).toHaveBeenCalledWith(customPreset.id))
  })
})
