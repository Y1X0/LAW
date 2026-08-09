import { useState, type FormEvent } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Button, Field, SelectField } from '@/core/ui/primitives'
import { useToast } from '@/core/ui/useToast'
import { useFormErrors } from '@/core/api/useFormErrors'
import { Modal } from '@/admin/ui/Modal'
import { USER_STATUSES, createUser, userStatusLabel } from '@/admin/api/users'

/** نموذج إنشاء مستخدم جديد (ADMIN-2). الأدوار تُدار لاحقاً في ADMIN-3. */
export function CreateUserModal({ onClose }: { onClose: () => void }) {
  const qc = useQueryClient()
  const { show } = useToast()
  const formErrors = useFormErrors()
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [status, setStatus] = useState('active')

  const create = useMutation({
    mutationFn: () =>
      createUser({
        name: name.trim(),
        email: email.trim(),
        username: username.trim() || undefined,
        password,
        status,
      }),
    onSuccess: () => {
      show('تم إنشاء المستخدم')
      void qc.invalidateQueries({ queryKey: ['admin', 'users'] })
      onClose()
    },
    onError: formErrors.onError,
  })

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    formErrors.reset()
    create.mutate()
  }

  return (
    <Modal title="إنشاء مستخدم جديد" onClose={onClose}>
      <form onSubmit={onSubmit} className="space-y-3">
        <Field label="الاسم" value={name} onChange={(e) => setName(e.target.value)} required error={formErrors.fieldError('name')} />
        <Field
          label="البريد الإلكتروني"
          type="email"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          required
          error={formErrors.fieldError('email')}
        />
        <Field
          label="اسم المستخدم (اختياري)"
          value={username}
          onChange={(e) => setUsername(e.target.value)}
          error={formErrors.fieldError('username')}
        />
        <Field
          label="كلمة المرور"
          type="password"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          minLength={8}
          required
          error={formErrors.fieldError('password')}
        />
        <SelectField label="الحالة" value={status} onChange={(e) => setStatus(e.target.value)}>
          {USER_STATUSES.map((s) => (
            <option key={s} value={s}>
              {userStatusLabel(s)}
            </option>
          ))}
        </SelectField>
        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" onClick={onClose} disabled={create.isPending}>
            إلغاء
          </Button>
          <Button type="submit" disabled={create.isPending}>
            {create.isPending ? 'جارٍ…' : 'إنشاء'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
