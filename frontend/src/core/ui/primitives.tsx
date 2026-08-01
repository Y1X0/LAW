import type {
  ButtonHTMLAttributes,
  InputHTMLAttributes,
  ReactNode,
  SelectHTMLAttributes,
  TextareaHTMLAttributes,
} from 'react'

/** زر أساسي (أساسي/ثانوي). */
export function Button({
  variant = 'primary',
  className = '',
  ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & { variant?: 'primary' | 'ghost' }) {
  const base =
    'lp-press inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed disabled:active:transform-none'
  const styles =
    variant === 'primary'
      ? 'bg-brand-600 text-white shadow-sm hover:bg-brand-700 hover:shadow'
      : 'bg-transparent text-brand-700 hover:bg-brand-50'
  return <button className={`${base} ${styles} ${className}`} {...props} />
}

/** بطاقة محتوى — حافة ناعمة وظلّ هادئ؛ مع رفعة خفيفة عند المرور (interactive). */
export function Card({
  children,
  className = '',
  interactive = false,
}: {
  children: ReactNode
  className?: string
  interactive?: boolean
}) {
  return (
    <div
      className={`lp-card rounded-2xl border border-slate-200/80 bg-white p-5 shadow-card ${
        interactive ? 'lp-card-hover' : ''
      } ${className}`}
    >
      {children}
    </div>
  )
}

/** شارة حالة صغيرة بنبرة لونية. */
export function Badge({
  children,
  tone = 'slate',
}: {
  children: ReactNode
  tone?: 'green' | 'amber' | 'slate' | 'navy'
}) {
  const tones: Record<string, string> = {
    green: 'bg-green-50 text-green-700 ring-green-600/20',
    amber: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    slate: 'bg-slate-100 text-slate-600 ring-slate-500/20',
    navy: 'bg-brand-50 text-brand-700 ring-brand-600/20',
  }
  return (
    <span
      className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset ${tones[tone]}`}
    >
      {children}
    </span>
  )
}

/** حقل إدخال بعنوان ورسالة خطأ اختيارية. */
export function Field({
  label,
  error,
  ...props
}: InputHTMLAttributes<HTMLInputElement> & { label: string; error?: string }) {
  return (
    <label className="block space-y-1">
      <span className="text-sm font-medium text-slate-700">{label}</span>
      <input
        className="lp-input w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none hover:border-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15"
        {...props}
      />
      {error ? <span className="text-xs text-red-600">{error}</span> : null}
    </label>
  )
}

const controlClass =
  'lp-input w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none hover:border-slate-400 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15'

/** قائمة منسدلة بعنوان ورسالة خطأ اختيارية. */
export function SelectField({
  label,
  error,
  children,
  ...props
}: SelectHTMLAttributes<HTMLSelectElement> & { label: string; error?: string }) {
  return (
    <label className="block space-y-1">
      <span className="text-sm font-medium text-slate-700">{label}</span>
      <select className={controlClass} {...props}>
        {children}
      </select>
      {error ? <span className="text-xs text-red-600">{error}</span> : null}
    </label>
  )
}

/** حقل نصّي متعدّد الأسطر بعنوان ورسالة خطأ اختيارية. */
export function TextareaField({
  label,
  error,
  ...props
}: TextareaHTMLAttributes<HTMLTextAreaElement> & { label: string; error?: string }) {
  return (
    <label className="block space-y-1">
      <span className="text-sm font-medium text-slate-700">{label}</span>
      <textarea className={controlClass} {...props} />
      {error ? <span className="text-xs text-red-600">{error}</span> : null}
    </label>
  )
}
