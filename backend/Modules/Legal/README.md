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

## قادم (Epic #69)
LC-2 القضايا + الإسناد + عزل `view_own` · LC-3 الجلسات · LC-4 الخط الزمني والمستندات · LC-5 المهام والإنجازات اليومية.
