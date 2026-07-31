import { Shell, type NavItem } from '@/core/layout/Shell'

/**
 * التنقّل الموحّد للمحامي (المعتمد): يدمج المجال القانوني مع خدماته الذاتية.
 * الجلسات/المستندات/الأرشيف ليست عناصر مستقلة — كلها داخل «ملف القضية» (تحت /cases).
 */
const NAV: NavItem[] = [
  { to: '/home', label: 'الرئيسية', icon: 'home' },
  { to: '/cases', label: 'قضاياي', icon: 'cases', end: false },
  { to: '/tasks', label: 'المهام', icon: 'tasks' },
  { to: '/worklog', label: 'الإنجازات اليومية', icon: 'worklog' },
  { to: '/leave', label: 'الإجازات', icon: 'leave' },
  { to: '/payslips', label: 'الراتب', icon: 'salary' },
  { to: '/profile', label: 'الملف الشخصي', icon: 'profile' },
]

export function LawyerLayout() {
  return <Shell nav={NAV} subtitle="بوابة المحامي" />
}
