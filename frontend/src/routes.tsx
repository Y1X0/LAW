import { createBrowserRouter, Navigate } from 'react-router-dom'
import { LoginPage } from '@/core/auth/LoginPage'
import { ProtectedRoute } from '@/core/auth/ProtectedRoute'
import { CapabilitiesProvider } from '@/core/capabilities/CapabilitiesProvider'
import { RequireLawyer } from '@/core/capabilities/RequireLawyer'
import { IndexRedirect } from '@/core/layout/IndexRedirect'
import { RoleLayout } from '@/core/layout/RoleLayout'
import { AttendancePage } from '@/employee/pages/AttendancePage'
import { DashboardPage } from '@/employee/pages/DashboardPage'
import { LeavePage } from '@/employee/pages/LeavePage'
import { PayslipDetailPage } from '@/employee/pages/PayslipDetailPage'
import { PayslipsPage } from '@/employee/pages/PayslipsPage'
import { ProfilePage } from '@/employee/pages/ProfilePage'
import { ComingSoon } from '@/lawyer/pages/ComingSoon'
import { LawyerDashboardPage } from '@/lawyer/pages/LawyerDashboardPage'

/*
| التوجيه بطبقات: مصادقة (ProtectedRoute) → كشف القدرة (CapabilitiesProvider) →
| هيكل حسب الدور (RoleLayout) → المسارات. مسارات المحامي محميّة بـ RequireLawyer
| فوق حارس المصادقة (بلا تكرار منطق). خدمات الموظف الذاتية متاحة للدورين
| (تنقّل المحامي الموحّد يربط الإجازات/الراتب/الملف).
*/
export const router = createBrowserRouter(
  [
    { path: '/login', element: <LoginPage /> },
    {
      element: <ProtectedRoute />,
      children: [
        {
          element: <CapabilitiesProvider />,
          children: [
            {
              element: <RoleLayout />,
              children: [
                { index: true, element: <IndexRedirect /> },

                // ---- خدمة ذاتية (HR) — للدورين ----
                { path: 'dashboard', element: <DashboardPage /> },
                { path: 'payslips', element: <PayslipsPage /> },
                { path: 'payslips/:id', element: <PayslipDetailPage /> },
                { path: 'attendance', element: <AttendancePage /> },
                { path: 'leave', element: <LeavePage /> },
                { path: 'profile', element: <ProfilePage /> },

                // ---- المجال القانوني (محامٍ فقط) — الشاشات تُبنى في LP-2..LP-5 ----
                {
                  element: <RequireLawyer />,
                  children: [
                    { path: 'home', element: <LawyerDashboardPage /> },
                    { path: 'cases', element: <ComingSoon title="قضاياي" phase="LP-3" /> },
                    { path: 'tasks', element: <ComingSoon title="المهام" phase="LP-5" /> },
                    { path: 'worklog', element: <ComingSoon title="الإنجازات اليومية" phase="LP-5" /> },
                  ],
                },
              ],
            },
          ],
        },
      ],
    },
    { path: '*', element: <Navigate to="/" replace /> },
  ],
  { future: { v7_relativeSplatPath: true } },
)
