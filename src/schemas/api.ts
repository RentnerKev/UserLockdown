import { z } from 'zod'

export const restrictedUserSchema = z.object({
  id: z.string().trim().min(1),
  displayName: z.string().trim().min(1),
  avatarUrl: z.string().trim().min(1).nullable().default(null),
})

export const searchUserSchema = restrictedUserSchema.extend({
  restricted: z.boolean().default(false),
})

export const restrictedUsersResponseSchema = z.object({
  data: z.object({
    users: z.array(restrictedUserSchema),
  }),
})

export const searchUsersResponseSchema = z.object({
  data: z.object({
    users: z.array(searchUserSchema),
  }),
})

export const addRestrictedUserResponseSchema = z.object({
  data: z.object({
    user: restrictedUserSchema,
  }),
})

export const removeRestrictedUserResponseSchema = z.object({
  data: z.object({
    userId: z.string().trim().min(1),
  }),
})

export const apiErrorResponseSchema = z.object({
  error: z.object({
    code: z.string().trim().min(1),
    message: z.string().trim().min(1),
  }),
})

export const adminConfigSchema = z.object({
  minimumSearchLength: z.number().int().min(1).max(10).default(2),
  searchDebounceMs: z.number().int().min(0).max(2_000).default(250),
})

export type AdminConfig = z.infer<typeof adminConfigSchema>
