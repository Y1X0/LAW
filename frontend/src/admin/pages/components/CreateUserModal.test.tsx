import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tokenStorage } from '@/core/auth/tokenStorage'
import { renderWithProviders } from '@/core/test/renderWithProviders'
import { CreateUserModal } from './CreateUserModal'

function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

afterEach(() => {
  vi.restoreAllMocks()
  tokenStorage.clear()
})

/** يملأ الحقول الأساسية ويُرسِل النموذج. */
async function fillAndSubmit() {
  const user = userEvent.setup()
  const dialog = await screen.findByRole('dialog')
  await user.type(within(dialog).getByLabelText('الاسم'), 'مستخدم جديد')
  await user.type(within(dialog).getByLabelText('البريد الإلكتروني'), 'dup@law.test')
  await user.type(within(dialog).getByLabelText('كلمة المرور'), 'weak')
  await user.click(within(dialog).getByRole('button', { name: 'إنشاء' }))
  return dialog
}

describe('CreateUserModal — أخطاء الحقول عبر المعالج المركزي', () => {
  it('422 متعدد الحقول: كل خطأ يظهر تحت حقله', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        json(
          {
            data: null,
            meta: null,
            errors: {
              code: 'VALIDATION_ERROR',
              message: 'تحقّق من الحقول المميّزة بالأحمر.',
              fields: {
                email: ['قيمة البريد الإلكتروني مستخدمة مسبقاً.'],
                password: ['يجب أن تحتوي كلمة المرور على رمز واحد على الأقل.'],
              },
            },
          },
          422,
        ),
      ),
    )

    renderWithProviders(<CreateUserModal onClose={() => {}} />)
    const dialog = await fillAndSubmit()

    // كل رسالة تظهر تحت حقلها الصحيح (لا فشل صامت، لا رسالة ثابتة).
    expect(await within(dialog).findByText('قيمة البريد الإلكتروني مستخدمة مسبقاً.')).toBeInTheDocument()
    expect(within(dialog).getByText('يجب أن تحتوي كلمة المرور على رمز واحد على الأقل.')).toBeInTheDocument()
  })

  it('409 (تعارض): يعرض رسالة الخادم الواضحة ولا يبتلعها', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue(
        json(
          { data: null, meta: null, errors: { code: 'CONFLICT_DUPLICATE', message: 'القيمة المُدخلة مستخدمة مسبقاً.' } },
          409,
        ),
      ),
    )

    renderWithProviders(<CreateUserModal onClose={() => {}} />)
    await fillAndSubmit()

    // الرسالة العامّة تظهر في التوست (role=status) — رسالة الخادم لا رسالة ثابتة.
    await waitFor(() => expect(screen.getByText('القيمة المُدخلة مستخدمة مسبقاً.')).toBeInTheDocument())
  })

  it('انقطاع الشبكة: رسالة اتصال واضحة للمستخدم', async () => {
    tokenStorage.set({ access_token: 't', refresh_token: 'r' })
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')))

    renderWithProviders(<CreateUserModal onClose={() => {}} />)
    await fillAndSubmit()

    await waitFor(() =>
      expect(screen.getByText('تعذّر الاتصال بالخادم. تحقق من اتصال الإنترنت وحاول مرة أخرى.')).toBeInTheDocument(),
    )
  })
})
