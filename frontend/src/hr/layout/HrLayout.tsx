import { Shell, type NavItem } from '@/core/layout/Shell'

/** تنقّل بوابة الموارد البشرية. */
const NAV: NavItem[] = [
  { to: '/hr', label: 'الرئيسية', icon: 'dashboard' },
  { to: '/hr/employees', label: 'الموظفون', icon: 'profile', end: false },
  { to: '/hr/leave', label: 'الإجازات', icon: 'leave' },
  { to: '/hr/attendance', label: 'الحضور', icon: 'attendance' },
  { to: '/payroll', label: 'الرواتب', icon: 'salary', end: false },
]

export function HrLayout() {
  return <Shell nav={NAV} subtitle="بوابة الموارد البشرية" />
}
