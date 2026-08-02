import type { ReactNode } from 'react'

/** جدول RTL Premium بعناوين (رأس لاصق + تناوب/تحويم) — لتبويبات ملف الموظف. */
export function HrDataTable({ head, children }: { head: string[]; children: ReactNode }) {
  return (
    <div className="lp-table-wrap rounded-xl border border-slate-200 bg-white shadow-card">
      <table className="lp-table sm:min-w-[560px] text-right text-sm">
        <thead>
          <tr className="text-xs">
            {head.map((h) => (
              <th key={h} className="px-4 py-3 text-right">
                {h}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>{children}</tbody>
      </table>
    </div>
  )
}
