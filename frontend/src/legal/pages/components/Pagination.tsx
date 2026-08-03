import { Button } from '@/core/ui/primitives'

/**
 * ترقيم مشترك (Phase 4 / PR-4) — استُخرج من صفحات القوائم القانونية (قضايا/عملاء/
 * مهام/إنجاز) لإزالة تكرار متطابق. يظهر فقط عند تعدّد الصفحات، مع تعطيل الحدود.
 */
export function Pagination({ page, totalPages, onChange }: { page: number; totalPages: number; onChange: (page: number) => void }) {
  if (totalPages <= 1) return null
  return (
    <nav aria-label="ترقيم الصفحات" className="flex items-center justify-between gap-3 text-sm text-slate-600">
      <Button variant="ghost" onClick={() => onChange(Math.max(1, page - 1))} disabled={page <= 1}>السابق</Button>
      <span className="tabular-nums">صفحة {page} من {totalPages}</span>
      <Button variant="ghost" onClick={() => onChange(page + 1)} disabled={page >= totalPages}>التالي</Button>
    </nav>
  )
}
