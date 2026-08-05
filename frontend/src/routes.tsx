import { lazy } from 'react'
import { createBrowserRouter, Navigate } from 'react-router-dom'
import { LoginPage } from '@/core/auth/LoginPage'
import { ProtectedRoute } from '@/core/auth/ProtectedRoute'
import { CapabilitiesProvider } from '@/core/capabilities/CapabilitiesProvider'
import { RequireAdmin } from '@/core/capabilities/RequireAdmin'
import { RequireHr } from '@/core/capabilities/RequireHr'
import { RequireLawyer } from '@/core/capabilities/RequireLawyer'
import { RequirePayroll } from '@/core/capabilities/RequirePayroll'
import { RequireLegal } from '@/core/capabilities/RequireLegal'
import { RequireFinance } from '@/finance/RequireFinance'
import { RequireDashboard } from '@/dashboard/RequireDashboard'
import { IndexRedirect } from '@/core/layout/IndexRedirect'
import { RoleLayout } from '@/core/layout/RoleLayout'
import { AdminHomePage } from '@/admin/pages/AdminHomePage'
import { AdminUsersPage } from '@/admin/pages/AdminUsersPage'
import { AdminRolesPage } from '@/admin/pages/AdminRolesPage'
import { AdminOrgPage } from '@/admin/pages/AdminOrgPage'
import { AdminOnboardingPage } from '@/admin/pages/AdminOnboardingPage'
import { AdminAuditPage } from '@/admin/pages/AdminAuditPage'
import { AdminDataPage } from '@/admin/pages/AdminDataPage'
import { AdminCustomFieldsPage } from '@/admin/pages/AdminCustomFieldsPage'
import { AdminBackupPage } from '@/admin/pages/AdminBackupPage'
import { AdminSettingsPage } from '@/admin/pages/AdminSettingsPage'
import { HrDashboardPage } from '@/hr/pages/HrDashboardPage'
import { HrEmployeesPage } from '@/hr/pages/HrEmployeesPage'
import { HrEmployeeProfilePage } from '@/hr/pages/HrEmployeeProfilePage'
import { HrLeavePage } from '@/hr/pages/HrLeavePage'
import { HrAttendancePage } from '@/hr/pages/HrAttendancePage'
// وحدة الرواتب: تحميل كسول (lazy) لتقسيمها إلى chunks مستقلّة تُحمّل عند الحاجة.
import { PayrollLayout } from '@/payroll/PayrollLayout'
const PayrollDashboardPage = lazy(() => import('@/payroll/pages/PayrollDashboardPage').then((m) => ({ default: m.PayrollDashboardPage })))
const PayrollPeriodsPage = lazy(() => import('@/payroll/pages/PayrollPeriodsPage').then((m) => ({ default: m.PayrollPeriodsPage })))
const PayrollComponentsPage = lazy(() => import('@/payroll/pages/PayrollComponentsPage').then((m) => ({ default: m.PayrollComponentsPage })))
const PayrollSalaryPage = lazy(() => import('@/payroll/pages/PayrollSalaryPage').then((m) => ({ default: m.PayrollSalaryPage })))
const PayrollRunsPage = lazy(() => import('@/payroll/pages/PayrollRunsPage').then((m) => ({ default: m.PayrollRunsPage })))
const PayrollPayslipsPage = lazy(() => import('@/payroll/pages/PayrollPayslipsPage').then((m) => ({ default: m.PayrollPayslipsPage })))
const PayrollReportsPage = lazy(() => import('@/payroll/pages/PayrollReportsPage').then((m) => ({ default: m.PayrollReportsPage })))
// وحدة الإدارة القانونية: تحميل كسول لتقسيمها إلى chunk مستقل.
import { LegalLayout } from '@/legal/LegalLayout'
const LegalCasesPage = lazy(() => import('@/legal/pages/LegalCasesPage').then((m) => ({ default: m.LegalCasesPage })))
// وحدة المالية: تحميل كسول لتقسيمها إلى chunk مستقل.
import { FinanceLayout } from '@/finance/FinanceLayout'
const InvoicesListPage = lazy(() => import('@/finance/pages/InvoicesListPage').then((m) => ({ default: m.InvoicesListPage })))
const InvoiceDetailPage = lazy(() => import('@/finance/pages/InvoiceDetailPage').then((m) => ({ default: m.InvoiceDetailPage })))
const ExpensesListPage = lazy(() => import('@/finance/pages/ExpensesListPage').then((m) => ({ default: m.ExpensesListPage })))
const FinanceReportsPage = lazy(() => import('@/finance/pages/FinanceReportsPage').then((m) => ({ default: m.FinanceReportsPage })))
// لوحة المؤشّرات الإدارية: تحميل كسول لتقسيمها إلى chunk مستقل.
const ManagementDashboardPage = lazy(() => import('@/dashboard/pages/ManagementDashboardPage').then((m) => ({ default: m.ManagementDashboardPage })))
const LegalCaseDetailPage = lazy(() => import('@/legal/pages/LegalCaseDetailPage').then((m) => ({ default: m.LegalCaseDetailPage })))
const LegalClientsPage = lazy(() => import('@/legal/pages/LegalClientsPage').then((m) => ({ default: m.LegalClientsPage })))
const LegalClientDetailPage = lazy(() => import('@/legal/pages/LegalClientDetailPage').then((m) => ({ default: m.LegalClientDetailPage })))
const LegalTasksPage = lazy(() => import('@/legal/pages/LegalTasksPage').then((m) => ({ default: m.LegalTasksPage })))
const LegalWorklogPage = lazy(() => import('@/legal/pages/LegalWorklogPage').then((m) => ({ default: m.LegalWorklogPage })))
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
                    { path: 'admin/custom-fields', element: <AdminCustomFieldsPage /> },
                    { path: 'admin/backups', element: <AdminBackupPage /> },
                    { path: 'admin/settings', element: <AdminSettingsPage /> },
                  ],
                },

                // ---- لوحة المؤشّرات الإدارية (يملك dashboard.view_management) — Phase 7 ----
                {
                  element: <RequireDashboard />,
                  children: [{ path: 'management', element: <ManagementDashboardPage /> }],
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
                    {
                      element: <PayrollLayout />,
                      children: [
                        { path: 'payroll', element: <PayrollDashboardPage /> },
                        { path: 'payroll/periods', element: <PayrollPeriodsPage /> },
                        { path: 'payroll/components', element: <PayrollComponentsPage /> },
                        { path: 'payroll/salary', element: <PayrollSalaryPage /> },
                        { path: 'payroll/runs', element: <PayrollRunsPage /> },
                        { path: 'payroll/runs/:runId/payslips', element: <PayrollPayslipsPage /> },
                        { path: 'payroll/reports', element: <PayrollReportsPage /> },
                      ],
                    },
                  ],
                },

                // ---- الإدارة القانونية (مدير قانوني — يملك clients.view) — Phase 3 ----
                {
                  element: <RequireLegal />,
                  children: [
                    {
                      element: <LegalLayout />,
                      children: [
                        { path: 'legal', element: <LegalCasesPage /> },
                        { path: 'legal/clients', element: <LegalClientsPage /> },
                        { path: 'legal/clients/:id', element: <LegalClientDetailPage /> },
                        { path: 'legal/tasks', element: <LegalTasksPage /> },
                        { path: 'legal/worklog', element: <LegalWorklogPage /> },
                        { path: 'legal/cases/:id', element: <LegalCaseDetailPage /> },
                      ],
                    },
                  ],
                },

                // ---- المالية (يملك invoices.view — المحاسب/المالك) — Phase 6 ----
                {
                  element: <RequireFinance />,
                  children: [
                    {
                      element: <FinanceLayout />,
                      children: [
                        { path: 'finance/invoices', element: <InvoicesListPage /> },
                        { path: 'finance/invoices/:id', element: <InvoiceDetailPage /> },
                        { path: 'finance/expenses', element: <ExpensesListPage /> },
                        { path: 'finance/reports', element: <FinanceReportsPage /> },
                      ],
                    },
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
