# وحدة Leave

**الملكية (Owns):** leave_types, leave_balances, leave_requests
**تُتيح (Exposes):** LeaveService · حدثا LeaveApproved / LeaveCancelled

> المرجع: [docs/module-boundaries.md](../../../docs/module-boundaries.md) · [docs/03 أ-2](../../../docs/03-functional-modules.md) · [docs/10 §2.2](../../../docs/10-traceability-and-edge-cases.md)

**القاعدة الذهبية:** لا تصل هذه الوحدة إلى جداول وحدة أخرى مباشرةً — التواصل عبر الخدمات/الأحداث فقط.

الحالة: **إدارة الإجازات جاهزة (Issue #17).**

## الجداول (Migrations)

| الجدول | الوصف |
|--------|-------|
| `leave_types` | الأنواع وقواعدها: `code` (annual/sick/emergency/unpaid), `is_paid`, `consumes_balance`, `requires_attachment`, `default_annual_days`, `max_consecutive_days` |
| `leave_balances` | الرصيد لكل (موظف، نوع، سنة): `entitled_days`, `consumed_days`؛ **المتبقي محسوب**. فريد `(employee_id, leave_type_id, year)` |
| `leave_requests` | الطلبات: `start_date`, `end_date`, `days` (أيام عمل), `status`, `is_escalated`, `approver_id`, `decided_by`, `rejection_reason` |

## الحالات (Statuses)

`pending` · `approved` · `rejected` · `cancelled`

## LeaveService

| الدالة | الوظيفة |
|--------|---------|
| `request($employee, $data, ...)` | تقديم طلب مع كل عمليات التحقق |
| `approve($request, ...)` | اعتماد + خصم الرصيد + حدث `LeaveApproved` |
| `reject($request, $reason, ...)` | رفض مع سبب |
| `cancel($request, ...)` | إلغاء + إعادة الرصيد (إن كان معتمداً) + حدث `LeaveCancelled` |
| `workingDays($start, $end)` | عدد أيام العمل (استبعاد الجمعة/السبت) |

## التحقق من التعارض (docs/10 §2.2)

- تاريخ النهاية ≥ البداية، وضمن **سنة واحدة** (الطلب عبر سنتين يُقسَّم لطلبين).
- **مرفق إلزامي** للأنواع التي تتطلبه (مثل المرضية) وإلا يُمنع.
- **تداخل زمني** مع طلب معلّق/معتمد لنفس الموظف → رفض.
- **رصيد كافٍ** للأنواع التي تخصم رصيداً (`unpaid` لا يخصم).
- `max_consecutive_days` إن حُدّد.
- **التصعيد:** طلب موظف لديه مرؤوسون (مدير) يُعلَّم `is_escalated` للمستوى الأعلى.
- أيام العمل تُحتسب باستبعاد عطلة نهاية الأسبوع.

## نقاط النهاية والصلاحيات

| الطريقة | المسار | الصلاحية |
|---------|--------|----------|
| GET | `/api/leave-types` | `leaves.request` |
| POST | `/api/leave-requests` | `leaves.request` |
| GET | `/api/leave-requests` | `leaves.view_all` |
| GET | `/api/employees/{employee}/leave-balances` | `leaves.view_all` |
| POST | `/api/leave-requests/{id}/approve` | `leaves.approve` |
| POST | `/api/leave-requests/{id}/reject` | `leaves.approve` |
| POST | `/api/leave-requests/{id}/cancel` | `leaves.approve` |
| POST/PUT | `/api/leave-types[...]` | `leaves.approve` |
| POST | `/api/employees/{employee}/leave-balances` | `leaves.approve` |

## سجل التدقيق (Audit)

`leave_requested` · `leave_approved` · `leave_rejected` · `leave_cancelled` · `leave_type_created` · `leave_type_updated` · `leave_balance_set`

## تكامل الحضور (نقطة تكامل)

عند الاعتماد/الإلغاء يُطلَق حدث (`LeaveApproved` / `LeaveCancelled`) يحمل الطلب. **تعليم أيام الإجازة في الحضور** (`status=leave`) يتم عبر مستمع في وحدة الحضور (متابعة منفصلة) — احتراماً لحدود الوحدات (لا كتابة مباشرة في جداول الحضور).

## خارج نطاق #17

Payroll، Finance، تكامل الإشعارات (SMTP/SMS)، وربط user↔employee لإلغاء الموظف طلبه بنفسه — كلها خارج النطاق.
