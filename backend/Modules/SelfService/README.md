# وحدة SelfService (Epic 9)

**الملكية (Owns):** لا تملك جداول — سطح الخدمة الذاتية للموظف تحت `/api/me`.
**تُتيح (Exposes):** MyDashboardService, MyPayslipService

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

## نقاط النهاية

| الطريقة | المسار | الحارس + الصلاحية |
|---------|--------|-------------------|
| GET | `/api/me/dashboard` | `auth.token` + `employee.linked` + `dashboard.view_own` |
| GET | `/api/me/payslips` | `… + payslip.view_own` |
| GET | `/api/me/payslips/{payroll_item}` (JSON) · `/html` (طباعة) | `… + payslip.view_own` |

## خارج النطاق الحالي
Attendance/Leave/Profile الذاتية (#50–#52). لا كتابة في أي وحدة. لا لوحة إدارة (تلك وحدة Dashboard المنفصلة، #18).
