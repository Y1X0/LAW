import { createBrowserRouter, Navigate } from 'react-router-dom'
import { ProtectedRoute } from './auth/ProtectedRoute'
import { AppLayout } from './components/layout/AppLayout'
import { AttendancePage } from './pages/AttendancePage'
import { DashboardPage } from './pages/DashboardPage'
import { LeavePage } from './pages/LeavePage'
import { LoginPage } from './pages/LoginPage'
import { PayslipDetailPage } from './pages/PayslipDetailPage'
import { PayslipsPage } from './pages/PayslipsPage'
import { ProfilePage } from './pages/ProfilePage'

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
          { path: '/attendance', element: <AttendancePage /> },
          { path: '/leave', element: <LeavePage /> },
          { path: '/profile', element: <ProfilePage /> },
        ],
      },
    ],
  },
  { path: '*', element: <Navigate to="/" replace /> },
], {
  future: { v7_relativeSplatPath: true },
})
