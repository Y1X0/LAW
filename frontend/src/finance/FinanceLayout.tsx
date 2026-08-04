import { Suspense } from 'react'
import { NavLink, Outlet } from 'react-router-dom'
import { FullPageLoader } from '@/core/ui/states'
import { useFinanceCapabilities } from './api/capabilities'

/**
 * هيكل المالية (Phase 6) — تنقّل فرعي فوق شاشات المالية. تبويب التقارير يظهر فقط لمن
 * يملك finance.reports. تحميل الصفحات كسول.
 */
export function FinanceLayout() {
  const { canViewReports } = useFinanceCapabilities()

  const nav = [
    { to: '/finance/invoices', label: 'الفواتير', end: false },
    { to: '/finance/expenses', label: 'المصروفات', end: false },
    ...(canViewReports ? [{ to: '/finance/reports', label: 'التقارير', end: false }] : []),
  ]

  return (
    <div className="space-y-5">
      <nav aria-label="تنقّل المالية" className="overflow-x-auto border-b border-slate-200">
        <div className="flex gap-1">
          {nav.map((item) => (
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
