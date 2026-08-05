import { z } from 'zod'
import { api } from './client'

/** مستخدم مصادق (مطابق لحمولة الباك-إند من /auth/login و /auth/me). */
export const userSchema = z.object({
  id: z.number(),
  name: z.string(),
  username: z.string().nullable().optional(),
  email: z.string(),
  status: z.string().nullable().optional(),
  mfa_enabled: z.boolean().optional(),
})
export type AuthUser = z.infer<typeof userSchema>

export const tokensSchema = z.object({
  access_token: z.string(),
  refresh_token: z.string(),
  access_expires_at: z.string().optional(),
  refresh_expires_at: z.string().optional(),
})
export type AuthTokens = z.infer<typeof tokensSchema>

const loginResponseSchema = z.object({ user: userSchema, tokens: tokensSchema })
const meResponseSchema = z.object({ user: userSchema })

export const authApi = {
  async login(email: string, password: string): Promise<{ user: AuthUser; tokens: AuthTokens }> {
    const data = await api.post<unknown>('auth/login', { email, password })
    return loginResponseSchema.parse(data)
  },

  async me(): Promise<AuthUser> {
    const data = await api.get<unknown>('auth/me')
    return meResponseSchema.parse(data).user
  },

  async logout(): Promise<void> {
    await api.post('auth/logout')
  },

  /** طلب رابط إعادة تعيين كلمة المرور — يعيد رسالة عامة دائماً (منع تعداد الحسابات). */
  async forgotPassword(email: string): Promise<void> {
    await api.post('auth/forgot-password', { email })
  },

  /** تعيين كلمة مرور جديدة باستخدام الرمز من رابط البريد. */
  async resetPassword(input: {
    token: string
    email: string
    password: string
    password_confirmation: string
  }): Promise<void> {
    await api.post('auth/reset-password', input)
  },
}
