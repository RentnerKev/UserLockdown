import { loadState } from '@nextcloud/initial-state'
import { waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'

import type { PermissionSet } from '../../src/types/permissions'

const permissions = (overrides: Partial<PermissionSet> = {}): PermissionSet => ({
  viewFiles: true,
  writeFiles: false,
  deleteFiles: false,
  shareFiles: false,
  changePassword: false,
  fullAccess: false,
  ...overrides,
})

const renderFilesInterface = () => {
  document.body.innerHTML = `
    <main>
      <div data-cy-files-list>
        <div data-cy-files-list-row>Report.pdf</div>
      </div>
      <button data-cy-upload-picker>Upload</button>
      <button data-cy-files-list-row-action="rename">Rename</button>
      <button data-cy-files-list-row-action="delete">Delete</button>
      <button data-cy-files-list-row-action="share">Share</button>
      <div data-text-el="readonly-bar">
        <div class="save-status"><button>Save</button></div>
      </div>
      <div id="firstrunwizard" role="dialog">First-run wizard</div>
    </main>
    <div id="header-menu-user-menu">
      <div class="header-menu__content">
        <ul class="account-menu__list">
          <li id="profile-entry"><a href="/settings/user">Profile</a></li>
          <li id="settings-entry"><a href="/settings/user/security">Settings</a></li>
          <li id="logout-entry"><a href="/logout?requesttoken=test">Log out</a></li>
        </ul>
      </div>
    </div>
    <div id="readonly-toast" class="toastify">
      Your editing permissions have been revoked. The document is now read-only.
    </div>
  `
}

let stopLockdown: (() => void) | undefined

const startWithPermissions = async (permissionSet: PermissionSet) => {
  vi.mocked(loadState).mockReturnValue(permissionSet)
  vi.resetModules()
  const lockdown = await import('../../src/files-lockdown')

  if (!document.documentElement.classList.contains('user-lockdown-restricted')) {
    document.dispatchEvent(new Event('DOMContentLoaded'))
  }

  stopLockdown = lockdown.stopFilesLockdown
  return lockdown
}

afterEach(() => {
  stopLockdown?.()
  stopLockdown = undefined
  document.documentElement.className = ''
  document.body.innerHTML = ''
})

describe('restricted Files interface', () => {
  it('loads permissions from initial state and hides only read-only capabilities', async () => {
    renderFilesInterface()
    await startWithPermissions(permissions())

    expect(loadState).toHaveBeenCalledWith('user_lockdown', 'permissions', expect.any(Object))
    expect(document.documentElement).toHaveClass('user-lockdown-restricted')
    expect(document.documentElement).toHaveClass('user-lockdown-deny-write')
    expect(document.querySelector('[data-cy-files-list]')).toBeVisible()
    expect(document.querySelector('[data-cy-upload-picker]')).not.toBeVisible()
    expect(document.querySelector('[data-cy-files-list-row-action="delete"]')).not.toBeVisible()
    expect(document.querySelector('[data-cy-files-list-row-action="share"]')).not.toBeVisible()
    expect(document.getElementById('settings-entry')).not.toBeVisible()
    expect(document.getElementById('logout-entry')).toBeVisible()
    expect(document.getElementById('readonly-toast')).not.toBeVisible()

    const lateUpload = document.createElement('button')
    lateUpload.setAttribute('data-cy-upload-picker', '')
    document.body.append(lateUpload)
    await waitFor(() => expect(lateUpload).not.toBeVisible())
  })

  it('keeps write controls visible while independently blocking delete and share', async () => {
    renderFilesInterface()
    await startWithPermissions(permissions({ writeFiles: true, changePassword: true }))

    expect(document.querySelector('[data-cy-upload-picker]')).toBeVisible()
    expect(document.querySelector('[data-cy-files-list-row-action="rename"]')).toBeVisible()
    expect(document.querySelector('[data-cy-files-list-row-action="delete"]')).not.toBeVisible()
    expect(document.querySelector('[data-cy-files-list-row-action="share"]')).not.toBeVisible()
    expect(document.getElementById('profile-entry')).not.toBeVisible()
    expect(document.getElementById('settings-entry')).toBeVisible()
    expect(document.getElementById('readonly-toast')).toBeVisible()
  })

  it('allows deletion without allowing other file changes', async () => {
    renderFilesInterface()
    await startWithPermissions(permissions({ deleteFiles: true }))

    expect(document.querySelector('[data-cy-files-list-row-action="delete"]')).toBeVisible()
    expect(document.querySelector('[data-cy-upload-picker]')).not.toBeVisible()
    expect(document.querySelector('[data-cy-files-list-row-action="rename"]')).not.toBeVisible()
  })

  it('supports password-only users without exposing files', async () => {
    renderFilesInterface()
    await startWithPermissions(permissions({ viewFiles: false, changePassword: true }))

    expect(document.documentElement).toHaveClass('user-lockdown-deny-read')
    expect(document.querySelector('[data-cy-files-list]')).not.toBeVisible()
    expect(document.getElementById('profile-entry')).not.toBeVisible()
    expect(document.getElementById('settings-entry')).toBeVisible()
    expect(document.getElementById('logout-entry')).toBeVisible()
  })

  it('does not modify the interface for full access', async () => {
    renderFilesInterface()
    await startWithPermissions(
      permissions({
        viewFiles: true,
        writeFiles: true,
        deleteFiles: true,
        shareFiles: true,
        changePassword: true,
        fullAccess: true,
      }),
    )

    expect(document.documentElement).not.toHaveClass('user-lockdown-restricted')
    expect(document.querySelector('[data-cy-upload-picker]')).toBeVisible()
    expect(document.querySelector('[data-cy-files-list-row-action="delete"]')).toBeVisible()
    expect(document.getElementById('settings-entry')).toBeVisible()
    expect(document.getElementById('readonly-toast')).toBeVisible()
  })
})
