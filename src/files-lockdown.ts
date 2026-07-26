import { translate as t } from '@nextcloud/l10n'

import './styles/files-lockdown.css'

const textReadOnlyNotification =
  'Your editing permissions have been revoked. The document is now read-only.'

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
  '#header-menu-user-menu a:not([href*="/logout"])',
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
    const target = element.closest('#header-menu-user-menu li') ?? element
    if (target instanceof HTMLElement) {
      target.hidden = true
      target.setAttribute('data-user-lockdown-hidden', 'true')
    }
  })
}

const normalizeText = (value: string): string => value.replace(/\s+/g, ' ').trim()

const hideReadOnlyNotifications = (root: ParentNode): void => {
  const messages = new Set([
    normalizeText(textReadOnlyNotification),
    normalizeText(t('text', textReadOnlyNotification)),
  ])

  root.querySelectorAll('.toastify').forEach((element) => {
    if (
      element instanceof HTMLElement &&
      [...messages].some((message) => normalizeText(element.textContent ?? '').includes(message))
    ) {
      element.hidden = true
      element.setAttribute('data-user-lockdown-hidden', 'true')
    }
  })
}

const initializeFilesLockdown = (): (() => void) => {
  document.documentElement.classList.add('user-lockdown-restricted')
  hideBlockedControls(document)
  hideReadOnlyNotifications(document)

  let scanScheduled = false
  let scanFrameId: number | null = null
  const observer = new MutationObserver(() => {
    if (scanScheduled) {
      return
    }

    scanScheduled = true
    scanFrameId = window.requestAnimationFrame(() => {
      hideBlockedControls(document)
      hideReadOnlyNotifications(document)
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
    document.documentElement.classList.remove('user-lockdown-restricted')
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
