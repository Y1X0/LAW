import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, type RenderResult } from '@testing-library/react'
import type { ReactElement, ReactNode } from 'react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { ToastProvider } from '@/core/ui/ToastProvider'

const future = { v7_startTransition: true, v7_relativeSplatPath: true } as const

interface Options {
  /** المسار الابتدائي (لاختبار مكوّنات تعتمد على useParams). */
  route?: string
  /** نمط المسار (مثل "/payslips/:id"). عند تمريره يُغلَّف المكوّن بـ Route. */
  path?: string
}

/** يغلّف المكوّن بـ QueryClient (بلا إعادة محاولة) + Router للاختبارات. */
export function renderWithProviders(ui: ReactElement, options: Options = {}): RenderResult {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  })
  const Wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={queryClient}>
      <ToastProvider>
        <MemoryRouter initialEntries={[options.route ?? '/']} future={future}>
          {options.path ? (
            <Routes>
              <Route path={options.path} element={children} />
            </Routes>
          ) : (
            children
          )}
        </MemoryRouter>
      </ToastProvider>
    </QueryClientProvider>
  )
  return render(ui, { wrapper: Wrapper })
}
