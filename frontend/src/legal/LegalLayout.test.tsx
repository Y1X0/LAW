import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { describe, expect, it } from 'vitest'
import { LegalLayout } from './LegalLayout'

describe('LegalLayout', () => {
  it('يعرض التنقّل الفرعي الموحّد فوق المحتوى', async () => {
    render(
      <MemoryRouter initialEntries={['/legal']} future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
        <Routes>
          <Route element={<LegalLayout />}>
            <Route path="legal" element={<div>محتوى القضايا</div>} />
          </Route>
        </Routes>
      </MemoryRouter>,
    )
    expect(screen.getByRole('link', { name: 'القضايا' })).toHaveAttribute('aria-current', 'page')
    expect(await screen.findByText('محتوى القضايا')).toBeInTheDocument()
  })
})
