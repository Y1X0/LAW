import { Shell, type NavItem } from '@/core/layout/Shell'

/**
 * تنقّل بوابة الموارد البشرية.
 * تُضاف عناصر «الإجازات/الحضور» في المراحل التالية مع بناء شاشاتها،
 * لتجنّب روابط معطّلة قبل وجود مساراتها.
 */
const NAV: NavItem[] = [
  { to: '/hr', label: 'الرئيسية', icon: 'dashboard' },
  { to: '/hr/employees', label: 'الموظفون', icon: 'profile', end: false },
]

export function HrLayout() {
  return <Shell nav={NAV} subtitle="بوابة الموارد البشرية" />
}
