import { NavLink, Outlet } from 'react-router-dom'
import { useAuth } from '../../auth/useAuth'
import { Button } from '../ui/primitives'

const NAV = [
  { to: '/', label: 'الرئيسية', end: true },
  { to: '/dashboard', label: 'لوحتي' },
  { to: '/payslips', label: 'كشوف راتبي' },
  { to: '/attendance', label: 'حضوري' },
  { to: '/leave', label: 'إجازاتي' },
  { to: '/profile', label: 'ملفي' },
]

/** الهيكل العام (RTL): شريط جانبي + شريط علوي + منطقة محتوى. */
export function AppLayout() {
  const { user, logout } = useAuth()

  return (
    <div className="flex min-h-screen bg-slate-50 text-slate-900">
      <aside className="hidden w-60 shrink-0 border-l border-slate-200 bg-white p-4 md:block">
        <div className="mb-6 px-2 text-lg font-bold text-brand-700">بوابة الموظف</div>
        <nav className="space-y-1">
          {NAV.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.end}
              className={({ isActive }) =>
                `block rounded-lg px-3 py-2 text-sm ${
                  isActive ? 'bg-brand-50 font-medium text-brand-700' : 'text-slate-600 hover:bg-slate-100'
                }`
              }
            >
              {item.label}
            </NavLink>
          ))}
        </nav>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex items-center justify-between border-b border-slate-200 bg-white px-6 py-3">
          <span className="text-sm text-slate-600">{user ? `مرحباً، ${user.name}` : ''}</span>
          <Button variant="ghost" onClick={() => void logout()}>
            تسجيل الخروج
          </Button>
        </header>
        <main className="flex-1 p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
