import { describe, expect, it } from 'vitest'
import { tokenStorage } from './tokenStorage'

describe('tokenStorage', () => {
  it('يخزّن ويسترجع التوكنات', () => {
    tokenStorage.set({ access_token: 'a', refresh_token: 'r' })
    expect(tokenStorage.get()).toEqual({ access_token: 'a', refresh_token: 'r' })
    expect(tokenStorage.accessToken()).toBe('a')
  })

  it('يعيد null عندما لا يوجد توكن', () => {
    expect(tokenStorage.get()).toBeNull()
    expect(tokenStorage.accessToken()).toBeNull()
  })

  it('يمسح التوكنات', () => {
    tokenStorage.set({ access_token: 'a', refresh_token: 'r' })
    tokenStorage.clear()
    expect(tokenStorage.get()).toBeNull()
  })

  it('لا ينهار عند قيمة تالفة', () => {
    localStorage.setItem('law.portal.tokens', '{not json')
    expect(tokenStorage.get()).toBeNull()
  })
})
