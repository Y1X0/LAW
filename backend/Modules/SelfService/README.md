# وحدة SelfService (Epic 9)

**الملكية (Owns):** لا تملك جداول — سطح الخدمة الذاتية للموظف تحت `/api/me`.
**تُتيح (Exposes):** MyDashboardService, MyPayslipService, MyAttendanceService, MyLeaveService (+ تعديل الملف عبر HR::EmployeeService)

> المرجع: [docs/module-boundaries.md](../../../docs/module-boundaries.md) · Epic #46

**القاعدة الذهبية:** تقرأ من الوحدات الأخرى **قراءةً فقط** (عبر نماذجها) — لا تكتب فيها ولا تملك منطقاً مالياً/تنظيمياً. كل استعلام مقيّد بموظف المستخدم الحالي.

## المبدأ الحاكم
- الوصول دائماً من `Auth::user()->employee` (أساس #47).
- الحارس `employee.linked` يمنع مستخدماً بلا موظف مرتبط (403).
- صلاحية `*_own` مستقلة لكل ميزة. **لا وصول لبيانات موظف آخر** (العزل بالـ employee_id في كل استعلام).

## My Dashboard (#48) — `MyDashboardService`

`GET /api/me/dashboard` (`dashboard.view_own`) — ملخّص شخصي **للعرض فقط**:

| القسم | المصدر (قراءة فقط) |
|-------|--------------------|
| `profile` | HR: الاسم/الرقم/الوظيفة/الفرع/القسم/المدير |
| `leave_balance` | Leave: `LeaveBalance` للسنة الحالية (إجمالي متبقٍّ + تفصيل) |
| `attendance_today` | Attendance: `AttendanceRecord` لليوم (أو null) |
| `last_payslip` | Payroll: آخر `PayrollItem` من مسير **نهائي** (approved/paid/locked) — لا مسوّدات |

- صفر كتابة · صفر منطق جديد · صفر تسريب لموظف آخر.

## My Payslips (#49) — `MyPayslipService`

كشوف الموظف الذاتية — يعيد استخدام `PayslipService` (#37) بلا إعادة حساب:

- قائمة/عرض كشوفي من مسيّرات **نهائية فقط** (approved/paid/locked) — لا مسوّدات.
- **العزل:** كل كشف يُتحقَّق أنه يخصّ الموظف الحالي (`ownedItem`) — كشف موظف آخر → **403**.
- تدقيق تصدير الكشف الذاتي: `payslip_self_exported`.

## My Attendance (#50) — `MyAttendanceService`
`GET /api/me/attendance?from=&to=` (`attendance.view_own`) — سجلّ حضوري **قراءةً فقط** من `attendance_records` (لا API تعديل). عزل بالـ employee_id.

## My Leave (#51) — `MyLeaveService`
- `GET /api/me/leave/balance` · `/requests` (`leave.view_own`) — رصيدي وطلباتي.
- `POST /api/me/leave/requests` (`leave.request_own` — مستقلة عن العرض) — تقديم طلب **لنفسي حصراً**، يعيد استخدام `LeaveService` (#17). لا اعتماد/رفض، ولا تقديم لموظف آخر (employee_id مُتجاهَل).

## My Profile (#52) — تعديل عبر HR
- `GET /api/me/profile` · `PATCH /api/me/profile` (`profile.update_own`).
- **تعديل محدود فقط:** الهاتف · العنوان · صورة الملف · اسم/هاتف جهة الطوارئ.
- **ممنوع تماماً:** الراتب · القسم · الفرع · الوظيفة · المدير (دفاع بالطبقات: تحقّق المُدخل + قائمة بيضاء في `EmployeeService::SELF_EDITABLE_FIELDS`).
- الكتابة تمرّ عبر **HR (مالك الجدول)** — `EmployeeService::updateSelfProfile` مع تدقيق `employee_profile_self_updated`.

## نقاط النهاية

| الطريقة | المسار | الحارس + الصلاحية |
|---------|--------|-------------------|
| GET | `/api/me/dashboard` | `auth.token` + `employee.linked` + `dashboard.view_own` |
| GET | `/api/me/payslips` (+ `{id}` JSON/HTML) | `… + payslip.view_own` |
| GET | `/api/me/attendance?from=&to=` | `… + attendance.view_own` |
| GET | `/api/me/leave/balance` · `/leave/requests` | `… + leave.view_own` |
| POST | `/api/me/leave/requests` | `… + leave.request_own` |
| GET/PATCH | `/api/me/profile` | `… + profile.update_own` |

## التدقيق
`payslip_self_exported` · `employee_profile_self_updated` (+ أحداث LeaveService عند التقديم).

## خارج النطاق الحالي
لا لوحة إدارة (وحدة Dashboard المنفصلة، #18). SelfService لا يكتب في أي وحدة عدا التعديل الذاتي المحدود للملف عبر خدمة HR.
