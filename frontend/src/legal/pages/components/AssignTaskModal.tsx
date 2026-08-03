import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Button } from '@/core/ui/primitives'
import { Modal } from '@/admin/ui/Modal'
import { useToast } from '@/core/ui/useToast'
import { ApiError } from '@/core/api/types'
import { assignTask } from '@/legal/api/tasks'
import { EmployeePicker } from './EmployeePicker'

/**
 * إعادة إسناد مهمة (Phase 3 / PR-6) — عبر `PATCH tasks/{id}/assign` الموجود.
 * يمنع الإرسال المكرّر أثناء الحفظ، ويحدّث القائمة بعد النجاح.
 */
export function AssignTaskModal({ taskId, onClose }: { taskId: number; onClose: () => void }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const [selected, setSelected] = useState<{ id: number; name: string } | null>(null)

  const assign = useMutation({
    mutationFn: () => assignTask(taskId, selected!.id),
    onSuccess: () => {
      show('تمت إعادة الإسناد')
      void qc.invalidateQueries({ queryKey: ['legal', 'tasks'] })
      onClose()
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر الإسناد', 'error'),
  })

  return (
    <Modal title="إعادة إسناد المهمة" onClose={onClose}>
      <div className="space-y-3">
        <EmployeePicker selected={selected} onSelect={setSelected} onClear={() => setSelected(null)} disabled={assign.isPending} />
        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" onClick={onClose} disabled={assign.isPending}>إلغاء</Button>
          <Button type="button" onClick={() => assign.mutate()} disabled={!selected || assign.isPending}>
            {assign.isPending ? 'جارٍ…' : 'إسناد'}
          </Button>
        </div>
      </div>
    </Modal>
  )
}
