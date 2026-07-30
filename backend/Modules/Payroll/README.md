# وحدة Payroll

**الملكية (Owns):** payroll_periods, employee_salary_profiles, payroll_runs, salary_components, employee_salary_components, payroll_attendance_summaries, payroll_leave_summaries
**تُتيح (Exposes):** PayrollService, SalaryComponentService, PayrollAttendanceService, PayrollLeaveService

> المرجع: [docs/module-boundaries.md](../../../docs/module-boundaries.md) · Epic #31

**القاعدة الذهبية:** لا تصل هذه الوحدة إلى جداول وحدة أخرى مباشرةً — التواصل عبر الخدمات/الأحداث فقط. تقرأ من الحضور **قراءةً فقط** ولا تكتب فيه.

الحالة: **الأساس (#32) + المكوّنات (#33) + تكامل الحضور (#34) + تكامل الإجازات (#35) جاهزة.** الحساب/الكشوف في #36–#38.

## الجداول (Migrations)

| الجدول | الوصف |
|--------|-------|
| `payroll_periods` | فترة شهرية: `year`, `month`, `status` (draft/processing/approved/paid), `branch_id`. فريد `(year, month, branch_id)` |
| `employee_salary_profiles` | ملف راتب **تاريخي**: `basic_salary`, `currency`, `payment_method` (bank/cash/cheque), `effective_from`/`effective_to`, `is_active` |
| `payroll_runs` | مسير تنفيذ لفترة: `status`, `created_by`, `approved_by`/`approved_at`, `notes` |
| `salary_components` | كتالوج المكوّنات (#33): `code` (فريد), `type` (allowance/deduction), `value_type` (fixed/percentage), `is_active` |
| `employee_salary_components` | إسناد مكوّن لموظف (#33): `value`, `effective_from`/`effective_to`, `is_active` — تاريخي |
| `payroll_attendance_summaries` | **لقطة (Snapshot)** ملخّص الحضور الشهري لموظف ضمن مسير (#34): `total_work_days`, `absent_days`, `worked/late/early_leave/overtime_minutes`. فريد `(payroll_run_id, employee_id)` |
| `payroll_leave_summaries` | **لقطة (Snapshot)** ملخّص الإجازات الشهري لموظف ضمن مسير (#35): `paid_leave_days`, `unpaid_leave_days`. فريد `(payroll_run_id, employee_id)` |

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

## PayrollAttendanceService (#34) — قراءة فقط من الحضور

| الدالة | الوظيفة |
|--------|---------|
| `summarize($employee, $year, $month)` | ملخّص حضور شهري (قراءة فقط، بلا حفظ): أيام العمل/الغياب ودقائق التأخير/المغادرة/الإضافي |
| `snapshotEmployee($run, $employee, ...)` | حفظ لقطة الموظف ضمن مسير (idempotent لكل run+employee) |
| `snapshotRun($run, ...)` | لقطة لكل موظفي المسير (أصحاب ملف راتب نشط، ضمن فرع الفترة إن حُدّد) |

- **قراءة فقط** من `attendance_records` — لا كتابة/تعديل في وحدة الحضور.
- **Snapshot يثبّت التاريخ:** تغيّر الحضور لاحقاً لا يغيّر لقطة محفوظة؛ إعادة اللقطة تُحدّثها **فقط والمسير مسودة/قيد المعالجة** — يُرفض إعادة لقطة مسير معتمد/مقفل (422).
- الموظفون: أصحاب ملف راتب نشط فقط، وضمن فرع الفترة إن حُدّد (عزل بالفرع).
- `total_work_days` = أيام الحالات present/late/early_leave. `overtime_hours` = الدقائق ÷ 60.

## PayrollLeaveService (#35) — قراءة فقط من الإجازات

| الدالة | الوظيفة |
|--------|---------|
| `summarize($employee, $year, $month)` | ملخّص إجازات شهري (قراءة فقط): `paid_leave_days`, `unpaid_leave_days` من الإجازات **المعتمدة** المتداخلة مع الشهر |
| `snapshotEmployee` / `snapshotRun` | حفظ اللقطة (idempotent؛ يُرفض على مسير معتمد/مقفل 422؛ عزل بالفرع) |

- **قراءة فقط** من `leave_requests` — لا كتابة/تعديل في وحدة الإجازات (اختبار حارس).
- الإجازات **المعتمدة فقط**؛ تُحتسب أيام العمل ضمن **تقاطع** فترة الإجازة مع الشهر (استبعاد الويكند).
- التمييز مدفوعة/بدون راتب حسب `leave_types.is_paid`. **تحويلها إلى خصم في #36.**

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
| GET | `/api/employees/{employee}/attendance-summary?year=&month=` (معاينة) | `payroll.view` |
| GET | `/api/payroll-runs/{id}/attendance-summaries` | `payroll.view` |
| POST | `/api/payroll-runs/{id}/attendance-snapshot` | `payroll.create` |
| GET | `/api/employees/{employee}/leave-summary?year=&month=` (معاينة) | `payroll.view` |
| GET | `/api/payroll-runs/{id}/leave-summaries` | `payroll.view` |
| POST | `/api/payroll-runs/{id}/leave-snapshot` | `payroll.create` |

## سجل التدقيق (Audit)

`payroll_period_created` · `salary_profile_set` · `payroll_run_created` · `salary_component_created` · `salary_component_updated` · `employee_component_assigned` · `employee_component_deactivated` · `payroll_attendance_snapshotted` · `payroll_leave_snapshotted`

## مبادئ ثابتة عبر الـ Epic

- **Snapshot:** تغيّر الراتب يُنشئ ملفاً جديداً ويُبقي القديم؛ الكشوف لاحقاً تُحفَظ كـ snapshot فلا تتغيّر بأثر رجعي.
- **قراءة فقط** من الحضور/الإجازات (لا كتابة فيهما).
- **Audit** لكل عملية مالية (من أنشأ/عدّل/اعتمد/قفل).
- عزل بالفرع `branch_id` فقط (لا `tenant_id` — راجع ADR-005).

## خارج النطاق الحالي

محرك الحساب (#36 — تفسير fixed/percentage وتحويل ملخّصي الحضور والإجازات إلى خصومات/بدلات وحساب الصافي)، الكشوف والاعتماد (#37)، التقارير (#38). لا Finance/Banking/AI.
