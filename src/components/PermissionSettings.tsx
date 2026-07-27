import { translate as t } from '@nextcloud/l10n'
import { useEffect, useRef, useState } from 'react'

import type { PermissionSettings as PermissionSettingsData } from '../api/client'
import { useUpdateDefaultPermissionsMutation } from '../queries/permissions'
import {
  canonicalizePermissions,
  matchingPreset,
  permissionsEqual,
  presetDisplayName,
  type PermissionPreset,
  type PermissionSet,
} from '../types/permissions'
import { PermissionEditor } from './PermissionEditor'
import { PresetDeleteDialog } from './PresetDeleteDialog'
import { PresetDialog } from './PresetDialog'

type PermissionSettingsProps = {
  settings: PermissionSettingsData
}

const customPresetValue = '__custom__'

const readableError = (error: unknown): string =>
  error instanceof Error
    ? error.message
    : t('user_lockdown', 'The request could not be completed. Please try again.')

export const PermissionSettings = ({ settings }: PermissionSettingsProps) => {
  const [defaultPermissions, setDefaultPermissions] = useState<PermissionSet>(() =>
    canonicalizePermissions(settings.defaultPermissions),
  )
  const [presetDialog, setPresetDialog] = useState<PermissionPreset | null | undefined>(undefined)
  const [deletePreset, setDeletePreset] = useState<PermissionPreset | null>(null)
  const [saved, setSaved] = useState(false)
  const previousSavedPermissions = useRef(settings.defaultPermissions)
  const updateDefaultMutation = useUpdateDefaultPermissionsMutation()
  const selectedPreset = matchingPreset(defaultPermissions, settings.presets)
  const defaultDirty = !permissionsEqual(defaultPermissions, settings.defaultPermissions)

  useEffect(() => {
    const previousPermissions = previousSavedPermissions.current
    previousSavedPermissions.current = settings.defaultPermissions
    setDefaultPermissions((currentPermissions) =>
      permissionsEqual(currentPermissions, previousPermissions)
        ? canonicalizePermissions(settings.defaultPermissions)
        : currentPermissions,
    )
  }, [settings.defaultPermissions])

  const updateDefaultDraft = (permissions: PermissionSet) => {
    updateDefaultMutation.reset()
    setSaved(false)
    setDefaultPermissions(canonicalizePermissions(permissions))
  }

  const saveDefaultPermissions = () => {
    if (!defaultDirty || updateDefaultMutation.isPending) {
      return
    }

    updateDefaultMutation.mutate(canonicalizePermissions(defaultPermissions), {
      onSuccess: (nextSettings) => {
        setDefaultPermissions(canonicalizePermissions(nextSettings.defaultPermissions))
        setSaved(true)
      },
    })
  }

  return (
    <section className="user-lockdown-card" aria-labelledby="user-lockdown-default-title">
      <div className="user-lockdown-section-heading">
        <div>
          <h2 id="user-lockdown-default-title">{t('user_lockdown', 'Default permissions')}</h2>
          <p>
            {t('user_lockdown', 'These permissions are copied to every user when they are added.')}
          </p>
        </div>
      </div>

      <div className="user-lockdown-settings-toolbar">
        <div>
          <label className="user-lockdown-label" htmlFor="user-lockdown-default-preset">
            {t('user_lockdown', 'Apply preset')}
          </label>
          <select
            id="user-lockdown-default-preset"
            className="user-lockdown-select"
            value={selectedPreset?.id ?? customPresetValue}
            disabled={updateDefaultMutation.isPending}
            onChange={(event) => {
              const preset = settings.presets.find(
                (candidate) => candidate.id === event.currentTarget.value,
              )
              if (preset !== undefined) {
                updateDefaultDraft(preset.permissions)
              }
            }}
          >
            <option value={customPresetValue} disabled>
              {t('user_lockdown', 'Custom permissions')}
            </option>
            {settings.presets.map((preset) => (
              <option key={preset.id} value={preset.id}>
                {presetDisplayName(preset)}
              </option>
            ))}
          </select>
        </div>
        <button
          className="user-lockdown-button user-lockdown-button--secondary"
          type="button"
          onClick={() => setPresetDialog(null)}
        >
          {t('user_lockdown', 'Create preset')}
        </button>
      </div>

      <PermissionEditor
        idPrefix="user-lockdown-default-permission"
        permissions={defaultPermissions}
        disabled={updateDefaultMutation.isPending}
        onChange={updateDefaultDraft}
      />

      {!defaultPermissions.fullAccess &&
        !defaultPermissions.viewFiles &&
        !defaultPermissions.changePassword && (
          <p className="user-lockdown-message" role="status">
            {t('user_lockdown', 'Users with these permissions can only sign out.')}
          </p>
        )}

      {updateDefaultMutation.isError && (
        <p className="user-lockdown-message user-lockdown-message--error" role="alert">
          {t('user_lockdown', 'The default permissions could not be saved.')}{' '}
          {readableError(updateDefaultMutation.error)}
        </p>
      )}
      {saved && !defaultDirty && (
        <p className="user-lockdown-message user-lockdown-message--success" role="status">
          {t('user_lockdown', 'Default permissions saved.')}
        </p>
      )}

      <div className="user-lockdown-settings-actions">
        <button
          className="user-lockdown-button user-lockdown-button--primary"
          type="button"
          disabled={!defaultDirty || updateDefaultMutation.isPending}
          onClick={saveDefaultPermissions}
        >
          {updateDefaultMutation.isPending
            ? t('user_lockdown', 'Saving…')
            : t('user_lockdown', 'Save defaults')}
        </button>
      </div>

      <div className="user-lockdown-preset-list-heading">
        <h3>{t('user_lockdown', 'Available presets')}</h3>
        <p>
          {t(
            'user_lockdown',
            'Built-in presets cannot be changed. Custom presets can be reused for defaults and users.',
          )}
        </p>
      </div>
      <ul className="user-lockdown-preset-list">
        {settings.presets.map((preset) => (
          <li key={preset.id} className="user-lockdown-preset-row">
            <span className="user-lockdown-preset-row__identity">
              <strong>{presetDisplayName(preset)}</strong>
              <span>
                {preset.builtIn
                  ? t('user_lockdown', 'Built-in preset')
                  : t('user_lockdown', 'Custom preset')}
              </span>
            </span>
            {!preset.builtIn && (
              <span className="user-lockdown-preset-row__actions">
                <button
                  className="user-lockdown-button user-lockdown-button--secondary"
                  type="button"
                  onClick={() => setPresetDialog(preset)}
                >
                  {t('user_lockdown', 'Edit')}
                  <span className="user-lockdown-visually-hidden"> {preset.name}</span>
                </button>
                <button
                  className="user-lockdown-button user-lockdown-button--secondary"
                  type="button"
                  onClick={() => setDeletePreset(preset)}
                >
                  {t('user_lockdown', 'Delete')}
                  <span className="user-lockdown-visually-hidden"> {preset.name}</span>
                </button>
              </span>
            )}
          </li>
        ))}
      </ul>

      {presetDialog !== undefined && (
        <PresetDialog
          preset={presetDialog}
          presets={settings.presets}
          defaultPermissions={settings.defaultPermissions}
          onClose={() => setPresetDialog(undefined)}
        />
      )}
      {deletePreset !== null && (
        <PresetDeleteDialog preset={deletePreset} onClose={() => setDeletePreset(null)} />
      )}
    </section>
  )
}
