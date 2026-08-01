import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import { ToastProvider } from './ToastProvider'
import { useToast } from './useToast'

function Trigger({ tone }: { tone?: 'success' | 'error' | 'warning' }) {
  const { show } = useToast()
  return <button onClick={() => show('تمّت العملية', tone)}>أظهر</button>
}

describe('ToastProvider', () => {
  it('يعرض التوست عند استدعاء show', async () => {
    const user = userEvent.setup()
    render(
      <ToastProvider>
        <Trigger />
      </ToastProvider>,
    )
    await user.click(screen.getByRole('button', { name: 'أظهر' }))
    expect(await screen.findByRole('status')).toHaveTextContent('تمّت العملية')
  })

  it('useToast بلا مزوّد يعمل بأمان (no-op) دون كسر', async () => {
    const user = userEvent.setup()
    render(<Trigger />) // بلا ToastProvider
    await user.click(screen.getByRole('button', { name: 'أظهر' }))
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
  })
})
