import { useState, type FormEvent } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Button, Field, SelectField, TextareaField } from '@/core/ui/primitives'
import { Modal } from '@/admin/ui/Modal'
import { useToast } from '@/core/ui/useToast'
import { ApiError } from '@/core/api/types'
import {
  ROLE_ACTIONS,
  type CustomField,
  type CustomFieldInput,
  type CustomFieldMeta,
  type RoleAction,
  createCustomField,
  updateCustomField,
} from '@/admin/api/customFields'

type RoleMatrix = Record<RoleAction, string[]>

function initialRoles(field: CustomField | null): RoleMatrix {
  return {
    view_roles: field?.view_roles ?? [],
    edit_roles: field?.edit_roles ?? [],
    search_roles: field?.search_roles ?? [],
    export_roles: field?.export_roles ?? [],
  }
}

/**
 * نموذج إنشاء/تعديل حقل مخصّص (Phase 12 · PR-2). كل الخيارات (الأنواع/السياقات/الأدوار) تأتي
 * من meta الخادم. صلاحيات الحقل تُرسم كمصفوفة (دور × إجراء) — فارغة تعني «يرث صلاحية الكيان».
 * لا يخزّن قيماً داخل الكيانات — تعريف فقط.
 */
export function CustomFieldFormModal({
  field,
  entity,
  meta,
  onClose,
}: {
  field: CustomField | null
  entity: string
  meta: CustomFieldMeta
  onClose: () => void
}) {
  const qc = useQueryClient()
  const { show } = useToast()
  const editing = field !== null

  const [label, setLabel] = useState(field?.label ?? '')
  const [key, setKey] = useState(field?.key ?? '')
  const [description, setDescription] = useState(field?.description ?? '')
  const [type, setType] = useState(field?.type ?? meta.types[0]?.key ?? 'text')
  const [required, setRequired] = useState(field?.required ?? false)
  const [optionsText, setOptionsText] = useState((field?.options ?? []).join('\n'))
  const [displayIn, setDisplayIn] = useState<string[]>(field?.display_in ?? ['create', 'edit', 'details'])
  const [roles, setRoles] = useState<RoleMatrix>(initialRoles(field))
  const [sortOrder, setSortOrder] = useState(field?.sort_order ?? 0)
  const [active, setActive] = useState(field?.is_active ?? true)

  function toggleContext(ctx: string) {
    setDisplayIn((cur) => (cur.includes(ctx) ? cur.filter((c) => c !== ctx) : [...cur, ctx]))
  }

  function toggleRole(action: RoleAction, roleId: string) {
    setRoles((cur) => {
      const has = cur[action].includes(roleId)
      return { ...cur, [action]: has ? cur[action].filter((r) => r !== roleId) : [...cur[action], roleId] }
    })
  }

  const save = useMutation({
    mutationFn: () => {
      const options = type === 'dropdown'
        ? optionsText.split('\n').map((o) => o.trim()).filter(Boolean)
        : undefined
      const base = {
        label: label.trim(),
        description: description.trim() || undefined,
        type,
        required,
        options,
        display_in: displayIn,
        view_roles: roles.view_roles,
        edit_roles: roles.edit_roles,
        search_roles: roles.search_roles,
        export_roles: roles.export_roles,
        sort_order: sortOrder,
        is_active: active,
      }
      return editing
        ? updateCustomField(field.id, base)
        : createCustomField({ ...base, entity, key: key.trim() } as CustomFieldInput)
    },
    onSuccess: () => {
      show(editing ? 'تم تحديث الحقل' : 'تم إنشاء الحقل')
      void qc.invalidateQueries({ queryKey: ['admin', 'custom-fields'] })
      onClose()
    },
    onError: (e) => show(e instanceof ApiError ? e.message : 'تعذّرت العملية', 'error'),
  })

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    save.mutate()
  }

  return (
    <Modal title={editing ? `تعديل الحقل — ${field.label}` : 'إضافة حقل مخصّص'} onClose={onClose}>
      <form onSubmit={onSubmit} className="max-h-[70vh] space-y-3 overflow-y-auto pl-1">
        <Field label="اسم الحقل" value={label} onChange={(e) => setLabel(e.target.value)} required />

        {editing ? (
          <Field label="المُعرّف التقني" value={key} disabled />
        ) : (
          <Field
            label="المُعرّف التقني (أحرف إنجليزية صغيرة و_)"
            value={key}
            onChange={(e) => setKey(e.target.value)}
            placeholder="contract_number"
            required
          />
        )}

        <Field label="الوصف (اختياري)" value={description} onChange={(e) => setDescription(e.target.value)} />

        <SelectField label="النوع" value={type} onChange={(e) => setType(e.target.value)}>
          {meta.types.map((t) => (
            <option key={t.key} value={t.key}>{t.label}</option>
          ))}
        </SelectField>

        {type === 'dropdown' && (
          <TextareaField
            label="خيارات القائمة (خيار في كل سطر)"
            rows={3}
            value={optionsText}
            onChange={(e) => setOptionsText(e.target.value)}
          />
        )}

        <div className="flex flex-wrap items-center gap-4">
          <label className="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" checked={required} onChange={(e) => setRequired(e.target.checked)} className="h-4 w-4 rounded border-slate-300" />
            إلزامي
          </label>
          <label className="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" checked={active} onChange={(e) => setActive(e.target.checked)} className="h-4 w-4 rounded border-slate-300" />
            مُفعّل
          </label>
          <label className="flex items-center gap-2 text-sm text-slate-600">
            الترتيب
            <input
              type="number"
              min={0}
              value={sortOrder}
              onChange={(e) => setSortOrder(Number(e.target.value) || 0)}
              className="w-20 rounded-lg border border-slate-300 px-2 py-1 text-sm"
              aria-label="الترتيب"
            />
          </label>
        </div>

        <fieldset className="rounded-xl border border-slate-200 p-3">
          <legend className="px-1 text-sm font-medium text-slate-700">أين يظهر الحقل</legend>
          <div className="flex flex-wrap gap-4">
            {meta.contexts.map((c) => (
              <label key={c.key} className="flex items-center gap-2 text-sm text-slate-600">
                <input
                  type="checkbox"
                  checked={displayIn.includes(c.key)}
                  onChange={() => toggleContext(c.key)}
                  className="h-4 w-4 rounded border-slate-300"
                />
                {c.label}
              </label>
            ))}
          </div>
        </fieldset>

        <fieldset className="rounded-xl border border-slate-200 p-3">
          <legend className="px-1 text-sm font-medium text-slate-700">صلاحيات الحقل (فارغ = يرث صلاحية الكيان)</legend>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-slate-500">
                  <th className="p-1 text-right font-medium">الدور</th>
                  {ROLE_ACTIONS.map((a) => (
                    <th key={a.key} className="p-1 text-center font-medium">{a.label}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {meta.roles.map((role) => (
                  <tr key={role.id} className="border-t border-slate-100">
                    <td className="p-1 text-slate-700">{role.name}</td>
                    {ROLE_ACTIONS.map((a) => (
                      <td key={a.key} className="p-1 text-center">
                        <input
                          type="checkbox"
                          checked={roles[a.key].includes(role.id)}
                          onChange={() => toggleRole(a.key, role.id)}
                          aria-label={`${role.name} — ${a.label}`}
                          className="h-4 w-4 rounded border-slate-300"
                        />
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </fieldset>

        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" onClick={onClose} disabled={save.isPending}>إلغاء</Button>
          <Button type="submit" disabled={save.isPending}>{save.isPending ? 'جارٍ…' : 'حفظ'}</Button>
        </div>
      </form>
    </Modal>
  )
}
