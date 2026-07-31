/** أدوات عرض (لا منطق أعمال) — تنسيق القيم القادمة من الـ API فقط. */

const ATTENDANCE_STATUS: Record<string, string> = {
  present: 'حاضر',
  absent: 'غائب',
  late: 'متأخّر',
  early_leave: 'مغادرة مبكرة',
  leave: 'إجازة',
  holiday: 'عطلة رسمية',
  weekend: 'نهاية أسبوع',
}

/** تسمية عربية لحالة الحضور. */
export function attendanceStatusLabel(status: string): string {
  return ATTENDANCE_STATUS[status] ?? status
}

const PAYROLL_STATUS: Record<string, string> = {
  approved: 'معتمد',
  paid: 'مدفوع',
  locked: 'مقفل',
  completed: 'مكتمل',
}

/** تسمية عربية لحالة المسير. */
export function payrollStatusLabel(status: string | null): string {
  if (!status) return '—'
  return PAYROLL_STATUS[status] ?? status
}

const LEAVE_STATUS: Record<string, string> = {
  pending: 'قيد المراجعة',
  approved: 'معتمد',
  rejected: 'مرفوض',
  cancelled: 'ملغى',
}

/** تسمية عربية لحالة طلب الإجازة. */
export function leaveStatusLabel(status: string): string {
  return LEAVE_STATUS[status] ?? status
}

/** دقائق → "Xس Yد" (أو "—" عند الصفر). */
export function formatMinutes(minutes: number): string {
  if (!minutes) return '—'
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  if (h && m) return `${h}س ${m}د`
  return h ? `${h}س` : `${m}د`
}

/** "MM/YYYY". */
export function formatPeriod(year: number, month: number): string {
  return `${String(month).padStart(2, '0')}/${year}`
}

/** مبلغ بعملته (تنسيق ثابت غير معتمد على لغة النظام). */
export function formatCurrency(amount: number, currency: string): string {
  const value = amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  return `${value} ${currency}`
}

/** وقت قصير من ISO (HH:MM) أو "—". */
export function formatTime(iso: string | null): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return '—'
  return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}

/**
 * تاريخ "YYYY/MM/DD" أو "—". يتعامل مع تاريخ فقط (YYYY-MM-DD) بلا انزياح منطقة
 * زمنية (يقرأ من النص مباشرة)، ومع ISO كامل يأخذ جزء التاريخ.
 */
export function formatDate(value: string | null): string {
  if (!value) return '—'
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(value)
  if (m) return `${m[1]}/${m[2]}/${m[3]}`
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return '—'
  const y = d.getFullYear()
  return `${y}/${String(d.getMonth() + 1).padStart(2, '0')}/${String(d.getDate()).padStart(2, '0')}`
}

/** تاريخ ووقت من ISO ("YYYY/MM/DD HH:MM" بالتوقيت المحلي) أو "—". */
export function formatDateTime(iso: string | null): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return '—'
  const date = `${d.getFullYear()}/${String(d.getMonth() + 1).padStart(2, '0')}/${String(d.getDate()).padStart(2, '0')}`
  return `${date} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}
