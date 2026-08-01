import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { authApi, type AuthUser } from '@/core/api/auth'
import { setUnauthorizedHandler } from '@/core/api/client'
import { AuthContext, type AuthContextValue, type AuthStatus } from './authContext'
import { tokenStorage } from './tokenStorage'

export function AuthProvider({ children }: { children: ReactNode }) {
  const [status, setStatus] = useState<AuthStatus>('loading')
  const [user, setUser] = useState<AuthUser | null>(null)
  const queryClient = useQueryClient()

  const reset = useCallback(() => {
    tokenStorage.clear()
    // مسح كاش React Query بالكامل حتى لا تبقى بيانات المستخدم السابق في متصفّح مشترك.
    queryClient.clear()
    setUser(null)
    setStatus('unauthenticated')
  }, [queryClient])

  // إذا فشلت المصادقة نهائياً في أي نداء (401 بعد تعذّر التجديد) → أعِد الحالة (مع مسح الكاش).
  useEffect(() => {
    setUnauthorizedHandler(() => {
      queryClient.clear()
      setUser(null)
      setStatus('unauthenticated')
    })
    return () => setUnauthorizedHandler(null)
  }, [queryClient])

  // عند الإقلاع: إن وُجد توكن، تحقّق منه عبر /auth/me.
  useEffect(() => {
    let active = true
    if (!tokenStorage.accessToken()) {
      setStatus('unauthenticated')
      return
    }
    authApi
      .me()
      .then((u) => {
        if (!active) return
        setUser(u)
        setStatus('authenticated')
      })
      .catch(() => active && reset())
    return () => {
      active = false
    }
  }, [reset])

  const login = useCallback(
    async (email: string, password: string) => {
      const { user: u, tokens } = await authApi.login(email, password)
      tokenStorage.set(tokens)
      // امسح كاش المستخدم السابق (كشوف القدرات مخزّنة بلا نهاية) قبل تثبيت الجلسة
      // الجديدة، وإلّا قد يرث المستخدم الجديد بوابة/تنقّل المستخدم السابق في تبويب مشترك.
      queryClient.clear()
      setUser(u)
      setStatus('authenticated')
    },
    [queryClient],
  )

  const logout = useCallback(async () => {
    try {
      await authApi.logout()
    } catch {
      // حتى لو فشل نداء الخروج، نُنهي الجلسة محلياً.
    }
    reset()
  }, [reset])

  const value = useMemo<AuthContextValue>(
    () => ({ status, user, login, logout }),
    [status, user, login, logout],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
