import { useState, type FormEvent } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Button, Field, SelectField, TextareaField } from '@/core/ui/primitives'
import { Modal } from '@/admin/ui/Modal'
import { useToast } from '@/core/ui/useToast'
import { ApiError } from '@/core/api/types'
import { TASK_PRIORITIES, type Task, type TaskInput, createTask, taskPriorityLabel, updateTask } from '@/legal/api/tasks'
import { EmployeePicker } from './EmployeePicker'
import { CasePicker } from './CasePicker'

/**
 * إنشاء/تعديل مهمة (Phase 3 / PR-6). عند الإنشاء: منتقي موظف مطلوب (assigned_to).
 * عند التعديل: بلا منتقي موظف (إعادة الإسناد نقطة مستقلّة عبر AssignTaskModal).
 * الأولويات low/normal/high فقط؛ الحالة تُدار عبر الإكمال لا هنا.
 */
export function TaskFormModal({ existing, onClose }: { existing?: Task; onClose: () => void }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const [title, setTitle] = useState(existing?.title ?? '')
  const [description, setDescription] = useState(existing?.description ?? '')
  const [priority, setPriority] = useState(existing?.priority ?? 'normal')
  const [dueDate, setDueDate] = useState(existing?.due_date ?? '')
  const [assignee, setAssignee] = useState<{ id: number; name: string } | null>(null)
  const [caseSel, setCaseSel] = useState<{ id: number; label: string } | null>(
    existing?.case ? { id: existing.case.id, label: `${existing.case.internal_number} · ${existing.case.title}` } : null,
  )

  const save = useMutation({
    mutationFn: () => {
      const base: TaskInput = {
        title: title.trim(),
        description: description.trim() || null,
        priority,
        due_date: dueDate || null,
        case_id: caseSel?.id ?? null,
      }
      return existing ? updateTask(existing.id, base) : createTask({ ...base, assigned_to: assignee!.id })
    },
    onSuccess: () => {
      show(existing ? 'تم تحديث المهمة' : 'تمت إضافة المهمة')
      void qc.invalidateQueries({ queryKey: ['legal', 'tasks'] })
      onClose()
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر الحفظ', 'error'),
  })

  const canSubmit = title.trim().length > 0 && (existing != null || assignee != null) && !save.isPending
  function onSubmit(e: FormEvent) { e.preventDefault(); if (canSubmit) save.mutate() }

  return (
    <Modal title={existing ? 'تعديل مهمة' : 'إنشاء مهمة'} onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-3">
        <Field label="العنوان *" value={title} onChange={(e) => setTitle(e.target.value)} required />
        <TextareaField label="الوصف" value={description} onChange={(e) => setDescription(e.target.value)} rows={2} />
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <SelectField label="الأولوية" value={priority} onChange={(e) => setPriority(e.target.value)}>
            {TASK_PRIORITIES.map((p) => <option key={p} value={p}>{taskPriorityLabel(p)}</option>)}
          </SelectField>
          <Field label="تاريخ الاستحقاق" type="date" value={dueDate} onChange={(e) => setDueDate(e.target.value)} />
        </div>
        {!existing && (
          <EmployeePicker selected={assignee} onSelect={setAssignee} onClear={() => setAssignee(null)} disabled={save.isPending} />
        )}
        <CasePicker selected={caseSel} onSelect={setCaseSel} onClear={() => setCaseSel(null)} disabled={save.isPending} />
        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" onClick={onClose} disabled={save.isPending}>إلغاء</Button>
          <Button type="submit" disabled={!canSubmit}>{save.isPending ? 'جارٍ…' : existing ? 'حفظ' : 'إضافة'}</Button>
        </div>
      </form>
    </Modal>
  )
}
