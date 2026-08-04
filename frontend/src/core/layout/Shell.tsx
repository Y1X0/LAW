import { NavLink, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '@/core/auth/useAuth'
import { Button } from '@/core/ui/primitives'
import { NotificationsBell } from '@/notifications/NotificationsBell'
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
  `lp-press group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm transition ${
    isActive
      ? 'lp-nav-active bg-white/10 font-semibold text-white'
      : 'text-slate-300/90 hover:bg-white/5 hover:text-white'
  }`

/**
 * الهيكل العام (RTL) المشترك: شريط جانبي كحلي بالهوية + تنقّل جوّال + رأس + منطقة محتوى.
 * تمرّر كل بوابة (موظف/محامٍ) عناصر تنقّلها وعنوانها — دون تكرار الكروم.
 */
export function Shell({ nav, subtitle }: { nav: NavItem[]; subtitle: string }) {
  const { user, logout } = useAuth()
  const location = useLocation()

  return (
    <div className="flex min-h-screen bg-slate-50 text-slate-900">
      {/* شريط جانبي كحلي فاخر (سطح المكتب) */}
      <aside className="lp-sidebar hidden w-64 shrink-0 flex-col p-4 md:flex">
        <div className="mb-7 px-1 pt-1">
          <Brand subtitle={subtitle} onDark />
        </div>
        <nav className="space-y-1.5">
          {nav.map((item) => (
            <NavLink key={item.to} to={item.to} end={item.end ?? true} className={linkClass}>
              {({ isActive }) => (
                <>
                  <Icon
                    name={item.icon}
                    className={`h-5 w-5 transition ${isActive ? 'text-gold-400' : 'text-slate-400 group-hover:text-white'}`}
                  />
                  <span>{item.label}</span>
                </>
              )}
            </NavLink>
          ))}
        </nav>
        <div className="mt-auto border-t border-white/10 pt-3">
          <button
            onClick={() => void logout()}
            className="lp-press flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-300/90 transition hover:bg-white/5 hover:text-white"
          >
            <Icon name="profile" className="h-5 w-5 text-slate-400" />
            <span>تسجيل الخروج</span>
          </button>
        </div>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex items-center justify-between gap-3 border-b border-slate-200/70 bg-white px-4 py-3.5 shadow-header md:px-6">
          <div className="flex items-center gap-2 md:hidden">
            <Brand subtitle={subtitle} />
          </div>
          <div className="flex flex-1 items-center justify-end gap-3">
            <span className="hidden text-sm text-slate-600 md:inline">
              {user ? `مرحباً، ${user.name}` : ''}
            </span>
            <NotificationsBell />
            <Button variant="ghost" className="md:hidden" onClick={() => void logout()}>
              تسجيل الخروج
            </Button>
          </div>
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
          {/* انتقال صفحة خفيف: يُعاد تشغيله بمفتاح المسار (transform/opacity فقط). */}
          <div key={location.pathname} className="lp-page">
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  )
}
