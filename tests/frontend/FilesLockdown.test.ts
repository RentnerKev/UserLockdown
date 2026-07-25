import { describe, expect, it } from 'vitest'

describe('restricted Files interface', () => {
  it('hides mutating controls and announces read-only access', async () => {
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
    `

    const { stopFilesLockdown } = await import('../../src/files-lockdown')

    expect(document.documentElement).toHaveClass('user-lockdown-restricted')
    expect(document.querySelector('[data-cy-upload-picker]')).not.toBeVisible()
    expect(document.querySelector('[data-cy-files-list-row-action="lock"]')).not.toBeVisible()
    expect(
      document.querySelector('[data-text-el="readonly-bar"] .save-status button'),
    ).not.toBeVisible()
    expect(document.getElementById('firstrunwizard')).not.toBeVisible()
    expect(document.getElementById('user-lockdown-blocked-banner')).toHaveTextContent(
      'Read-only access: You can view and download existing files.',
    )

    stopFilesLockdown()
  })
})
