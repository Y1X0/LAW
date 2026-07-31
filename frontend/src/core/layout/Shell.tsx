import { NavLink, Outlet } from 'react-router-dom'
import { useAuth } from '@/core/auth/useAuth'
import { Button } from '@/core/ui/primitives'
import { Brand } from './Brand'
import { Icon, type IconName } from './icons'

export interface NavItem {
  to: string
  label: string
  icon: IconName
  /** يطابق المسارات الفرعية (end=false) — مثل ملف القضية تحت «قضاياي». */
  end?: boolean
}

const linkClass = ({ isActive }: { isActive: boolean }) =>
  `flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition ${
    isActive
      ? 'bg-brand-50 font-semibold text-brand-700'
      : 'text-slate-600 hover:bg-slate-100 hover:text-brand-700'
  }`

/**
 * الهيكل العام (RTL) المشترك: شريط جانبي بالهوية + تنقّل جوّال + رأس + منطقة محتوى.
 * تمرّر كل بوابة (موظف/محامٍ) عناصر تنقّلها وعنوانها — دون تكرار الكروم.
 */
export function Shell({ nav, subtitle }: { nav: NavItem[]; subtitle: string }) {
  const { user, logout } = useAuth()

  return (
    <div className="flex min-h-screen bg-slate-50 text-slate-900">
      {/* شريط جانبي (سطح المكتب) */}
      <aside className="hidden w-64 shrink-0 border-l border-slate-200 bg-white p-4 md:block">
        <div className="mb-6 px-1">
          <Brand subtitle={subtitle} />
        </div>
        <nav className="space-y-1">
          {nav.map((item) => (
            <NavLink key={item.to} to={item.to} end={item.end ?? true} className={linkClass}>
              <Icon name={item.icon} />
              <span>{item.label}</span>
            </NavLink>
          ))}
        </nav>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3 md:px-6">
          <div className="flex items-center gap-2 md:hidden">
            <Brand subtitle={subtitle} />
          </div>
          <span className="hidden text-sm text-slate-600 md:inline">
            {user ? `مرحباً، ${user.name}` : ''}
          </span>
          <Button variant="ghost" onClick={() => void logout()}>
            تسجيل الخروج
          </Button>
        </header>

        {/* تنقّل جوّال أفقي */}
        <nav className="flex gap-1 overflow-x-auto border-b border-slate-200 bg-white px-2 py-2 md:hidden">
          {nav.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.end ?? true}
              className={({ isActive }) =>
                `flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs transition ${
                  isActive
                    ? 'bg-brand-50 font-semibold text-brand-700'
                    : 'text-slate-600 hover:bg-slate-100'
                }`
              }
            >
              <Icon name={item.icon} className="h-4 w-4" />
              <span>{item.label}</span>
            </NavLink>
          ))}
        </nav>

        <main className="flex-1 p-4 md:p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
