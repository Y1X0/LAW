# وحدة Attendance

**الملكية (Owns):** attendance_records, work_shifts, employee_shifts (biometric_devices لاحقاً في #16)
**تُتيح (Exposes):** AttendanceService

> المرجع: [docs/module-boundaries.md](../../../docs/module-boundaries.md)

**القاعدة الذهبية:** لا تصل هذه الوحدة إلى جداول وحدة أخرى مباشرةً — التواصل عبر الخدمات/الأحداث فقط.

الحالة: **محرّك الحضور الأساسي جاهز (Issue #15).** تكامل أجهزة البصمة لاحقاً في #16 (`source=manual` حالياً).

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

## خارج نطاق #15

تكامل أجهزة البصمة (ZKTeco/Hikvision/…)، المزامنة مع الأجهزة، والتقاط `source` الآلي — كلها في Issue #16.
