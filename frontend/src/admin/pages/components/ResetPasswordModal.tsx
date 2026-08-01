import { useState, type FormEvent } from 'react'
import { useMutation } from '@tanstack/react-query'
import { Button, Field } from '@/core/ui/primitives'
import { useToast } from '@/core/ui/useToast'
import { Modal } from '@/admin/ui/Modal'
import { resetUserPassword, type AdminUser } from '@/admin/api/users'
import { ApiError } from '@/core/api/types'

/** إعادة تعيين كلمة مرور مستخدم إدارياً (تُبطِل جلساته). */
export function ResetPasswordModal({ user, onClose }: { user: AdminUser; onClose: () => void }) {
  const { show } = useToast()
  const [password, setPassword] = useState('')
  const [confirm, setConfirm] = useState('')

  const reset = useMutation({
    mutationFn: () => resetUserPassword(user.id, password),
    onSuccess: () => {
      show('تم تعيين كلمة مرور جديدة')
      onClose()
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّر تعيين كلمة المرور', 'error'),
  })

  const mismatch = confirm.length > 0 && password !== confirm

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    if (mismatch) return
    reset.mutate()
  }

  return (
    <Modal title={`إعادة تعيين كلمة المرور — ${user.name}`} onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-3">
        <Field
          label="كلمة المرور الجديدة"
          type="password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          minLength={8}
          required
        />
        <Field
          label="تأكيد كلمة المرور"
          type="password"
          value={confirm}
          onChange={(e) => setConfirm(e.target.value)}
          error={mismatch ? 'كلمتا المرور غير متطابقتين.' : undefined}
          required
        />
        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" onClick={onClose} disabled={reset.isPending}>
            إلغاء
          </Button>
          <Button type="submit" disabled={reset.isPending || mismatch || password.length < 8}>
            {reset.isPending ? 'جارٍ…' : 'حفظ'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
