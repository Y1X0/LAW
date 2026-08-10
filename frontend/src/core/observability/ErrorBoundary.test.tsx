import { render, screen } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ErrorBoundary } from './ErrorBoundary'

function Boom(): never {
  throw new Error('render exploded')
}

describe('ErrorBoundary', () => {
  beforeEach(() => {
    // نكتم console.error المتوقّع من React أثناء التقاط الخطأ.
    vi.spyOn(console, 'error').mockImplementation(() => {})
  })
  afterEach(() => vi.restoreAllMocks())

  it('يعرض الأبناء عند عدم وجود خطأ', () => {
    render(
      <ErrorBoundary>
        <p>محتوى سليم</p>
      </ErrorBoundary>,
    )
    expect(screen.getByText('محتوى سليم')).toBeInTheDocument()
  })

  it('يعرض واجهة تعافٍ عربية بدل الشاشة البيضاء عند خطأ عرض', () => {
    render(
      <ErrorBoundary>
        <Boom />
      </ErrorBoundary>,
    )
    expect(screen.getByText('حدث خطأ غير متوقّع')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'إعادة تحميل الصفحة' })).toBeInTheDocument()
  })
})
