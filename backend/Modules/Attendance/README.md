# وحدة Attendance

**الملكية (Owns):** attendance_records, work_shifts, employee_shifts, biometric_devices, attendance_logs
**تُتيح (Exposes):** AttendanceService, BiometricIngestionService, BiometricAdapter (واجهة موحّدة)

> المرجع: [docs/module-boundaries.md](../../../docs/module-boundaries.md)

**القاعدة الذهبية:** لا تصل هذه الوحدة إلى جداول وحدة أخرى مباشرةً — التواصل عبر الخدمات/الأحداث فقط.

الحالة: **محرّك الحضور الأساسي (Issue #15) + أساس تكامل البصمة (Issue #16) جاهزان.**

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

**الحضور (#15):** `attendance_check_in` · `attendance_check_out` · `attendance_manual_recorded` · `attendance_approved` · `work_shift_created`

**البصمة (#16):** `biometric_device_registered` · `biometric_device_updated` · `biometric_user_enrolled` · `biometric_sync_triggered` · `biometric_device_synced` · `biometric_log_unmatched` · `biometric_log_processed`

---

# تكامل البصمة (Issue #16)

أساس تكامل أجهزة البصمة عبر **نمط Adapter** (ADR-003) + طبقة **Staging خام** + بنية **مهمة مزامنة**. يدعم MVP مورّد **ZKTeco** بالكامل (Push أساسي + Pull للتسوية).

## الجداول

| الجدول | الوصف |
|--------|-------|
| `biometric_devices` | الأجهزة: `vendor`, `api_mode` (push/pull), `ip_address`, `port`, `serial_number`, `auth_token` (مشفّر), `status`, `last_sync_at`, `is_active` |
| `attendance_logs` | Staging خام: `device_id`, `biometric_user_id`, `employee_id`, `punch_time`, `punch_type`, `verify_mode`, `source`, `status`, `raw_payload` (JSONB) |

**منع التكرار (Idempotency):** `unique(device_id, biometric_user_id, punch_time)` — أي إعادة إرسال تُتجاهل.

## طبقة المحوّلات (Adapter Layer)

- `Biometric\Contracts\BiometricAdapter` — الواجهة الموحّدة: `connect` / `fetchLogs` / `parseWebhook` / `normalize` / `enrollUser` / `deleteUser` / `getDeviceStatus`.
- `Biometric\Adapters\ZKTecoAdapter` — محوّل ZKTeco (تعيينات status/verify؛ Push عبر normalize، Pull عبر fetchLogs).
- `Biometric\BiometricAdapterFactory` — يختار المحوّل حسب `vendor` (إضافة مورّد = محوّل + فرع جديد).
- `Biometric\PunchData` — النبضة الموحّدة `{biometric_user_id, punch_time, punch_type, verify_mode, raw}`.

## خط الاستيعاب (Ingestion) والمعالجة

1. **Push:** الجهاز يرسل إلى `POST /api/biometric/devices/{device}/webhook` (يُصادَق بتوكن الجهاز) → `parseWebhook` → استيعاب.
2. **Pull/التسوية:** `biometric:sync` (كل دقيقة) يطلق `SyncBiometricDeviceJob` لكل جهاز نشط → `fetchLogs(since=last_sync_at)`.
3. **BiometricIngestionService:** منع التكرار → حفظ خام في `attendance_logs` → مطابقة الموظف بـ `biometric_user_id`.
4. **المطابَق:** `ProcessAttendanceLogJob` (طابور + إعادة محاولة) → `AttendanceService::applyPunch` → `attendance_records` بمصدر `biometric` (أول دخول/آخر خروج).
5. **غير المطابَق:** يبقى `status=unmatched` (قائمة عمل HR عبر `GET /api/biometric/logs?status=unmatched`) + أثر تدقيق `biometric_log_unmatched`.

## نقاط النهاية (البصمة)

| الطريقة | المسار | الصلاحية |
|---------|--------|----------|
| POST | `/api/biometric/devices/{device}/webhook` | توكن الجهاز (Push) |
| GET/POST | `/api/biometric/devices` | `attendance.devices` |
| PUT | `/api/biometric/devices/{device}` | `attendance.devices` |
| POST | `/api/biometric/devices/{device}/enroll` | `attendance.devices` |
| POST | `/api/biometric/devices/{device}/sync` | `attendance.devices` |
| GET | `/api/biometric/logs` | `attendance.devices` |
