import { Shell, type NavItem } from '@/core/layout/Shell'
import { useFinanceCapabilities } from '@/finance/api/capabilities'

/** تنقّل الموظف العادي (كما في Epic 10 — دون تغيير سلوكي). */
const NAV: NavItem[] = [
  { to: '/dashboard', label: 'لوحتي', icon: 'dashboard' },
  { to: '/payslips', label: 'كشوف راتبي', icon: 'salary' },
  { to: '/attendance', label: 'حضوري', icon: 'attendance' },
  { to: '/leave', label: 'إجازاتي', icon: 'leave' },
  { to: '/profile', label: 'ملفي', icon: 'profile' },
]

// رابط الفواتير يظهر فقط لمن يملك القدرة المالية (مثل المحاسب) — الخادم يبقى الحكم النهائي.
const FINANCE_LINK: NavItem = { to: '/finance/invoices', label: 'الفواتير', icon: 'salary', end: false }

export function EmployeeLayout() {
  const { canView } = useFinanceCapabilities()
  const nav: NavItem[] = canView ? [...NAV, FINANCE_LINK] : NAV
  return <Shell nav={nav} subtitle="بوابة الموظف" />
}
