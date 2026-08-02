import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Button, SelectField } from '@/core/ui/primitives'
import { useToast } from '@/core/ui/useToast'
import { Modal } from '@/admin/ui/Modal'
import { ApiError } from '@/core/api/types'
import { fetchBranches } from '@/core/api/org'
import { MONTHS, createPeriod } from '@/payroll/api/payroll'

/** حالية سنة الميلاد (وقت المتصفّح) كقيمة افتراضية للفترة الجديدة. */
const CURRENT_YEAR = new Date().getFullYear()

/**
 * نموذج إنشاء فترة رواتب (Phase 2 / PR-2) — يستخدم POST /payroll-periods الموجود.
 * الفرع اختياري (فترة على مستوى المكتب حين يُترك فارغاً). لا تغيير خلفي.
 */
export function CreatePeriodModal({ onClose }: { onClose: () => void }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const [year, setYear] = useState(String(CURRENT_YEAR))
  const [month, setMonth] = useState(String(new Date().getMonth() + 1))
  const [branchId, setBranchId] = useState('')

  const branches = useQuery({ queryKey: ['org', 'branches'], queryFn: fetchBranches })

  const create = useMutation({
    mutationFn: () =>
      createPeriod({
        year: Number(year),
        month: Number(month),
        branch_id: branchId ? Number(branchId) : null,
      }),
    onSuccess: () => {
      show('تم إنشاء الفترة')
      void qc.invalidateQueries({ queryKey: ['payroll', 'periods'] })
      void qc.invalidateQueries({ queryKey: ['payroll', 'recent-periods'] })
      onClose()
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر إنشاء الفترة', 'error'),
  })

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    create.mutate()
  }

  return (
    <Modal title="إنشاء فترة رواتب" onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-3">
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <SelectField label="السنة *" value={year} onChange={(e) => setYear(e.target.value)}>
            {Array.from({ length: 6 }, (_, i) => CURRENT_YEAR - 3 + i).map((y) => (
              <option key={y} value={y}>{y}</option>
            ))}
          </SelectField>
          <SelectField label="الشهر *" value={month} onChange={(e) => setMonth(e.target.value)}>
            {MONTHS.map((label, i) => (
              <option key={i + 1} value={i + 1}>{label}</option>
            ))}
          </SelectField>
        </div>
        <SelectField label="الفرع (اختياري)" value={branchId} onChange={(e) => setBranchId(e.target.value)}>
          <option value="">كل الفروع</option>
          {branches.data?.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
        </SelectField>
        <p className="text-xs text-slate-400">حين يُترك الفرع فارغاً تُنشأ الفترة على مستوى المكتب كامل.</p>
        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" onClick={onClose} disabled={create.isPending}>إلغاء</Button>
          <Button type="submit" disabled={create.isPending}>{create.isPending ? 'جارٍ…' : 'إنشاء'}</Button>
        </div>
      </form>
    </Modal>
  )
}
