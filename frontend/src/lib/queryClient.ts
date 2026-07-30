import { QueryClient } from '@tanstack/react-query'
import { ApiError } from '../api/types'

/** عميل TanStack Query — لا يعيد المحاولة على 401/403 (لا فائدة من تكرارها). */
export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      retry: (failureCount, error) => {
        if (error instanceof ApiError && (error.isUnauthorized || error.isForbidden)) return false
        return failureCount < 2
      },
    },
  },
})
