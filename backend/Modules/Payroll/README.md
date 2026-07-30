# وحدة Payroll

**الملكية (Owns):** payroll_periods, employee_salary_profiles, payroll_runs, salary_components, employee_salary_components
**تُتيح (Exposes):** PayrollService, SalaryComponentService

> المرجع: [docs/module-boundaries.md](../../../docs/module-boundaries.md) · Epic #31

**القاعدة الذهبية:** لا تصل هذه الوحدة إلى جداول وحدة أخرى مباشرةً — التواصل عبر الخدمات/الأحداث فقط. تقرأ من الحضور/الإجازات (لاحقاً) دون الكتابة فيها.

الحالة: **الأساس (#32) + مكوّنات الراتب (#33) جاهزان.** الحساب/الكشوف في #34–#38.

## الجداول (Migrations)

| الجدول | الوصف |
|--------|-------|
| `payroll_periods` | فترة شهرية: `year`, `month`, `status` (draft/processing/approved/paid), `branch_id`. فريد `(year, month, branch_id)` |
| `employee_salary_profiles` | ملف راتب **تاريخي**: `basic_salary`, `currency`, `payment_method` (bank/cash/cheque), `effective_from`/`effective_to`, `is_active` |
| `payroll_runs` | مسير تنفيذ لفترة: `status`, `created_by`, `approved_by`/`approved_at`, `notes` |
| `salary_components` | كتالوج المكوّنات (#33): `code` (فريد), `type` (allowance/deduction), `value_type` (fixed/percentage), `is_active` |
| `employee_salary_components` | إسناد مكوّن لموظف (#33): `value`, `effective_from`/`effective_to`, `is_active` — تاريخي |

## PayrollService

| الدالة | الوظيفة |
|--------|---------|
| `createPeriod($data, ...)` | إنشاء فترة (فريدة لكل سنة/شهر/فرع) |
| `setSalaryProfile($employee, $data, ...)` | ضبط ملف راتب نشط جديد **مع أرشفة السابق** (لا حذف — لسلامة الـ snapshot) |
| `createRun($period, $data, ...)` | إنشاء مسير مسودة لفترة |

## SalaryComponentService (#33)

| الدالة | الوظيفة |
|--------|---------|
| `assign($employee, $component, $data, ...)` | إسناد مكوّن نشط للموظف بقيمة/فعالية **مع أرشفة السابق لنفس المكوّن** |
| `deactivate($assignment, ...)` | إيقاف إسناد (أرشفة بنهاية فعالية — لا حذف) |

- مكوّنات مختلفة (سكن/مواصلات) **تتعايش نشطة** معاً؛ إعادة إسناد **نفس** المكوّن تؤرشف نسخته السابقة.
- `value` تُفسَّر حسب `value_type` للمكوّن (مبلغ ثابت أو نسبة من الأساسي) — **التفسير في الحساب #36**، لا حساب هنا.

## نقاط النهاية والصلاحيات

| الطريقة | المسار | الصلاحية |
|---------|--------|----------|
| GET | `/api/payroll-periods` (+ `{id}`) | `payroll.view` |
| POST | `/api/payroll-periods` | `payroll.create` |
| GET | `/api/payroll-periods/{id}/runs` · `/api/payroll-runs/{id}` | `payroll.view` |
| POST | `/api/payroll-periods/{id}/runs` | `payroll.create` |
| GET | `/api/employees/{employee}/salary-profiles` | `payroll.view` |
| POST | `/api/employees/{employee}/salary-profiles` | `payroll.create` |
| GET | `/api/salary-components` | `payroll.view` |
| POST/PUT | `/api/salary-components[...]` | `payroll.create` |
| GET | `/api/employees/{employee}/salary-components` | `payroll.view` |
| POST | `/api/employees/{employee}/salary-components` | `payroll.create` |
| DELETE | `/api/employee-salary-components/{id}` (إيقاف) | `payroll.create` |

## سجل التدقيق (Audit)

`payroll_period_created` · `salary_profile_set` · `payroll_run_created` · `salary_component_created` · `salary_component_updated` · `employee_component_assigned` · `employee_component_deactivated`

## مبادئ ثابتة عبر الـ Epic

- **Snapshot:** تغيّر الراتب يُنشئ ملفاً جديداً ويُبقي القديم؛ الكشوف لاحقاً تُحفَظ كـ snapshot فلا تتغيّر بأثر رجعي.
- **قراءة فقط** من الحضور/الإجازات (لا كتابة فيهما).
- **Audit** لكل عملية مالية (من أنشأ/عدّل/اعتمد/قفل).
- عزل بالفرع `branch_id` فقط (لا `tenant_id` — راجع ADR-005).

## خارج النطاق الحالي

دمج الحضور (#34)، دمج الإجازات (#35)، محرك الحساب (#36 — تفسير fixed/percentage وحساب الصافي)، الكشوف والاعتماد (#37)، التقارير (#38). لا Finance/Banking/AI.
