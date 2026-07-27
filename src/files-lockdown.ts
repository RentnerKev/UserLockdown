import { loadState } from '@nextcloud/initial-state'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'

import { permissionSetSchema } from './schemas/api'
import {
  canonicalizePermissions,
  readOnlyPermissions,
  type PermissionSet,
} from './types/permissions'
import './styles/files-lockdown.css'

const textReadOnlyNotification =
  'Your editing permissions have been revoked. The document is now read-only.'

const alwaysBlockedSelectors = [
  '#appmenu li[data-id]:not([data-id="files"])',
  '#appmenu a[data-app-id]:not([data-app-id="files"])',
  '#header-start__appmenu .app-menu-entry:not(.app-menu-entry--active)',
  '#header-start__appmenu .app-menu__overflow',
  '#header-start__appmenu .app-menu__waffle',
  '#notifications',
  '#contactsmenu',
  '#firstrunwizard',
  '.unified-search-menu',
  '.app-navigation-entry__settings',
  '#header-menu-user-menu a:not([href*="/logout"]):not([href*="/settings/user/security"])',
  '#header-menu-user-menu button',
]

const readBlockedSelectors = [
  '[data-cy-files-list]',
  '[data-cy-files-list-row]',
  '.files-list__table',
  '.files-list__row',
  '.files-list__empty',
]

const writeBlockedSelectors = [
  '[data-cy-upload-picker]',
  '[data-cy-upload-picker-input]',
  '[data-cy-files-list-action="new"]',
  '[data-cy-files-list-action="upload"]',
  '[data-cy-files-list-row-action="rename"]',
  '[data-cy-files-list-row-action="move-copy"]',
  '[data-cy-files-list-row-action="favorite"]',
  '[data-cy-files-list-row-action="lock"]',
  '[data-cy-files-list-row-action="systemtags:bulk"]',
  '[data-cy-files-list-row-action="set-reminder-menu"]',
  '[data-cy-files-list-row-action="edit-locally"]',
  '[data-action="upload"]',
  '[data-action="new"]',
  '[data-action="rename"]',
  '[data-action="move"]',
  '[data-action="copy"]',
  '[data-action="favorite"]',
  '[data-text-el="readonly-bar"] .save-status button',
  '#uploadprogresswrapper',
  '.files-list__header-upload-button',
  '.files-controls .actions-creatable',
  '.files-controls .new',
]

const deleteBlockedSelectors = [
  '[data-cy-files-list-row-action="delete"]',
  '[data-action="delete"]',
]

const shareBlockedSelectors = [
  '[data-cy-files-list-row-action="share"]',
  '[data-cy-files-list-row-action="sharing-status"]',
  '[data-action="share"]',
  '.sharing-entry__actions',
]

const accountBlockedSelectors = ['#header-menu-user-menu a[href*="/settings/user/security"]']

const sideNavigationSelectors = [
  '#app-navigation-vue',
  '#app-navigation',
  '[data-cy-files-navigation]',
  '[data-cy-files-navigation-toggle]',
  '.files-navigation',
  '.app-navigation',
  '.app-navigation-toggle',
  '.app-navigation-toggle-wrapper',
]

type NavigationLocation = Pick<Location, 'href' | 'origin' | 'replace'>

type FilesRoute = 'all-files' | 'other-files-view' | 'outside-files'

const normalizeText = (value: string): string => value.replace(/\s+/g, ' ').trim()

const normalizePath = (path: string): string => path.replace(/\/+$/, '') || '/'

const getFilesRoute = (navigationLocation: NavigationLocation): FilesRoute => {
  const currentPath = normalizePath(new URL(navigationLocation.href).pathname)
  const filesPath = '/apps/files'
  const filesPathIndex = currentPath.lastIndexOf(filesPath)
  if (filesPathIndex < 0) {
    return 'outside-files'
  }

  const routedPath = currentPath.slice(filesPathIndex)
  if (routedPath !== filesPath && !routedPath.startsWith(`${filesPath}/`)) {
    return 'outside-files'
  }

  const allFilesPath = `${filesPath}/files`

  if (routedPath === allFilesPath || routedPath.startsWith(`${allFilesPath}/`)) {
    return 'all-files'
  }

  return 'other-files-view'
}

const hideElements = (
  root: ParentNode,
  selectors: readonly string[],
  hiddenElements: Map<HTMLElement, boolean>,
): void => {
  if (selectors.length === 0) {
    return
  }

  root.querySelectorAll(selectors.join(',')).forEach((element) => {
    const target = element.closest('#header-menu-user-menu li') ?? element
    if (!(target instanceof HTMLElement)) {
      return
    }

    if (!hiddenElements.has(target)) {
      hiddenElements.set(target, target.hidden)
    }
    target.hidden = true
    target.setAttribute('data-user-lockdown-hidden', 'true')
  })
}

const restoreElements = (hiddenElements: Map<HTMLElement, boolean>): void => {
  hiddenElements.forEach((wasHidden, element) => {
    element.hidden = wasHidden
    element.removeAttribute('data-user-lockdown-hidden')
  })
  hiddenElements.clear()
}

const hideReadOnlyNotifications = (
  root: ParentNode,
  hiddenElements: Map<HTMLElement, boolean>,
): void => {
  const messages = new Set([
    normalizeText(textReadOnlyNotification),
    normalizeText(t('text', textReadOnlyNotification)),
  ])

  root.querySelectorAll('.toastify').forEach((element) => {
    if (
      element instanceof HTMLElement &&
      [...messages].some((message) => normalizeText(element.textContent ?? '').includes(message))
    ) {
      if (!hiddenElements.has(element)) {
        hiddenElements.set(element, element.hidden)
      }
      element.hidden = true
      element.setAttribute('data-user-lockdown-hidden', 'true')
    }
  })
}

const loadPermissions = (): PermissionSet => {
  const parsedPermissions = permissionSetSchema.safeParse(
    loadState<unknown>('user_lockdown', 'permissions', readOnlyPermissions),
  )

  return canonicalizePermissions(
    parsedPermissions.success ? parsedPermissions.data : readOnlyPermissions,
  )
}

export const initializeFilesLockdown = (
  permissions: PermissionSet,
  navigationLocation: NavigationLocation = window.location,
): (() => void) => {
  const canonicalPermissions = canonicalizePermissions(permissions)
  if (canonicalPermissions.fullAccess) {
    return () => undefined
  }

  const rootClasses = ['user-lockdown-restricted']
  const blockedSelectors = [...alwaysBlockedSelectors]

  if (!canonicalPermissions.viewFiles) {
    rootClasses.push('user-lockdown-deny-read')
    blockedSelectors.push(...readBlockedSelectors)
  }
  if (!canonicalPermissions.writeFiles) {
    rootClasses.push('user-lockdown-deny-write')
    blockedSelectors.push(...writeBlockedSelectors)
  }
  if (!canonicalPermissions.deleteFiles) {
    rootClasses.push('user-lockdown-deny-delete')
    blockedSelectors.push(...deleteBlockedSelectors)
  }
  if (!canonicalPermissions.shareFiles) {
    rootClasses.push('user-lockdown-deny-share')
    blockedSelectors.push(...shareBlockedSelectors)
  }
  if (!canonicalPermissions.changePassword) {
    rootClasses.push('user-lockdown-deny-account')
    blockedSelectors.push(...accountBlockedSelectors)
  }

  document.documentElement.classList.add(...rootClasses)
  const hiddenElements = new Map<HTMLElement, boolean>()
  const hiddenSideNavigation = new Map<HTMLElement, boolean>()
  let redirectingToAllFiles = false

  const updateSideNavigation = () => {
    if (!canonicalPermissions.hideSideNavigation) {
      return
    }

    const filesRoute = getFilesRoute(navigationLocation)
    const hideNavigation = filesRoute !== 'outside-files'
    document.documentElement.classList.toggle('user-lockdown-hide-files-navigation', hideNavigation)

    if (hideNavigation) {
      hideElements(document, sideNavigationSelectors, hiddenSideNavigation)
    } else {
      restoreElements(hiddenSideNavigation)
    }

    if (filesRoute === 'other-files-view') {
      if (!redirectingToAllFiles) {
        redirectingToAllFiles = true
        navigationLocation.replace(generateUrl('/apps/files/files'))
      }
      return
    }

    redirectingToAllFiles = false
  }

  const scan = () => {
    updateSideNavigation()
    hideElements(document, blockedSelectors, hiddenElements)
    if (!canonicalPermissions.writeFiles) {
      hideReadOnlyNotifications(document, hiddenElements)
    }
  }
  scan()

  let scanScheduled = false
  let scanFrameId: number | null = null
  const observer = new MutationObserver(() => {
    if (scanScheduled) {
      return
    }

    scanScheduled = true
    scanFrameId = window.requestAnimationFrame(() => {
      scan()
      scanScheduled = false
      scanFrameId = null
    })
  })

  observer.observe(document.body, { childList: true, subtree: true })
  window.addEventListener('popstate', scan)
  window.addEventListener('hashchange', scan)

  return () => {
    observer.disconnect()
    window.removeEventListener('popstate', scan)
    window.removeEventListener('hashchange', scan)
    if (scanFrameId !== null) {
      window.cancelAnimationFrame(scanFrameId)
    }
    document.documentElement.classList.remove(...rootClasses)
    document.documentElement.classList.remove('user-lockdown-hide-files-navigation')
    restoreElements(hiddenElements)
    restoreElements(hiddenSideNavigation)
  }
}

let cleanupFilesLockdown: (() => void) | undefined
const startFilesLockdown = (): void => {
  cleanupFilesLockdown?.()
  cleanupFilesLockdown = initializeFilesLockdown(loadPermissions())
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
