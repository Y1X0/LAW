# وحدة Payroll

**الملكية (Owns):** payroll_periods, employee_salary_profiles, payroll_runs, salary_components, employee_salary_components, payroll_attendance_summaries, payroll_leave_summaries, payroll_items
**تُتيح (Exposes):** PayrollService, SalaryComponentService, PayrollAttendanceService, PayrollLeaveService, PayrollCalculationService, PayrollApprovalService, PayslipService, PayrollReportService

> المرجع: [docs/module-boundaries.md](../../../docs/module-boundaries.md) · Epic #31

**القاعدة الذهبية:** لا تصل هذه الوحدة إلى جداول وحدة أخرى مباشرةً — التواصل عبر الخدمات/الأحداث فقط. تقرأ من الحضور/الإجازات **قراءةً فقط** ولا تكتب فيهما.

الحالة: **الأساس (#32) + المكوّنات (#33) + الحضور (#34) + الإجازات (#35) + الحساب (#36) + الكشف والاعتماد (#37) + التقارير (#38) جاهزة.** Epic Payroll (#31) مكتمل.

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
| `payroll_items` | **نتيجة الحساب النهائية** لموظف ضمن مسير (#36): `basic_salary`, `allowances_total`, `deductions_total`, `gross_amount`, `net_amount`, `breakdown` (JSONB) + **لقطة الموقع التنظيمي** `branch_id`/`department_id` مجمّدة لحظة الحساب (#38، بلا FK لحفظ التاريخ). فريد `(payroll_run_id, employee_id)` |

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

## PayrollCalculationService (#36) — محرّك الحساب

يجمع كل المدخلات → **Gross / Deductions / Net** ويحفظ لقطة نهائية (`payroll_items`) مع تفصيل البنود (`breakdown`).

| المُدخَل | المصدر | التأثير |
|---------|--------|---------|
| الراتب الأساسي | `employee_salary_profiles` النشط | أساس الحساب |
| البدلات | `employee_salary_components` (allowance) | + (ثابت أو % من الأساسي) |
| الخصومات اليدوية | `employee_salary_components` (deduction) | − |
| الغياب/التأخير/الإضافي | **لقطة الحضور #34** (لا حضور حيّ) | −غياب، −تأخير، +إضافي |
| الإجازة بدون راتب | **لقطة الإجازات #35** | − (المدفوعة لا تُخصم) |

**قواعد المعدّلات (موثّقة، قابلة للضبط):** اليومي = الأساسي ÷ 30 · الساعة = اليومي ÷ 8 · الدقيقة = الساعة ÷ 60 · الإضافي = الساعة × 1.5 × ساعات الإضافي.
`gross = basic + allowances` · `net = gross − deductions` (تقريب لخانتين).

- **قراءة فقط** من اللقطات المجمّدة (#34/#35) والمكوّنات — لا يقرأ الحضور/الإجازات حيّاً ولا يعدّل أي مصدر (اختبار حارس).
- **غير قابل لإعادة الحساب بعد الاعتماد:** يُرفض على مسير `approved/paid` (422)؛ idempotent على المسودة.
- كل نتيجة تُحفظ كـ **snapshot نهائي** مع تفصيل قابل للتدقيق والكشف (#37).

## الاعتماد والكشف (#37)

**دورة المسير:** `draft/processing` (بعد الحساب #36 تُنتَج النتائج) → **approve** (`payroll.approve`) → `approved` → **lock** (`payroll.pay`) → `locked` (نهائي، غير قابل للتغيير).

- **PayrollApprovalService:** `approve` (يشترط وجود نتائج محسوبة) · `lock` (يشترط الاعتماد أولاً). بعد `approved/locked` تُرفض إعادة الحساب/اللقطات تلقائياً (الحُرّاس السابقة). لا يمسّ منطق الحساب.
- **PayslipService:** كشف راتب من `payroll_item` المجمّد (لا يعيد الحساب) — **JSON** + **مستند HTML مكتفٍ ذاتياً** للطباعة → PDF من المتصفح. **لا مكتبة PDF / لا تعديل composer** (تصدير PDF رسمي بشعار/توقيع = Issue مستقل لاحقاً).
- تدقيق: `payroll_run_approved` · `payroll_run_locked` · `payslip_exported`.

## التقارير (#38) — PayrollReportService

تقارير مالية للرواتب **من النتائج المجمّدة فقط** (`payroll_items` ← `payroll_runs` ← `payroll_periods` — كلها مملوكة لـ Payroll).

| الدالة | الوظيفة |
|--------|---------|
| `costReport($filters)` | تكلفة الرواتب: إجماليات (headcount / basic / allowances / deductions / gross / net) + تفصيل مجمّع حسب `group_by` ∈ {branch, department, month} |
| `employeeReport($employee, $filters)` | تاريخ رواتب موظف عبر المسيّرات + إجماليات |

- **الفلاتر:** `year` · `month` · `branch_id` · `department_id` · `status` (للتكلفة) · `year`/`status` (للموظف).
- **النزاهة التاريخية (المبدأ الحاكم):** لا يقرأ إطلاقاً من `attendance_records` / `leave_requests` / `employee_salary_components` الحيّة ولا من ملفات الرواتب النشطة. الموقع التنظيمي (`branch_id`/`department_id`) **مجمّد على العنصر** لحظة الحساب، فتبقى تقارير المسيّرات المقفولة ثابتة حتى لو انتقل الموظف أو تغيّر راتبه لاحقاً (اختبار حارس).
- **عزل بالفرع:** تمرير `branch_id` يقصر الإجماليات/المجموعات على الفرع (لا تسرّب).
- **تجميعات محمولة:** `SUM`/`COUNT(DISTINCT)` عبر SQLite/PostgreSQL. تسمية الفرع/القسم عبر نماذجها (قراءة هوية، دفعة واحدة).
- **تدقيق كل عرض حسّاس:** `payroll_cost_report_viewed` · `payroll_employee_report_viewed`.
- **خارج نطاق #38:** تصدير Excel/PDF ولوحات المؤشرات = Issue مستقل لاحقاً.

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
| POST | `/api/payroll-runs/{id}/calculate` | `payroll.create` |
| GET | `/api/payroll-runs/{id}/items` · `/api/payroll-items/{id}` | `payroll.view` |
| POST | `/api/payroll-runs/{id}/approve` | `payroll.approve` |
| POST | `/api/payroll-runs/{id}/lock` | `payroll.pay` |
| GET | `/api/payroll-runs/{id}/payslips` | `payroll.view` |
| GET | `/api/payroll-items/{id}/payslip` (JSON) · `/payslip/html` (طباعة) | `payroll.view` |
| GET | `/api/payroll-reports/cost?year=&month=&branch_id=&department_id=&status=&group_by=` | `payroll.view` |
| GET | `/api/payroll-reports/employees/{employee}?year=&status=` | `payroll.view` |

## سجل التدقيق (Audit)

`payroll_period_created` · `salary_profile_set` · `payroll_run_created` · `salary_component_created` · `salary_component_updated` · `employee_component_assigned` · `employee_component_deactivated` · `payroll_attendance_snapshotted` · `payroll_leave_snapshotted` · `payroll_calculated` · `payroll_run_approved` · `payroll_run_locked` · `payslip_exported` · `payroll_cost_report_viewed` · `payroll_employee_report_viewed`

## مبادئ ثابتة عبر الـ Epic

- **Snapshot:** تغيّر الراتب يُنشئ ملفاً جديداً ويُبقي القديم؛ الكشوف لاحقاً تُحفَظ كـ snapshot فلا تتغيّر بأثر رجعي.
- **قراءة فقط** من الحضور/الإجازات (لا كتابة فيهما).
- **Audit** لكل عملية مالية (من أنشأ/عدّل/اعتمد/قفل).
- عزل بالفرع `branch_id` فقط (لا `tenant_id` — راجع ADR-005).

## خارج النطاق الحالي

Epic Payroll (#31) مكتمل. لا Finance/Banking/AI. مؤجّل كـ Issues مستقلة لاحقاً: تصدير Excel/PDF رسمي للتقارير والكشوف (شعار/توقيع) · لوحات المؤشرات · بوابة الخدمة الذاتية للموظف (`payslip.view_own`) · تسوية الإجازات↔الحضور.
