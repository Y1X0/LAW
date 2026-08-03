import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { describe, expect, it } from 'vitest'
import { PayrollLayout } from './PayrollLayout'

function renderAt(route: string) {
  return render(
    <MemoryRouter initialEntries={[route]} future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <Routes>
        <Route element={<PayrollLayout />}>
          <Route path="payroll" element={<div>محتوى اللوحة</div>} />
          <Route path="payroll/runs" element={<div>محتوى المسيرات</div>} />
        </Route>
      </Routes>
    </MemoryRouter>,
  )
}

describe('PayrollLayout', () => {
  it('يعرض التنقّل الفرعي الموحّد فوق محتوى الصفحة', async () => {
    renderAt('/payroll')
    for (const label of ['الرئيسية', 'الفترات', 'المكوّنات', 'رواتب الموظفين', 'المسيرات', 'التقارير']) {
      expect(screen.getByRole('link', { name: label })).toBeInTheDocument()
    }
    expect(await screen.findByText('محتوى اللوحة')).toBeInTheDocument()
    // الرابط النشِط يحمل aria-current للوصولية.
    expect(screen.getByRole('link', { name: 'الرئيسية' })).toHaveAttribute('aria-current', 'page')
  })

  it('يبرز «المسيرات» على مسارها الفرعي', async () => {
    renderAt('/payroll/runs')
    expect(await screen.findByText('محتوى المسيرات')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'المسيرات' })).toHaveAttribute('aria-current', 'page')
    // «الرئيسية» (end=true) لا تبقى نشِطة على المسارات الفرعية.
    expect(screen.getByRole('link', { name: 'الرئيسية' })).not.toHaveAttribute('aria-current')
  })
})
