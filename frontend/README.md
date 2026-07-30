# بوابة الموظف (Employee Web Portal) — Epic 10

واجهة **Web للموظف** تستهلك نقاط `/api/me/*` من الباك-إند (Laravel). لا تحتوي أي منطق أعمال — **الباك-إند هو الحكم النهائي** على الصلاحيات والحسابات.

## الـ Stack
React + Vite + TypeScript · TanStack Query · React Router · Tailwind CSS (RTL) · Zod (تحقّق استجابات API) · Vitest (اختبارات وحدة). Playwright للـ E2E يُضاف في Issue #63.

## البنية (الأساس #57)
```
src/
  lib/        env · queryClient
  api/        types (ApiEnvelope/ApiError) · client (fetch + Bearer + تجديد التوكن) · auth (Zod)
  auth/       tokenStorage · AuthContext · useAuth · ProtectedRoute
  components/ ui (primitives + حالات loading/empty/error/permission) · layout (AppLayout RTL)
  pages/      LoginPage · HomePage (+ Placeholder للأقسام القادمة)
  routes.tsx  التوجيه المحمي
```

## المبادئ
- **الصلاحيات:** لا قائمة صلاحيات في العميل — يعالج العميل 401 (تجديد ثم خروج) و403 (لا صلاحية / حساب غير مرتبط) مركزياً.
- **الحالات:** كل شاشة تعرض loading / empty / error / permission بوضوح.
- **RTL** أولاً (عربي).

## التشغيل
```bash
npm install
cp .env.example .env      # VITE_API_BASE_URL=/api
npm run dev               # يبروكسي /api إلى http://localhost:8000 (أو VITE_DEV_API_TARGET)
```

## الأوامر
`npm run dev` · `npm run build` · `npm run lint` · `npm run type-check` · `npm test`
