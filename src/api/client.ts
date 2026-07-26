import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { z } from 'zod'

import {
  addRestrictedUserResponseSchema,
  apiErrorResponseSchema,
  removeRestrictedUserResponseSchema,
  restrictedUsersResponseSchema,
  searchUsersResponseSchema,
} from '../schemas/api'
import type { RestrictedUser, SearchUser } from '../types/user'

const requestHeaders = {
  'OCS-APIRequest': 'true',
}

const responseErrorSchema = z.object({
  response: z.object({
    data: z.unknown(),
  }),
})

class ApiClientError extends Error {
  public readonly code: string

  public constructor(code: string, message: string) {
    super(message)
    this.name = 'ApiClientError'
    this.code = code
  }
}

const normalizeApiError = (error: unknown): ApiClientError => {
  if (error instanceof ApiClientError) {
    return error
  }

  if (error instanceof z.ZodError) {
    return new ApiClientError(
      'invalid_response',
      t('user_lockdown', 'The server returned an invalid response.'),
    )
  }

  const parsedResponseError = responseErrorSchema.safeParse(error)
  if (parsedResponseError.success) {
    const parsedError = apiErrorResponseSchema.safeParse(parsedResponseError.data.response.data)

    if (parsedError.success) {
      return new ApiClientError(parsedError.data.error.code, parsedError.data.error.message)
    }
  }

  return new ApiClientError(
    'request_failed',
    t('user_lockdown', 'The request could not be completed. Please try again.'),
  )
}

export const fetchRestrictedUsers = async (): Promise<RestrictedUser[]> => {
  try {
    const response = await axios.get<unknown>(
      generateUrl('/apps/user_lockdown/api/restricted-users'),
      { headers: requestHeaders },
    )

    return restrictedUsersResponseSchema.parse(response.data).data.users
  } catch (error: unknown) {
    throw normalizeApiError(error)
  }
}

export const searchUsers = async (query: string): Promise<SearchUser[]> => {
  try {
    const response = await axios.get<unknown>(generateUrl('/apps/user_lockdown/api/users/search'), {
      headers: requestHeaders,
      params: { query },
    })

    return searchUsersResponseSchema.parse(response.data).data.users
  } catch (error: unknown) {
    throw normalizeApiError(error)
  }
}

export const addRestrictedUser = async (userId: string): Promise<RestrictedUser> => {
  try {
    const response = await axios.post<unknown>(
      generateUrl('/apps/user_lockdown/api/restricted-users'),
      { userId },
      { headers: requestHeaders },
    )

    return addRestrictedUserResponseSchema.parse(response.data).data.user
  } catch (error: unknown) {
    throw normalizeApiError(error)
  }
}

export const removeRestrictedUser = async (userId: string): Promise<string> => {
  try {
    const response = await axios.delete<unknown>(
      generateUrl('/apps/user_lockdown/api/restricted-users/{userId}', { userId }),
      { headers: requestHeaders },
    )

    return removeRestrictedUserResponseSchema.parse(response.data).data.userId
  } catch (error: unknown) {
    throw normalizeApiError(error)
  }
}
