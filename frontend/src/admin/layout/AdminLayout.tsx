import { Shell, type NavItem } from '@/core/layout/Shell'

/**
 * تنقّل وحدة التحكّم (Super Admin). تُضاف عناصر «الأدوار/النظام…» في المراحل
 * التالية مع بناء شاشاتها، لتجنّب روابط معطّلة قبل وجود مساراتها.
 */
const NAV: NavItem[] = [
  { to: '/admin', label: 'الرئيسية', icon: 'dashboard' },
  { to: '/hr/employees', label: 'الموظفون', icon: 'profile', end: false },
  { to: '/admin/onboarding', label: 'تهيئة موظف جديد', icon: 'users' },
  { to: '/admin/users', label: 'المستخدمون', icon: 'users' },
  { to: '/admin/roles', label: 'الأدوار والصلاحيات', icon: 'shield' },
  { to: '/admin/org', label: 'الهيكل التنظيمي', icon: 'profile' },
  { to: '/payroll', label: 'الرواتب', icon: 'salary', end: false },
  { to: '/admin/audit', label: 'سجلّ التدقيق', icon: 'audit' },
  { to: '/admin/data', label: 'إدارة البيانات (Excel)', icon: 'data' },
  { to: '/admin/settings', label: 'الإعدادات', icon: 'settings' },
]

export function AdminLayout() {
  return <Shell nav={NAV} subtitle="وحدة التحكّم" />
}
