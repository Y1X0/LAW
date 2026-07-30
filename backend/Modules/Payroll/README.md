# وحدة Payroll

**الملكية (Owns):** payroll_periods, employee_salary_profiles, payroll_runs
**تُتيح (Exposes):** PayrollService

> المرجع: [docs/module-boundaries.md](../../../docs/module-boundaries.md) · Epic #31

**القاعدة الذهبية:** لا تصل هذه الوحدة إلى جداول وحدة أخرى مباشرةً — التواصل عبر الخدمات/الأحداث فقط. تقرأ من الحضور/الإجازات (لاحقاً) دون الكتابة فيها.

الحالة: **الهيكل الأساسي جاهز (Issue #32).** المكوّنات/الحساب/الكشوف في #33–#38.

## الجداول (Migrations)

| الجدول | الوصف |
|--------|-------|
| `payroll_periods` | فترة شهرية: `year`, `month`, `status` (draft/processing/approved/paid), `branch_id`. فريد `(year, month, branch_id)` |
| `employee_salary_profiles` | ملف راتب **تاريخي**: `basic_salary`, `currency`, `payment_method` (bank/cash/cheque), `effective_from`/`effective_to`, `is_active` |
| `payroll_runs` | مسير تنفيذ لفترة: `status`, `created_by`, `approved_by`/`approved_at`, `notes` |

## PayrollService

| الدالة | الوظيفة |
|--------|---------|
| `createPeriod($data, ...)` | إنشاء فترة (فريدة لكل سنة/شهر/فرع) |
| `setSalaryProfile($employee, $data, ...)` | ضبط ملف راتب نشط جديد **مع أرشفة السابق** (لا حذف — لسلامة الـ snapshot) |
| `createRun($period, $data, ...)` | إنشاء مسير مسودة لفترة |

## نقاط النهاية والصلاحيات

| الطريقة | المسار | الصلاحية |
|---------|--------|----------|
| GET | `/api/payroll-periods` (+ `{id}`) | `payroll.view` |
| POST | `/api/payroll-periods` | `payroll.create` |
| GET | `/api/payroll-periods/{id}/runs` · `/api/payroll-runs/{id}` | `payroll.view` |
| POST | `/api/payroll-periods/{id}/runs` | `payroll.create` |
| GET | `/api/employees/{employee}/salary-profiles` | `payroll.view` |
| POST | `/api/employees/{employee}/salary-profiles` | `payroll.create` |

## سجل التدقيق (Audit)

`payroll_period_created` · `salary_profile_set` · `payroll_run_created`

## مبادئ ثابتة عبر الـ Epic

- **Snapshot:** تغيّر الراتب يُنشئ ملفاً جديداً ويُبقي القديم؛ الكشوف لاحقاً تُحفَظ كـ snapshot فلا تتغيّر بأثر رجعي.
- **قراءة فقط** من الحضور/الإجازات (لا كتابة فيهما).
- **Audit** لكل عملية مالية (من أنشأ/عدّل/اعتمد/قفل).
- عزل بالفرع `branch_id` فقط (لا `tenant_id` — راجع ADR-005).

## خارج نطاق #32

المكوّنات (#33)، دمج الحضور (#34)، دمج الإجازات (#35)، محرك الحساب (#36)، الكشوف والاعتماد (#37)، التقارير (#38). لا Finance/Banking/AI.
