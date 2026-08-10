import { render, screen } from '@testing-library/react'
import { RouterProvider, createMemoryRouter, Outlet } from 'react-router-dom'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { RouteErrorBoundary } from './RouteErrorBoundary'

const captureError = vi.fn()
vi.mock('./sentry', () => ({ captureError: (e: unknown) => captureError(e) }))

function Boom(): never {
  throw new Error('route render exploded')
}

/**
 * يثبت أن أخطاء عرض المسارات (data router) يلتقطها errorElement فتظهر واجهة التعافي
 * العربية بدل شاشة الراوتر الافتراضية، وأن الخطأ يُبلَّغ للمراقبة.
 */
describe('RouteErrorBoundary', () => {
  beforeEach(() => {
    vi.spyOn(console, 'error').mockImplementation(() => {})
    captureError.mockClear()
  })
  afterEach(() => vi.restoreAllMocks())

  it('يعرض واجهة التعافي العربية عند خطأ عرض في مسار ويُبلّغ المراقبة', () => {
    const router = createMemoryRouter(
      [
        {
          element: <Outlet />,
          errorElement: <RouteErrorBoundary />,
          children: [{ path: '/', element: <Boom /> }],
        },
      ],
      { initialEntries: ['/'] },
    )

    render(<RouterProvider router={router} />)

    expect(screen.getByText('حدث خطأ غير متوقّع')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'إعادة تحميل الصفحة' })).toBeInTheDocument()
    expect(captureError).toHaveBeenCalledTimes(1)
  })

  it('يعرض محتوى المسار عادةً عند عدم وجود خطأ', () => {
    const router = createMemoryRouter(
      [
        {
          element: <Outlet />,
          errorElement: <RouteErrorBoundary />,
          children: [{ path: '/', element: <p>محتوى سليم</p> }],
        },
      ],
      { initialEntries: ['/'] },
    )

    render(<RouterProvider router={router} />)

    expect(screen.getByText('محتوى سليم')).toBeInTheDocument()
    expect(captureError).not.toHaveBeenCalled()
  })
})
