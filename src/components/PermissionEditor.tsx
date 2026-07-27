import { translate as t } from '@nextcloud/l10n'

import { canonicalizePermissions, type PermissionSet } from '../types/permissions'

type PermissionEditorProps = {
  idPrefix: string
  permissions: PermissionSet
  onChange: (permissions: PermissionSet) => void
  disabled?: boolean
}

type PermissionOption = {
  key: keyof PermissionSet
  label: string
  description: string
  requiresFiles?: boolean
}

export const PermissionEditor = ({
  idPrefix,
  permissions,
  onChange,
  disabled = false,
}: PermissionEditorProps) => {
  const options: PermissionOption[] = [
    {
      key: 'fullAccess',
      label: t('user_lockdown', 'Normal user (full access)'),
      description: t(
        'user_lockdown',
        'Do not apply any User Lockdown restrictions to this managed user.',
      ),
    },
    {
      key: 'viewFiles',
      label: t('user_lockdown', 'View and download files'),
      description: t('user_lockdown', 'Open folders and download existing files.'),
    },
    {
      key: 'writeFiles',
      label: t('user_lockdown', 'Create and edit files'),
      description: t(
        'user_lockdown',
        'Upload, create, edit, rename, move and copy files and folders.',
      ),
      requiresFiles: true,
    },
    {
      key: 'deleteFiles',
      label: t('user_lockdown', 'Delete files'),
      description: t('user_lockdown', 'Delete files and folders the user can access.'),
      requiresFiles: true,
    },
    {
      key: 'shareFiles',
      label: t('user_lockdown', 'Share files'),
      description: t('user_lockdown', 'Create and manage shares for files and folders.'),
      requiresFiles: true,
    },
    {
      key: 'changePassword',
      label: t('user_lockdown', 'Change own password'),
      description: t('user_lockdown', 'Open account settings and change the login password.'),
    },
  ]

  const updatePermission = (key: keyof PermissionSet, checked: boolean) => {
    const nextPermissions = canonicalizePermissions({
      ...permissions,
      [key]: checked,
    })

    onChange(nextPermissions)
  }

  return (
    <fieldset className="user-lockdown-permission-editor" disabled={disabled}>
      <legend className="user-lockdown-visually-hidden">{t('user_lockdown', 'Permissions')}</legend>
      {options.map((option) => {
        const optionDisabled =
          disabled ||
          (option.key !== 'fullAccess' && permissions.fullAccess) ||
          (option.requiresFiles === true && !permissions.viewFiles)
        const inputId = `${idPrefix}-${option.key}`

        return (
          <label
            key={option.key}
            className={`user-lockdown-permission-option${
              option.key === 'fullAccess' ? ' user-lockdown-permission-option--full' : ''
            }`}
            htmlFor={inputId}
          >
            <input
              id={inputId}
              type="checkbox"
              checked={permissions[option.key]}
              disabled={optionDisabled}
              onChange={(event) => updatePermission(option.key, event.currentTarget.checked)}
            />
            <span>
              <strong>{option.label}</strong>
              <small>{option.description}</small>
            </span>
          </label>
        )
      })}
    </fieldset>
  )
}
