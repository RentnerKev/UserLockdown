import { z } from 'zod'

export const permissionSetSchema = z.object({
  viewFiles: z.boolean(),
  writeFiles: z.boolean(),
  deleteFiles: z.boolean(),
  shareFiles: z.boolean(),
  changePassword: z.boolean(),
  fullAccess: z.boolean(),
})

export const permissionPresetSchema = z.object({
  id: z.string().trim().min(1),
  name: z.string().trim().min(1).max(64),
  builtIn: z.boolean(),
  permissions: permissionSetSchema,
})

const userSummarySchema = z.object({
  id: z.string().trim().min(1),
  displayName: z.string().trim().min(1),
  avatarUrl: z.string().trim().min(1).nullable().default(null),
})

export const restrictedUserSchema = userSummarySchema.extend({
  permissions: permissionSetSchema,
})

export const searchUserSchema = userSummarySchema.extend({
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

export const updateRestrictedUserResponseSchema = z.object({
  data: z.object({
    user: restrictedUserSchema,
  }),
})

export const permissionSettingsResponseSchema = z.object({
  data: z.object({
    defaultPermissions: permissionSetSchema,
    presets: z.array(permissionPresetSchema),
  }),
})

export const presetResponseSchema = z.object({
  data: z.object({
    preset: permissionPresetSchema,
  }),
})

export const removePresetResponseSchema = z.object({
  data: z.object({
    presetId: z.string().trim().min(1),
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
