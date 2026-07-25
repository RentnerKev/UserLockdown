import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import {
  addRestrictedUser,
  fetchRestrictedUsers,
  removeRestrictedUser,
  searchUsers,
} from '../api/client'

export const userQueryKeys = {
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
    mutationFn: addRestrictedUser,
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
    mutationFn: removeRestrictedUser,
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: userQueryKeys.restricted }),
        queryClient.invalidateQueries({ queryKey: userQueryKeys.searchRoot }),
      ])
    },
  })
}
