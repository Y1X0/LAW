# Modules/Legal

مجال إدارة مكتب المحاماة (Legal Domain) فوق محرك HR/Payroll.

## LC-1 — Clients (العملاء)
- كيان `clients`: أفراد/شركات، بلا حذف فعلي (تعطيل عبر `status`).
- الصلاحيات (موجودة في `RbacSeeder`): `clients.view` · `clients.create` · `clients.update`.
- النقاط:
  - `GET /api/clients` — قائمة (بحث/تصفية بالحالة والنوع، ترقيم؛ تُخفي المعطّلين افتراضياً، `include_inactive=1` لإظهارهم).
  - `GET /api/clients/{id}`
  - `POST /api/clients`
  - `PUT /api/clients/{id}`
  - `PATCH /api/clients/{id}/status` — تفعيل/تعطيل.

## LC-2 — Cases (القضايا)
- `cases`: `internal_number` + `court_case_number` · `client_id` · `responsible_lawyer_id` · `case_type` · `status` (open/pending/closed) · `progress` · `value`.
- `case_assignments` (lead/support) — أساس عزل `view_own`.
- الصلاحيات: `cases.view_own` (المحامي المسند) · `cases.view_all` (الإدارة) · `cases.create` · `cases.update` · `cases.assign` · `cases.close`.
- العزل مشترك عبر `Concerns/AuthorizesCaseAccess`.

## LC-3 — Hearings (الجلسات)
- الرؤية **ترث عزل القضية** (لا صلاحيات عرض مستقلة)؛ الكتابة تحت `hearings.manage`.
- استعلامات مدمجة: `GET /api/hearings?scope=upcoming|past|postponed`.
- التأجيل (`POST /api/hearings/{id}/postpone`) يحفظ القديمة (`postponed` + `postponed_to` + `postponed_reason`) ويُنشئ جلسة `scheduled` جديدة.
- لا يُعدَّل سجل جلسة `held`.

## قادم (Epic #69)
LC-4 الخط الزمني والمستندات · LC-5 المهام والإنجازات اليومية.
