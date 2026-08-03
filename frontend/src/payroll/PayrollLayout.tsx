import { Suspense } from 'react'
import { NavLink, Outlet } from 'react-router-dom'
import { FullPageLoader } from '@/core/ui/states'

/**
 * هيكل وحدة الرواتب (Phase 2 / PR-7) — تنقّل فرعي موحّد يظهر فوق كل شاشات الرواتب،
 * ليصبح المستخدم داخل «وحدة» متماسكة لا شاشات متفرّقة. تحميل الصفحات كسول
 * (lazy) عبر Suspense هنا لتقسيم حزمة الرواتب إلى chunks مستقلّة.
 */
const NAV = [
  { to: '/payroll', label: 'الرئيسية', end: true },
  { to: '/payroll/periods', label: 'الفترات', end: false },
  { to: '/payroll/components', label: 'المكوّنات', end: false },
  { to: '/payroll/salary', label: 'رواتب الموظفين', end: false },
  { to: '/payroll/runs', label: 'المسيرات', end: false },
  { to: '/payroll/reports', label: 'التقارير', end: false },
]

export function PayrollLayout() {
  return (
    <div className="space-y-5">
      <nav aria-label="تنقّل الرواتب" className="overflow-x-auto border-b border-slate-200">
        <div className="flex gap-1">
          {NAV.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.end}
              className={({ isActive }) =>
                `shrink-0 border-b-2 px-4 py-2.5 text-sm transition-colors ${
                  isActive
                    ? 'border-gold-400 font-semibold text-brand-700'
                    : 'border-transparent text-slate-500 hover:text-brand-700'
                }`
              }
            >
              {item.label}
            </NavLink>
          ))}
        </div>
      </nav>

      <Suspense fallback={<FullPageLoader />}>
        <Outlet />
      </Suspense>
    </div>
  )
}
