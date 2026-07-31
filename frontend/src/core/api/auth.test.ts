import { describe, expect, it } from 'vitest'
import { tokensSchema, userSchema } from './auth'

describe('auth schemas (Zod)', () => {
  it('يقبل حمولة مستخدم صحيحة من الباك-إند', () => {
    const parsed = userSchema.parse({
      id: 1, name: 'أحمد', username: 'ahmad', email: 'a@b.com', status: 'active', mfa_enabled: false,
    })
    expect(parsed.id).toBe(1)
    expect(parsed.name).toBe('أحمد')
  })

  it('يرفض حمولة ناقصة (بلا id)', () => {
    expect(() => userSchema.parse({ name: 'x', email: 'a@b.com' })).toThrow()
  })

  it('يتحقّق من توكنات صحيحة', () => {
    const t = tokensSchema.parse({ access_token: 'a', refresh_token: 'r', access_expires_at: '2026-01-01T00:00:00Z' })
    expect(t.access_token).toBe('a')
  })
})
