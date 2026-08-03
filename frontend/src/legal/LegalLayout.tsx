import { Suspense } from 'react'
import { NavLink, Outlet } from 'react-router-dom'
import { FullPageLoader } from '@/core/ui/states'

/**
 * هيكل الإدارة القانونية (Phase 3) — تنقّل فرعي موحّد فوق كل شاشات الإدارة القانونية
 * (وحدة متماسكة لا شاشات متفرّقة). تُضاف العناصر مع بناء شاشاتها لتفادي روابط معطّلة.
 * تحميل الصفحات كسول (lazy) عبر Suspense لتقسيم الحزمة.
 */
const NAV = [
  { to: '/legal', label: 'القضايا', end: true },
  { to: '/legal/clients', label: 'العملاء', end: false },
  { to: '/legal/tasks', label: 'المهام', end: false },
  { to: '/legal/worklog', label: 'الإنجاز اليومي', end: false },
]

export function LegalLayout() {
  return (
    <div className="space-y-5">
      <nav aria-label="تنقّل الإدارة القانونية" className="overflow-x-auto border-b border-slate-200">
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
