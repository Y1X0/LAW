import { useState, type FormEvent } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Button, Field } from '@/core/ui/primitives'
import { useToast } from '@/core/ui/useToast'
import { Modal } from '@/admin/ui/Modal'
import { copyRole, createRole, type Role } from '@/admin/api/roles'
import { ApiError } from '@/core/api/types'

/**
 * إنشاء دور جديد، أو نسخ دور قائم مع صلاحياته (عند تمرير `copyFrom`).
 * النسخ عملية على العميل تعيد استخدام نقاط إنشاء الدور ومزامنة الصلاحيات.
 */
export function RoleFormModal({ copyFrom, onClose }: { copyFrom?: Role; onClose: () => void }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const [name, setName] = useState('')
  const [displayName, setDisplayName] = useState(copyFrom ? `${copyFrom.display_name || copyFrom.name} (نسخة)` : '')

  const submit = useMutation({
    mutationFn: () => {
      const input = { name: name.trim(), display_name: displayName.trim() }
      return copyFrom ? copyRole(copyFrom, input) : createRole(input)
    },
    onSuccess: () => {
      show(copyFrom ? 'تم نسخ الدور' : 'تم إنشاء الدور')
      void qc.invalidateQueries({ queryKey: ['admin', 'roles'] })
      onClose()
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّرت العملية', 'error'),
  })

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    submit.mutate()
  }

  return (
    <Modal title={copyFrom ? `نسخ الدور — ${copyFrom.display_name || copyFrom.name}` : 'إنشاء دور جديد'} onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-3">
        <Field
          label="المعرّف (بالإنجليزية، فريد)"
          value={name}
          onChange={(e) => setName(e.target.value)}
          placeholder="مثال: auditor"
          required
        />
        <Field
          label="الاسم الظاهر"
          value={displayName}
          onChange={(e) => setDisplayName(e.target.value)}
          required
        />
        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" onClick={onClose} disabled={submit.isPending}>
            إلغاء
          </Button>
          <Button type="submit" disabled={submit.isPending}>
            {submit.isPending ? 'جارٍ…' : copyFrom ? 'نسخ' : 'إنشاء'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
