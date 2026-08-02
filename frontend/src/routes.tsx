import { createBrowserRouter, Navigate } from 'react-router-dom'
import { LoginPage } from '@/core/auth/LoginPage'
import { ProtectedRoute } from '@/core/auth/ProtectedRoute'
import { CapabilitiesProvider } from '@/core/capabilities/CapabilitiesProvider'
import { RequireAdmin } from '@/core/capabilities/RequireAdmin'
import { RequireHr } from '@/core/capabilities/RequireHr'
import { RequireLawyer } from '@/core/capabilities/RequireLawyer'
import { RequirePayroll } from '@/core/capabilities/RequirePayroll'
import { IndexRedirect } from '@/core/layout/IndexRedirect'
import { RoleLayout } from '@/core/layout/RoleLayout'
import { AdminHomePage } from '@/admin/pages/AdminHomePage'
import { AdminUsersPage } from '@/admin/pages/AdminUsersPage'
import { AdminRolesPage } from '@/admin/pages/AdminRolesPage'
import { AdminOrgPage } from '@/admin/pages/AdminOrgPage'
import { AdminOnboardingPage } from '@/admin/pages/AdminOnboardingPage'
import { AdminAuditPage } from '@/admin/pages/AdminAuditPage'
import { AdminDataPage } from '@/admin/pages/AdminDataPage'
import { AdminSettingsPage } from '@/admin/pages/AdminSettingsPage'
import { HrDashboardPage } from '@/hr/pages/HrDashboardPage'
import { HrEmployeesPage } from '@/hr/pages/HrEmployeesPage'
import { HrEmployeeProfilePage } from '@/hr/pages/HrEmployeeProfilePage'
import { HrLeavePage } from '@/hr/pages/HrLeavePage'
import { HrAttendancePage } from '@/hr/pages/HrAttendancePage'
import { PayrollDashboardPage } from '@/payroll/pages/PayrollDashboardPage'
import { PayrollPeriodsPage } from '@/payroll/pages/PayrollPeriodsPage'
import { AttendancePage } from '@/employee/pages/AttendancePage'
import { DashboardPage } from '@/employee/pages/DashboardPage'
import { LeavePage } from '@/employee/pages/LeavePage'
import { PayslipDetailPage } from '@/employee/pages/PayslipDetailPage'
import { PayslipsPage } from '@/employee/pages/PayslipsPage'
import { ProfilePage } from '@/employee/pages/ProfilePage'
import { CaseFilePage } from '@/lawyer/pages/CaseFilePage'
import { LawyerDashboardPage } from '@/lawyer/pages/LawyerDashboardPage'
import { MyCasesPage } from '@/lawyer/pages/MyCasesPage'
import { TasksPage } from '@/lawyer/pages/TasksPage'
import { WorklogPage } from '@/lawyer/pages/WorklogPage'

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

                // ---- وحدة التحكّم (Super Admin — يملك roles.manage) — ADMIN-1..2 ----
                {
                  element: <RequireAdmin />,
                  children: [
                    { path: 'admin', element: <AdminHomePage /> },
                    { path: 'admin/users', element: <AdminUsersPage /> },
                    { path: 'admin/roles', element: <AdminRolesPage /> },
                    { path: 'admin/org', element: <AdminOrgPage /> },
                    { path: 'admin/onboarding', element: <AdminOnboardingPage /> },
                    { path: 'admin/audit', element: <AdminAuditPage /> },
                    { path: 'admin/data', element: <AdminDataPage /> },
                    { path: 'admin/settings', element: <AdminSettingsPage /> },
                  ],
                },

                // ---- خدمة ذاتية (HR) — للدورين ----
                { path: 'dashboard', element: <DashboardPage /> },
                { path: 'payslips', element: <PayslipsPage /> },
                { path: 'payslips/:id', element: <PayslipDetailPage /> },
                { path: 'attendance', element: <AttendancePage /> },
                { path: 'leave', element: <LeavePage /> },
                { path: 'profile', element: <ProfilePage /> },

                // ---- إدارة الموارد البشرية (يملك employees.view) — HR-1: الأساس ----
                {
                  element: <RequireHr />,
                  children: [
                    { path: 'hr', element: <HrDashboardPage /> },
                    { path: 'hr/employees', element: <HrEmployeesPage /> },
                    { path: 'hr/employees/:id', element: <HrEmployeeProfilePage /> },
                    { path: 'hr/leave', element: <HrLeavePage /> },
                    { path: 'hr/attendance', element: <HrAttendancePage /> },
                  ],
                },

                // ---- الرواتب (يملك payroll.view — HR/المالك) — Phase 2: PR-1 اللوحة ----
                {
                  element: <RequirePayroll />,
                  children: [
                    { path: 'payroll', element: <PayrollDashboardPage /> },
                    { path: 'payroll/periods', element: <PayrollPeriodsPage /> },
                  ],
                },

                // ---- المجال القانوني (محامٍ فقط) — الشاشات تُبنى في LP-2..LP-5 ----
                {
                  element: <RequireLawyer />,
                  children: [
                    { path: 'home', element: <LawyerDashboardPage /> },
                    { path: 'cases', element: <MyCasesPage /> },
                    { path: 'cases/:id', element: <CaseFilePage /> },
                    { path: 'tasks', element: <TasksPage /> },
                    { path: 'worklog', element: <WorklogPage /> },
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
