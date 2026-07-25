import { loadState } from '@nextcloud/initial-state'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'

import { AdminApp } from './AdminApp'
import { adminConfigSchema } from './schemas/api'
import './styles/admin.css'

const config = adminConfigSchema.parse(loadState<unknown>('user_lockdown', 'admin-config', {}))

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 15_000,
      retry: 1,
      refetchOnWindowFocus: false,
    },
    mutations: {
      retry: 0,
    },
  },
})

const mountElement = document.getElementById('user-lockdown-admin-root')

if (mountElement === null) {
  throw new Error('User Lockdown admin mount point is missing.')
}

createRoot(mountElement).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <AdminApp config={config} />
    </QueryClientProvider>
  </StrictMode>,
)
