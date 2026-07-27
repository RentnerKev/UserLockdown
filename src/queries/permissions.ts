import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import {
  createPreset,
  fetchPermissionSettings,
  removePreset,
  type SavePresetInput,
  updateDefaultPermissions,
  updatePreset,
} from '../api/client'
import type { PermissionSet } from '../types/permissions'

const permissionSettingsQueryKey = ['permission-settings'] as const

export const usePermissionSettingsQuery = () =>
  useQuery({
    queryKey: permissionSettingsQueryKey,
    queryFn: fetchPermissionSettings,
  })

export const useUpdateDefaultPermissionsMutation = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (permissions: PermissionSet) => updateDefaultPermissions(permissions),
    onSuccess: (settings) => {
      queryClient.setQueryData(permissionSettingsQueryKey, settings)
    },
  })
}

export const useCreatePresetMutation = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (input: SavePresetInput) => createPreset(input),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: permissionSettingsQueryKey })
    },
  })
}

export const useUpdatePresetMutation = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ presetId, input }: { presetId: string; input: SavePresetInput }) =>
      updatePreset(presetId, input),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: permissionSettingsQueryKey })
    },
  })
}

export const useRemovePresetMutation = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (presetId: string) => removePreset(presetId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: permissionSettingsQueryKey })
    },
  })
}
