import { translate as t } from '@nextcloud/l10n'
import type { z } from 'zod'

import type { permissionPresetSchema, permissionSetSchema } from '../schemas/api'

export type PermissionSet = z.infer<typeof permissionSetSchema>
export type PermissionPreset = z.infer<typeof permissionPresetSchema>

export const permissionKeys = [
  'viewFiles',
  'writeFiles',
  'deleteFiles',
  'shareFiles',
  'changePassword',
  'fullAccess',
] as const satisfies readonly (keyof PermissionSet)[]

export const readOnlyPermissions: PermissionSet = {
  viewFiles: true,
  writeFiles: false,
  deleteFiles: false,
  shareFiles: false,
  changePassword: false,
  fullAccess: false,
}

export const canonicalizePermissions = (permissions: PermissionSet): PermissionSet => {
  if (permissions.fullAccess) {
    return {
      viewFiles: true,
      writeFiles: true,
      deleteFiles: true,
      shareFiles: true,
      changePassword: true,
      fullAccess: true,
    }
  }

  if (!permissions.viewFiles) {
    return {
      ...permissions,
      writeFiles: false,
      deleteFiles: false,
      shareFiles: false,
      fullAccess: false,
    }
  }

  return { ...permissions, fullAccess: false }
}

export const permissionsEqual = (left: PermissionSet, right: PermissionSet): boolean => {
  const canonicalLeft = canonicalizePermissions(left)
  const canonicalRight = canonicalizePermissions(right)

  return permissionKeys.every((key) => canonicalLeft[key] === canonicalRight[key])
}

export const matchingPreset = (
  permissions: PermissionSet,
  presets: readonly PermissionPreset[],
): PermissionPreset | null =>
  presets.find((preset) => permissionsEqual(preset.permissions, permissions)) ?? null

export const presetDisplayName = (preset: PermissionPreset): string => {
  if (!preset.builtIn) {
    return preset.name
  }

  const builtInId = preset.id.startsWith('builtin:')
    ? preset.id.slice('builtin:'.length)
    : preset.id

  switch (builtInId) {
    case 'blocked':
      return t('user_lockdown', 'Blocked')
    case 'read-only':
      return t('user_lockdown', 'Read only')
    case 'file-editor':
      return t('user_lockdown', 'File editor')
    case 'deletion-only':
      return t('user_lockdown', 'Deletion only')
    case 'password-only':
      return t('user_lockdown', 'Password only')
    case 'normal-user':
      return t('user_lockdown', 'Normal user')
    default:
      return preset.name
  }
}
