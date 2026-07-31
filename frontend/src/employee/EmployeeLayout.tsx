import { Shell, type NavItem } from '@/core/layout/Shell'

/** تنقّل الموظف العادي (كما في Epic 10 — دون تغيير سلوكي). */
const NAV: NavItem[] = [
  { to: '/dashboard', label: 'لوحتي', icon: 'dashboard' },
  { to: '/payslips', label: 'كشوف راتبي', icon: 'salary' },
  { to: '/attendance', label: 'حضوري', icon: 'attendance' },
  { to: '/leave', label: 'إجازاتي', icon: 'leave' },
  { to: '/profile', label: 'ملفي', icon: 'profile' },
]

export function EmployeeLayout() {
  return <Shell nav={NAV} subtitle="بوابة الموظف" />
}
