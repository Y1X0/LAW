import { useEffect, useState, type FormEvent } from 'react'
import { useProfile, useUpdateProfile, type Profile } from '@/employee/api/profile'
import { ApiError } from '@/core/api/types'
import { Button, Field, TextareaField } from '@/core/ui/primitives'
import { InfoRow, SectionCard } from '@/core/ui/section'
import { ErrorState, LoadingState } from '@/core/ui/states'

export function ProfilePage() {
  const { data, isPending, isError, error } = useProfile()

  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">ملفي</h1>
      {isPending ? (
        <LoadingState />
      ) : isError ? (
        <ErrorState error={error} />
      ) : (
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
          <ReadOnlyCard profile={data} />
          <EditCard profile={data} />
        </div>
      )}
    </div>
  )
}

/** حقول HR — للعرض فقط، غير قابلة للتحرير من الموظف. */
function ReadOnlyCard({ profile }: { profile: Profile }) {
  return (
    <SectionCard title="بيانات وظيفية (للعرض فقط)">
      <InfoRow label="الاسم" value={profile.name} />
      <InfoRow label="الرقم الوظيفي" value={profile.employee_number} />
      <InfoRow label="المسمّى الوظيفي" value={profile.job_title} />
      <InfoRow label="الفرع" value={profile.branch} />
      <InfoRow label="القسم" value={profile.department} />
      <InfoRow label="المدير" value={profile.manager} />
      <p className="mt-2 text-xs text-slate-400">هذه الحقول تديرها الموارد البشرية ولا يمكن تعديلها هنا.</p>
    </SectionCard>
  )
}

/** الحقول المسموح بتعديلها ذاتياً فقط. */
function EditCard({ profile }: { profile: Profile }) {
  const update = useUpdateProfile()
  const [phone, setPhone] = useState('')
  const [address, setAddress] = useState('')
  const [photoPath, setPhotoPath] = useState('')
  const [ecName, setEcName] = useState('')
  const [ecPhone, setEcPhone] = useState('')

  useEffect(() => {
    setPhone(profile.phone ?? '')
    setAddress(profile.address ?? '')
    setPhotoPath(profile.photo_path ?? '')
    setEcName(profile.emergency_contact_name ?? '')
    setEcPhone(profile.emergency_contact_phone ?? '')
  }, [profile])

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    update.mutate({
      phone,
      address,
      photo_path: photoPath,
      emergency_contact_name: ecName,
      emergency_contact_phone: ecPhone,
    })
  }

  const fieldErrors = update.error instanceof ApiError ? update.error.fields : undefined

  return (
    <SectionCard title="تعديل بياناتي">
      <form onSubmit={onSubmit} className="space-y-3">
        <Field label="الهاتف" value={phone} onChange={(e) => setPhone(e.target.value)} error={fieldErrors?.phone?.[0]} />
        <TextareaField label="العنوان" rows={2} value={address} onChange={(e) => setAddress(e.target.value)} />
        <Field label="مسار الصورة" value={photoPath} onChange={(e) => setPhotoPath(e.target.value)} />
        <Field label="جهة الطوارئ (الاسم)" value={ecName} onChange={(e) => setEcName(e.target.value)} />
        <Field label="جهة الطوارئ (الهاتف)" value={ecPhone} onChange={(e) => setEcPhone(e.target.value)} />

        {update.isError ? (
          <p role="alert" className="text-sm text-red-600">
            {update.error instanceof ApiError ? update.error.message : 'تعذّر الحفظ.'}
          </p>
        ) : null}
        {update.isSuccess ? <p className="text-sm text-green-600">تم حفظ بياناتك بنجاح.</p> : null}

        <Button type="submit" className="w-full" disabled={update.isPending}>
          {update.isPending ? 'جارٍ الحفظ…' : 'حفظ'}
        </Button>
      </form>
    </SectionCard>
  )
}
