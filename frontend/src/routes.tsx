import { createBrowserRouter, Navigate } from 'react-router-dom'
import { ProtectedRoute } from './auth/ProtectedRoute'
import { AppLayout } from './components/layout/AppLayout'
import { HomePage, PlaceholderPage } from './pages/HomePage'
import { LoginPage } from './pages/LoginPage'

export const router = createBrowserRouter([
  { path: '/login', element: <LoginPage /> },
  {
    element: <ProtectedRoute />,
    children: [
      {
        element: <AppLayout />,
        children: [
          { path: '/', element: <HomePage /> },
          // أماكن الشاشات القادمة (#58–#62) — الأساس يوفّر التوجيه فقط.
          { path: '/dashboard', element: <PlaceholderPage title="لوحتي" /> },
          { path: '/payslips', element: <PlaceholderPage title="كشوف راتبي" /> },
          { path: '/attendance', element: <PlaceholderPage title="حضوري" /> },
          { path: '/leave', element: <PlaceholderPage title="إجازاتي" /> },
          { path: '/profile', element: <PlaceholderPage title="ملفي" /> },
        ],
      },
    ],
  },
  { path: '*', element: <Navigate to="/" replace /> },
])
