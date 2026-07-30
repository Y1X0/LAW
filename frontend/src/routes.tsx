import { createBrowserRouter, Navigate } from 'react-router-dom'
import { ProtectedRoute } from './auth/ProtectedRoute'
import { AppLayout } from './components/layout/AppLayout'
import { DashboardPage } from './pages/DashboardPage'
import { LoginPage } from './pages/LoginPage'
import { PayslipDetailPage } from './pages/PayslipDetailPage'
import { PayslipsPage } from './pages/PayslipsPage'
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
          { path: '/payslips', element: <PayslipsPage /> },
          { path: '/payslips/:id', element: <PayslipDetailPage /> },
          // أماكن الشاشات القادمة (#60–#62).
          { path: '/attendance', element: <PlaceholderPage title="حضوري" /> },
          { path: '/leave', element: <PlaceholderPage title="إجازاتي" /> },
          { path: '/profile', element: <PlaceholderPage title="ملفي" /> },
        ],
      },
    ],
  },
  { path: '*', element: <Navigate to="/" replace /> },
], {
  future: { v7_relativeSplatPath: true },
})
