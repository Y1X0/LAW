import { Suspense } from 'react'
import { NavLink, Outlet } from 'react-router-dom'
import { FullPageLoader } from '@/core/ui/states'

/**
 * هيكل المالية (Phase 6) — تنقّل فرعي فوق شاشات المالية. تُضاف التبويبات (المدفوعات/
 * المصروفات/التقارير) مع بناء شاشاتها لتفادي روابط معطّلة. تحميل الصفحات كسول.
 */
const NAV = [
  { to: '/finance/invoices', label: 'الفواتير', end: false },
  { to: '/finance/expenses', label: 'المصروفات', end: false },
]

export function FinanceLayout() {
  return (
    <div className="space-y-5">
      <nav aria-label="تنقّل المالية" className="overflow-x-auto border-b border-slate-200">
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
