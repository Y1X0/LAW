import { Shell, type NavItem } from '@/core/layout/Shell'

/**
 * تنقّل بوابة الموارد البشرية (HR-1: الأساس فقط).
 * تُضاف عناصر «الموظفون/الإجازات/الحضور» في المراحل التالية مع بناء شاشاتها،
 * لتجنّب روابط معطّلة قبل وجود مساراتها.
 */
const NAV: NavItem[] = [{ to: '/hr', label: 'الرئيسية', icon: 'dashboard' }]

export function HrLayout() {
  return <Shell nav={NAV} subtitle="بوابة الموارد البشرية" />
}
