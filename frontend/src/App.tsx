import { QueryClientProvider } from '@tanstack/react-query'
import { RouterProvider } from 'react-router-dom'
import { AuthProvider } from '@/core/auth/AuthContext'
import { ToastProvider } from '@/core/ui/ToastProvider'
import { queryClient } from '@/core/lib/queryClient'
import { router } from './routes'

export function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <ToastProvider>
        <AuthProvider>
          <RouterProvider router={router} future={{ v7_startTransition: true }} />
        </AuthProvider>
      </ToastProvider>
    </QueryClientProvider>
  )
}
