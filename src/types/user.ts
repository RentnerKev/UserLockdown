import type { z } from 'zod'

import type { restrictedUserSchema, searchUserSchema } from '../schemas/api'

export type RestrictedUser = z.infer<typeof restrictedUserSchema>
export type SearchUser = z.infer<typeof searchUserSchema>
