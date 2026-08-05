import { screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import type { CustomFieldFormItem } from '@/legal/api/cases'
import { CaseCustomFieldsSection } from './CaseCustomFieldsSection'

const items: CustomFieldFormItem[] = [
  { key: 'note', label: 'ملاحظة', type: 'text', options: null, required: true, editable: true, value: '' },
  { key: 'secret', label: 'قيمة سرّية', type: 'currency', options: null, required: false, editable: false, value: 5000 },
  { key: 'grade', label: 'الدرجة', type: 'dropdown', options: ['A', 'B'], required: false, editable: true, value: '' },
]

describe('CaseCustomFieldsSection', () => {
  it('يرسم الحقل القابل للتعديل كمدخل، وغير القابل معطّلاً بقيمته، والقائمة بخياراتها', () => {
    renderWithProviders(
      <CaseCustomFieldsSection
        items={items}
        values={{ note: '', secret: '5000', grade: '' }}
        onChange={() => {}}
      />,
    )

    // إلزامي بنجمة + مدخل مفعّل.
    const note = screen.getByLabelText('ملاحظة *')
    expect(note).toBeEnabled()

    // غير قابل للتعديل ⇒ معطّل، بقيمته الحالية (للعرض فقط لا الإخفاء).
    const secret = screen.getByLabelText('قيمة سرّية')
    expect(secret).toBeDisabled()
    expect(secret).toHaveValue(5000)

    // القائمة المنسدلة ترسم خياراتها من الخادم.
    expect(screen.getByRole('option', { name: 'A' })).toBeInTheDocument()
    expect(screen.getByRole('option', { name: 'B' })).toBeInTheDocument()
  })

  it('لا يعرض القسم إن لم تكن هناك حقول', () => {
    const { container } = renderWithProviders(
      <CaseCustomFieldsSection items={[]} values={{}} onChange={() => {}} />,
    )
    expect(container).toBeEmptyDOMElement()
  })

  it('يستدعي onChange عند تعديل حقل', async () => {
    const onChange = vi.fn()
    const { default: userEvent } = await import('@testing-library/user-event')
    const user = userEvent.setup()
    renderWithProviders(
      <CaseCustomFieldsSection items={[items[0]]} values={{ note: '' }} onChange={onChange} />,
    )
    await user.type(screen.getByLabelText('ملاحظة *'), 'x')
    expect(onChange).toHaveBeenCalledWith('note', 'x')
  })
})
