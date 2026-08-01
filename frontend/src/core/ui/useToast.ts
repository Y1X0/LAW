import { useContext } from 'react'
import { ToastContext, type ToastApi } from './toastContext'

const noop: ToastApi = { show: () => {} }

/**
 * الوصول إلى نظام التوست الموحّد من أي شاشة (تحت ToastProvider).
 * يرجع no-op آمناً إن غاب المزوّد (لا يكسر مكوّناً مركّباً منفرداً).
 */
export function useToast(): ToastApi {
  return useContext(ToastContext) ?? noop
}
