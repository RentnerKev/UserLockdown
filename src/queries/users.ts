import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import {
  addRestrictedUser,
  fetchRestrictedUsers,
  removeRestrictedUser,
  searchUsers,
  updateRestrictedUser,
} from '../api/client'
import type { PermissionSet } from '../types/permissions'

const userQueryKeys = {
  restricted: ['restricted-users'] as const,
  searchRoot: ['user-search'] as const,
  search: (query: string) => ['user-search', query] as const,
}

export const useRestrictedUsersQuery = () =>
  useQuery({
    queryKey: userQueryKeys.restricted,
    queryFn: fetchRestrictedUsers,
  })

export const useUserSearchQuery = (query: string, enabled: boolean) =>
  useQuery({
    queryKey: userQueryKeys.search(query),
    queryFn: () => searchUsers(query),
    enabled,
  })

export const useAddRestrictedUserMutation = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (userId: string) => addRestrictedUser(userId),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: userQueryKeys.restricted }),
        queryClient.invalidateQueries({ queryKey: userQueryKeys.searchRoot }),
      ])
    },
  })
}

export const useRemoveRestrictedUserMutation = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (userId: string) => removeRestrictedUser(userId),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: userQueryKeys.restricted }),
        queryClient.invalidateQueries({ queryKey: userQueryKeys.searchRoot }),
      ])
    },
  })
}

export const useUpdateRestrictedUserMutation = () => {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ userId, permissions }: { userId: string; permissions: PermissionSet }) =>
      updateRestrictedUser(userId, permissions),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: userQueryKeys.restricted })
    },
  })
}
