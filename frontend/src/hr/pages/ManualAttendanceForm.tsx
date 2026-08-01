import { useState, type FormEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Button, Card, Field, SelectField, TextareaField } from '@/core/ui/primitives'
import { fetchEmployees } from '@/hr/api/employees'
import { ATTENDANCE_STATUSES, attendanceStatusLabel, type ManualAttendanceInput } from '@/hr/api/attendance'

/** نموذج قيد حضور يدوي — يختار الموظف من قائمة موجودة ويرسل عبر API الموجود. */
export function ManualAttendanceForm({
  submitting,
  onSubmit,
  onCancel,
}: {
  submitting: boolean
  onSubmit: (input: ManualAttendanceInput) => void
  onCancel: () => void
}) {
  const employees = useQuery({
    queryKey: ['hr', 'employees', 'options'],
    queryFn: () => fetchEmployees({ status: 'active', perPage: 100 }),
  })

  const [employeeId, setEmployeeId] = useState('')
  const [workDate, setWorkDate] = useState('')
  const [status, setStatus] = useState('present')
  const [checkIn, setCheckIn] = useState('')
  const [checkOut, setCheckOut] = useState('')
  const [notes, setNotes] = useState('')

  const canSubmit = employeeId !== '' && workDate !== '' && status !== '' && !submitting

  function handleSubmit(e: FormEvent) {
    e.preventDefault()
    if (!canSubmit) return
    onSubmit({
      employee_id: Number(employeeId),
      work_date: workDate,
      status,
      check_in: checkIn || null,
      check_out: checkOut || null,
      notes: notes.trim() || null,
    })
  }

  return (
    <Card className="lp-reveal">
      <h2 className="mb-3 text-sm font-semibold text-slate-700">قيد حضور يدوي</h2>
      <form onSubmit={handleSubmit} className="space-y-3">
        <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
          <SelectField label="الموظف" value={employeeId} onChange={(e) => setEmployeeId(e.target.value)} required>
            <option value="">اختر موظفاً…</option>
            {(employees.data?.items ?? []).map((emp) => (
              <option key={emp.id} value={emp.id}>
                {emp.full_name_ar}
              </option>
            ))}
          </SelectField>
          <Field label="التاريخ" type="date" value={workDate} onChange={(e) => setWorkDate(e.target.value)} required />
          <SelectField label="الحالة" value={status} onChange={(e) => setStatus(e.target.value)} required>
            {ATTENDANCE_STATUSES.map((s) => (
              <option key={s} value={s}>
                {attendanceStatusLabel(s)}
              </option>
            ))}
          </SelectField>
          <Field label="الحضور" type="datetime-local" value={checkIn} onChange={(e) => setCheckIn(e.target.value)} />
          <Field label="الانصراف" type="datetime-local" value={checkOut} onChange={(e) => setCheckOut(e.target.value)} />
        </div>
        <TextareaField label="ملاحظات" rows={2} value={notes} onChange={(e) => setNotes(e.target.value)} placeholder="اختياري…" />
        <div className="flex gap-2">
          <Button type="submit" disabled={!canSubmit}>
            {submitting ? 'جارٍ الحفظ…' : 'حفظ القيد'}
          </Button>
          <Button type="button" variant="ghost" onClick={onCancel} disabled={submitting}>
            إلغاء
          </Button>
        </div>
      </form>
    </Card>
  )
}
