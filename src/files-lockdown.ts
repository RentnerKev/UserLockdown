import { translate as t } from '@nextcloud/l10n'

import './styles/files-lockdown.css'

const blockedControlSelectors = [
  '[data-cy-upload-picker]',
  '[data-cy-upload-picker-input]',
  '[data-cy-files-list-action="new"]',
  '[data-cy-files-list-action="upload"]',
  '[data-cy-files-list-row-action="delete"]',
  '[data-cy-files-list-row-action="rename"]',
  '[data-cy-files-list-row-action="move-copy"]',
  '[data-cy-files-list-row-action="share"]',
  '[data-cy-files-list-row-action="sharing-status"]',
  '[data-cy-files-list-row-action="favorite"]',
  '[data-cy-files-list-row-action="lock"]',
  '[data-cy-files-list-row-action="systemtags:bulk"]',
  '[data-cy-files-list-row-action="set-reminder-menu"]',
  '[data-cy-files-list-row-action="edit-locally"]',
  '[data-action="upload"]',
  '[data-action="new"]',
  '[data-action="delete"]',
  '[data-action="rename"]',
  '[data-action="move"]',
  '[data-action="copy"]',
  '[data-action="share"]',
  '[data-action="favorite"]',
  '#header-menu-user-menu button',
  '#notifications',
  '#contactsmenu',
  '#firstrunwizard',
  '#uploadprogresswrapper',
  '.unified-search-menu',
  '.app-navigation-entry__settings',
  '[data-text-el="readonly-bar"] .save-status button',
  '.files-list__header-upload-button',
  '.files-controls .actions-creatable',
  '.files-controls .new',
  '.sharing-entry__actions',
].join(',')

const hideBlockedControls = (root: ParentNode): void => {
  root.querySelectorAll(blockedControlSelectors).forEach((element) => {
    if (element instanceof HTMLElement) {
      element.hidden = true
      element.setAttribute('data-user-lockdown-hidden', 'true')
    }
  })
}

const showRestrictionBanner = (): void => {
  const currentUrl = new URL(window.location.href)
  const blockedAction = currentUrl.searchParams.get('user_lockdown') === 'blocked'

  if (document.getElementById('user-lockdown-blocked-banner') !== null) {
    return
  }

  const banner = document.createElement('div')
  banner.id = 'user-lockdown-blocked-banner'
  banner.className = 'user-lockdown-blocked-banner'
  banner.setAttribute('role', blockedAction ? 'alert' : 'status')
  banner.textContent = blockedAction
    ? `${t('user_lockdown', 'This action has been disabled by your administrator.')} ${t(
        'user_lockdown',
        'Read-only access: You can view and download existing files. Changes are disabled by your administrator.',
      )}`
    : t(
        'user_lockdown',
        'Read-only access: You can view and download existing files. Changes are disabled by your administrator.',
      )

  const content = document.querySelector('main, #content-vue, #content')
  if (content === null) {
    document.body.prepend(banner)
  } else {
    content.prepend(banner)
  }

  if (blockedAction) {
    banner.setAttribute('tabindex', '-1')
    banner.focus()
    currentUrl.searchParams.delete('user_lockdown')
    window.history.replaceState(window.history.state, '', currentUrl)
  }
}

const initializeFilesLockdown = (): (() => void) => {
  document.documentElement.classList.add('user-lockdown-restricted')
  showRestrictionBanner()
  hideBlockedControls(document)

  let scanScheduled = false
  let scanFrameId: number | null = null
  const observer = new MutationObserver(() => {
    if (scanScheduled) {
      return
    }

    scanScheduled = true
    scanFrameId = window.requestAnimationFrame(() => {
      showRestrictionBanner()
      hideBlockedControls(document)
      scanScheduled = false
      scanFrameId = null
    })
  })

  observer.observe(document.body, { childList: true, subtree: true })

  return () => {
    observer.disconnect()
    if (scanFrameId !== null) {
      window.cancelAnimationFrame(scanFrameId)
    }
  }
}

let cleanupFilesLockdown: (() => void) | undefined
const startFilesLockdown = (): void => {
  cleanupFilesLockdown?.()
  cleanupFilesLockdown = initializeFilesLockdown()
}

export const stopFilesLockdown = (): void => {
  cleanupFilesLockdown?.()
  cleanupFilesLockdown = undefined
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', startFilesLockdown, { once: true })
} else {
  startFilesLockdown()
}
