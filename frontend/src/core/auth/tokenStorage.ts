/** تخزين توكنات المصادقة (Access/Refresh) في localStorage. */
export interface StoredTokens {
  access_token: string
  refresh_token: string
  access_expires_at?: string
  refresh_expires_at?: string
}

const KEY = 'law.portal.tokens'

export const tokenStorage = {
  get(): StoredTokens | null {
    try {
      const raw = localStorage.getItem(KEY)
      return raw ? (JSON.parse(raw) as StoredTokens) : null
    } catch {
      return null
    }
  },

  set(tokens: StoredTokens): void {
    localStorage.setItem(KEY, JSON.stringify(tokens))
  },

  clear(): void {
    localStorage.removeItem(KEY)
  },

  accessToken(): string | null {
    return this.get()?.access_token ?? null
  },
}
