import { Shell, type NavItem } from '@/core/layout/Shell'

/**
 * تنقّل وحدة التحكّم (Super Admin). تُضاف عناصر «الأدوار/النظام…» في المراحل
 * التالية مع بناء شاشاتها، لتجنّب روابط معطّلة قبل وجود مساراتها.
 */
const NAV: NavItem[] = [
  { to: '/admin', label: 'الرئيسية', icon: 'dashboard' },
  { to: '/admin/users', label: 'المستخدمون', icon: 'users' },
]

export function AdminLayout() {
  return <Shell nav={NAV} subtitle="وحدة التحكّم" />
}
