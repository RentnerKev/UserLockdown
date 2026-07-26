import { waitFor } from '@testing-library/react'
import { describe, expect, it } from 'vitest'

describe('restricted Files interface', () => {
  it('hides mutating controls without restriction messaging', async () => {
    document.body.innerHTML = `
      <main>
        <button data-cy-upload-picker>Upload</button>
        <div data-cy-files-list-row-action="lock">
          <button>Unlock file</button>
        </div>
        <div data-text-el="readonly-bar">
          <div class="save-status"><button>Save</button></div>
        </div>
        <div id="firstrunwizard" role="dialog">First-run wizard</div>
      </main>
      <div id="header-menu-user-menu">
        <div class="header-menu__content">
          <ul class="account-menu__list">
            <li id="profile-entry">
              <a href="/settings/user">Profile</a>
              <button type="button">Show QR code</button>
            </li>
            <li id="settings-entry"><a href="/settings/user/security">Settings</a></li>
            <li id="logout-entry"><a href="/logout?requesttoken=test">Log out</a></li>
          </ul>
        </div>
      </div>
      <div id="readonly-toast" class="toastify">
        Your editing permissions have been revoked. The document is now read-only.
        <button type="button">Close</button>
      </div>
    `

    const { stopFilesLockdown } = await import('../../src/files-lockdown')

    expect(document.documentElement).toHaveClass('user-lockdown-restricted')
    expect(document.querySelector('[data-cy-upload-picker]')).not.toBeVisible()
    expect(document.querySelector('[data-cy-files-list-row-action="lock"]')).not.toBeVisible()
    expect(
      document.querySelector('[data-text-el="readonly-bar"] .save-status button'),
    ).not.toBeVisible()
    expect(document.getElementById('firstrunwizard')).not.toBeVisible()
    expect(document.getElementById('profile-entry')).not.toBeVisible()
    expect(document.getElementById('settings-entry')).not.toBeVisible()
    expect(document.getElementById('logout-entry')).toBeVisible()
    expect(document.querySelector('#logout-entry a')).toBeVisible()
    expect(document.getElementById('readonly-toast')).not.toBeVisible()
    expect(document.getElementById('user-lockdown-blocked-banner')).not.toBeInTheDocument()

    const lateProfileEntry = document.createElement('li')
    lateProfileEntry.innerHTML = '<a href="/settings/user">Late profile entry</a>'
    document.querySelector('.account-menu__list')?.append(lateProfileEntry)

    await waitFor(() => expect(lateProfileEntry).not.toBeVisible())

    stopFilesLockdown()
    expect(document.documentElement).not.toHaveClass('user-lockdown-restricted')
  })
})
