import { createBrowserRouter, Navigate } from 'react-router-dom'
import { ProtectedRoute } from './auth/ProtectedRoute'
import { AppLayout } from './components/layout/AppLayout'
import { DashboardPage } from './pages/DashboardPage'
import { LoginPage } from './pages/LoginPage'
import { PlaceholderPage } from './pages/PlaceholderPage'

export const router = createBrowserRouter([
  { path: '/login', element: <LoginPage /> },
  {
    element: <ProtectedRoute />,
    children: [
      {
        element: <AppLayout />,
        children: [
          { path: '/', element: <Navigate to="/dashboard" replace /> },
          { path: '/dashboard', element: <DashboardPage /> },
          // أماكن الشاشات القادمة (#59–#62).
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
