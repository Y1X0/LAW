import { Field, SelectField, TextareaField } from '@/core/ui/primitives'
import type { CustomFieldFormItem } from '@/legal/api/cases'

type CFValue = string | boolean

/**
 * قسم الحقول المخصّصة داخل نموذج القضية (Phase 12 · PR-4). يرسم كل حقل حسب نوعه من مخطّط
 * الخادم. الخادم هو الحكم: الحقول غير القابلة للتعديل (editable=false) تظهر معطّلة (للعرض فقط)،
 * والواجهة تعكس editable فقط بلا أي منطق صلاحيات. لا يظهر القسم إن لم تكن هناك حقول.
 */
export function CaseCustomFieldsSection({
  items,
  values,
  onChange,
}: {
  items: CustomFieldFormItem[]
  values: Record<string, CFValue>
  onChange: (key: string, value: CFValue) => void
}) {
  if (items.length === 0) {
    return null
  }

  return (
    <fieldset className="rounded-xl border border-slate-200 p-3">
      <legend className="px-1 text-sm font-medium text-slate-700">الحقول المخصّصة</legend>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        {items.map((f) => (
          <FieldControl key={f.key} field={f} value={values[f.key] ?? ''} onChange={(v) => onChange(f.key, v)} />
        ))}
      </div>
    </fieldset>
  )
}

function FieldControl({
  field,
  value,
  onChange,
}: {
  field: CustomFieldFormItem
  value: CFValue
  onChange: (value: CFValue) => void
}) {
  const label = field.required ? `${field.label} *` : field.label
  const disabled = !field.editable

  if (field.type === 'boolean') {
    return (
      <label className="flex items-center gap-2 self-end py-2 text-sm text-slate-600">
        <input
          type="checkbox"
          checked={value === true || value === 'true' || value === '1'}
          disabled={disabled}
          onChange={(e) => onChange(e.target.checked)}
          className="h-4 w-4 rounded border-slate-300"
        />
        {label}
      </label>
    )
  }

  if (field.type === 'dropdown') {
    return (
      <SelectField label={label} value={String(value)} disabled={disabled} onChange={(e) => onChange(e.target.value)}>
        <option value="">— اختر —</option>
        {(field.options ?? []).map((o) => (
          <option key={o} value={o}>{o}</option>
        ))}
      </SelectField>
    )
  }

  if (field.type === 'longtext') {
    return <TextareaField label={label} value={String(value)} disabled={disabled} onChange={(e) => onChange(e.target.value)} />
  }

  const inputType = field.type === 'number' || field.type === 'currency'
    ? 'number'
    : field.type === 'date'
      ? 'date'
      : field.type === 'email'
        ? 'email'
        : field.type === 'url'
          ? 'url'
          : 'text'

  return <Field label={label} type={inputType} value={String(value)} disabled={disabled} onChange={(e) => onChange(e.target.value)} />
}
