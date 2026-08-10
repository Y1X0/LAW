import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { App } from './App'
import { initSentry } from '@/core/observability/sentry'
import './index.css'

// تهيئة مراقبة الأخطاء قبل الرسم (خاملة ما لم يُضبط VITE_SENTRY_DSN).
initSentry()

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
