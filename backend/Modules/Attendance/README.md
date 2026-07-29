# وحدة Attendance

**الملكية (Owns):** attendance_records, work_shifts, employee_shifts, biometric_devices, attendance_logs
**تُتيح (Exposes):** AttendanceService, BiometricSyncService, BiometricAdapter (+ Manager)

> المرجع: [docs/module-boundaries.md](../../../docs/module-boundaries.md) · [ADR-003](../../../docs/adr/003-attendance-adapter-pattern.md)

**القاعدة الذهبية:** لا تصل هذه الوحدة إلى جداول وحدة أخرى مباشرةً — التواصل عبر الخدمات/الأحداث فقط.

الحالة: **محرّك الحضور (Issue #15) + تكامل أجهزة البصمة (Issue #16) جاهزان.**

## الجداول (Migrations)

| الجدول | الوصف |
|--------|-------|
| `work_shifts` | نوبات العمل: `name`, `start_time`, `end_time`, `grace_minutes` (سماح), `weekdays` (JSON), `branch_id`, `is_active` |
| `employee_shifts` | ربط الموظف بنوبة ضمن فترة: `employee_id`, `shift_id`, `from_date`, `to_date` |
| `attendance_records` | سجل الحضور اليومي (فريد لكل موظف/يوم): `work_date`, `check_in`, `check_out`, `worked_minutes`, `late_minutes`, `early_leave_minutes`, `overtime_minutes`, `status`, `source`, `shift_id`, `approved_by`, `approved_at` |

قيد التفرّد: `unique(employee_id, work_date)` — سجل واحد لكل موظف في اليوم.

## الحالات (Statuses)

`present` · `absent` · `late` · `early_leave` · `leave` · `holiday` · `weekend`

## AttendanceService

| الدالة | الوظيفة |
|--------|---------|
| `resolveShift($employee, $workDate)` | النوبة النشطة للموظف في تاريخ معيّن |
| `checkIn($employee, $time, ...)` | تسجيل دخول واحتساب التأخير مقابل نافذة السماح |
| `checkOut($employee, $time, ...)` | تسجيل خروج واحتساب ساعات العمل والمغادرة المبكرة والإضافي |
| `storeManual($employee, $data, ...)` | تسجيل/تعديل يدوي (يبقى معلّقاً حتى الاعتماد) |
| `approve($record, ...)` | اعتماد سجل حضور |

**منطق الاحتساب:**
- التأخير: `check_in` بعد (`start_time` + `grace_minutes`) → عدد الدقائق الزائدة، والحالة `late`.
- ساعات العمل: الفرق بالدقائق بين `check_in` و`check_out`.
- المغادرة المبكرة: `check_out` قبل `end_time`.
- الإضافي: `check_out` بعد `end_time`.

## نقاط النهاية (Endpoints)

| الطريقة | المسار | الصلاحية |
|---------|--------|----------|
| GET | `/api/attendance` | `attendance.view` |
| GET | `/api/work-shifts` | `attendance.view` |
| POST | `/api/attendance/check-in` | `attendance.manual` |
| POST | `/api/attendance/check-out` | `attendance.manual` |
| POST | `/api/attendance/manual` | `attendance.manual` |
| POST | `/api/work-shifts` | `attendance.manual` |
| POST | `/api/employees/{employee}/shifts` | `attendance.manual` |
| POST | `/api/attendance/{record}/approve` | `attendance.approve` |

## سجل التدقيق (Audit)

`attendance_check_in` · `attendance_check_out` · `attendance_manual_recorded` · `attendance_approved` · `work_shift_created`

---

# تكامل أجهزة البصمة (Issue #16)

نمط **Adapter** (ADR-003): واجهة موحّدة لكل مورّد، وطبقة **Staging خام** (`attendance_logs`) تضمن عدم فقدان أي بصمة قبل معالجتها إلى `attendance_records` بمصدر `source=biometric`.

## الجداول

| الجدول | الوصف |
|--------|-------|
| `biometric_devices` | الأجهزة المسجّلة: `vendor` (zkteco/hikvision/suprema/anviz), `api_mode` (push/pull), `secret`, `last_sync_at`, `status` |
| `attendance_logs` | Staging خام لكل نبضة: `raw_payload` (JSONB), `punch_time`, `punch_type`, `status` (pending/processed/unmatched) |

**Idempotency:** فهرس فريد منطقي `(device_id, biometric_user_id, punch_time)` — إعادة الإرسال تُتجاهَل.

## المحوّلات (Adapters)

- `BiometricAdapter` — واجهة موحّدة: `connect` · `fetchLogs` (Pull) · `parseWebhook` (Push) · `normalize` · `enrollUser`.
- `ZktecoAdapter` — يفسّر JSON (`records`) ونص ATTLOG؛ حالة 0=دخول، 1=خروج.
- `BiometricAdapterManager` — سجلّ (singleton) يحلّ المحوّل حسب المورّد؛ إضافة مورّد = محوّل جديد فقط (`extend`).

## المزامنة (BiometricSyncService)

- **Push/Webhook**: `POST /api/biometric/devices/{device}/webhook` (تحقق بمفتاح الجهاز، بلا مصادقة مستخدم) → تفسير + Staging + معالجة.
- **Pull/Reconciliation**: `POST /api/biometric/devices/{device}/sync` (يدوي) — يسحب منذ `last_sync_at` كشبكة أمان.
- **بنية المهمة (Queue)**: `PullBiometricDeviceJob` (طابور، `tries=3`، تراجع أُسّي 10/30/60ث) يُرسَل عبر الأمر `attendance:sync-biometric` المُجدوَل كل 5 دقائق (`routes/console.php`).
- **المطابقة**: `attendance_logs.biometric_user_id` → `employees.biometric_user_id`؛ غير المطابَق يُعلَّم `unmatched` (لا يُفقد) مع تنبيه HR في السجل (`Log::warning`).
- **التطبيق**: أول نبضة = دخول، اللاحقة = خروج، عبر `AttendanceService` بمصدر `biometric`.

## نقاط النهاية والصلاحيات

| الطريقة | المسار | الصلاحية |
|---------|--------|----------|
| GET/POST/PUT/DELETE | `/api/biometric/devices[...]` | `attendance.devices` |
| POST | `/api/biometric/devices/{device}/sync` | `attendance.devices` |
| POST | `/api/biometric/devices/{device}/webhook` | مفتاح الجهاز (`X-Device-Secret`) |

## التدقيق (Audit)

`biometric_device_created` · `biometric_device_updated` · `biometric_device_deleted` · `biometric_webhook_received` · `biometric_pulled`

## أمان

- التحقق من Push بمقارنة ثابتة الزمن (`hash_equals`) لمفتاح الجهاز؛ يُفضّل تقييد المصدر بـ IP Allowlist/VPN على مستوى البنية.
- المفتاح السري (`secret`) مخفيّ من كل استجابات الـ API.

## خارج نطاق #16

طبقة النقل الفعلية عبر SDK كل مورّد (Pull الحقيقي + `enrollUser` — الواجهة معرّفة والتوصيل لكل بيئة نشر)، ومحوّلات المورّدين الآخرين (Hikvision/Suprema/Anviz). تنبيه HR الحالي عبر السجل؛ التكامل مع وحدة الإشعارات لاحقاً.
