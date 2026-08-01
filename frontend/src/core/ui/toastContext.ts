import { createContext } from 'react'

export type ToastTone = 'success' | 'error' | 'warning'
export interface ToastItem {
  message: string
  tone: ToastTone
}

/** واجهة التوست العامة — `show` متاحة من أي مكان تحت المزوّد. */
export interface ToastApi {
  show: (message: string, tone?: ToastTone) => void
}

export const ToastContext = createContext<ToastApi | null>(null)
