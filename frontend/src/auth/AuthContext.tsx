import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { authApi, type AuthUser } from '../api/auth'
import { setUnauthorizedHandler } from '../api/client'
import { AuthContext, type AuthContextValue, type AuthStatus } from './authContext'
import { tokenStorage } from './tokenStorage'

export function AuthProvider({ children }: { children: ReactNode }) {
  const [status, setStatus] = useState<AuthStatus>('loading')
  const [user, setUser] = useState<AuthUser | null>(null)

  const reset = useCallback(() => {
    tokenStorage.clear()
    setUser(null)
    setStatus('unauthenticated')
  }, [])

  // إذا فشلت المصادقة نهائياً في أي نداء (401 بعد تعذّر التجديد) → أعِد الحالة.
  useEffect(() => {
    setUnauthorizedHandler(() => {
      setUser(null)
      setStatus('unauthenticated')
    })
    return () => setUnauthorizedHandler(null)
  }, [])

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

  const login = useCallback(async (email: string, password: string) => {
    const { user: u, tokens } = await authApi.login(email, password)
    tokenStorage.set(tokens)
    setUser(u)
    setStatus('authenticated')
  }, [])

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
