import type { ReactNode } from 'react'
import { Button } from '@/core/ui/primitives'
import { Modal } from '@/admin/ui/Modal'

/**
 * حوار تأكيد بسيط للعمليات الحساسة (احتساب/اعتماد/قفل) — يعيد استخدام Modal المشترك.
 * الرسالة والعنوان يوضّحان جسامة العملية (كخطوة لا رجعة كالقفل).
 */
export function ConfirmDialog({
  title,
  message,
  confirmLabel = 'تأكيد',
  busy = false,
  onConfirm,
  onClose,
}: {
  title: string
  message: ReactNode
  confirmLabel?: string
  busy?: boolean
  onConfirm: () => void
  onClose: () => void
}) {
  return (
    <Modal title={title} onClose={onClose}>
      <div className="space-y-4">
        <div className="text-sm text-slate-600">{message}</div>
        <div className="flex justify-end gap-2">
          <Button type="button" variant="ghost" onClick={onClose} disabled={busy}>إلغاء</Button>
          <Button type="button" onClick={onConfirm} disabled={busy}>{busy ? 'جارٍ…' : confirmLabel}</Button>
        </div>
      </div>
    </Modal>
  )
}
