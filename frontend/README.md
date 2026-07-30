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
- **مصدر عنوان الـ API:** متغيّر البيئة `VITE_API_URL` فقط (لا عنوان ثابت داخل الكود).

## قرار أمني موثّق — تخزين التوكن
يُخزَّن التوكن في **`localStorage`** (في `src/auth/tokenStorage.ts`). مقبول لـ MVP، لكنه **قابل للقراءة عبر XSS**. تحسينات لاحقة (Issue مستقل عند تصلّب الأمان):
- الأفضلية: **Refresh token في cookie بخاصية HttpOnly** + الاحتفاظ بالـ access token في الذاكرة فقط، أو `sessionStorage` لتقليل زمن التعرّض.
- سياسة **CSP** صارمة + مراجعة XSS. التخزين مُجرَّد خلف `tokenStorage` فيسهل تبديله لاحقاً بلا لمس بقية الكود.

## التشغيل
```bash
npm install
cp .env.example .env      # VITE_API_URL=/api
npm run dev               # يبروكسي /api إلى http://localhost:8000 (أو VITE_DEV_API_TARGET)
```

## الأوامر
`npm run dev` · `npm run build` · `npm run lint` · `npm run type-check` · `npm test`
