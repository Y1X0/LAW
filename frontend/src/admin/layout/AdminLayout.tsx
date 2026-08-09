import { Shell, type NavItem } from '@/core/layout/Shell'

/**
 * تنقّل وحدة التحكّم (Super Admin). تُضاف عناصر «الأدوار/النظام…» في المراحل
 * التالية مع بناء شاشاتها، لتجنّب روابط معطّلة قبل وجود مساراتها.
 */
// مرتّبة لقسمين: الشغل اليومي (فوق، بلا ترويسة) ثم «الإدارة والإعداد» (إعداد لمرّة
// واحدة، تحت ترويسة هادئة) — تنظيم مؤسسي صامت. لا مسارات جديدة ولا ميزات محذوفة.
const NAV: NavItem[] = [
  { to: '/admin', label: 'الرئيسية', icon: 'dashboard' },
  { to: '/legal', label: 'الإدارة القانونية', icon: 'cases', end: false },
  { to: '/finance/invoices', label: 'الفواتير', icon: 'salary', end: false },
  { to: '/hr/employees', label: 'الموظفون', icon: 'profile', end: false },
  { to: '/management', label: 'المؤشّرات الإدارية', icon: 'chart' },

  { to: '/payroll', label: 'الرواتب', icon: 'salary', end: false, group: 'الإدارة والإعداد' },
  { to: '/admin/users', label: 'المستخدمون', icon: 'users', group: 'الإدارة والإعداد' },
  { to: '/admin/roles', label: 'الأدوار والصلاحيات', icon: 'shield', group: 'الإدارة والإعداد' },
  { to: '/admin/org', label: 'الهيكل التنظيمي', icon: 'profile', group: 'الإدارة والإعداد' },
  { to: '/admin/onboarding', label: 'تهيئة موظف جديد', icon: 'users', group: 'الإدارة والإعداد' },
  { to: '/admin/audit', label: 'سجلّ التدقيق', icon: 'audit', group: 'الإدارة والإعداد' },
  { to: '/admin/data', label: 'إدارة البيانات (Excel)', icon: 'data', group: 'الإدارة والإعداد' },
  { to: '/admin/custom-fields', label: 'الحقول المخصّصة', icon: 'settings', group: 'الإدارة والإعداد' },
  { to: '/admin/backups', label: 'النسخ الاحتياطي', icon: 'data', group: 'الإدارة والإعداد' },
  { to: '/admin/settings', label: 'الإعدادات', icon: 'settings', group: 'الإدارة والإعداد' },
]

export function AdminLayout() {
  return <Shell nav={NAV} subtitle="وحدة التحكّم" />
}
