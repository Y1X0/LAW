# وحدة HR

**الملكية (Owns):** employees, departments, contracts, allowances, deductions
**تُتيح (Exposes):** EmployeeService, EmployeeIdentityService

> المرجع: [docs/module-boundaries.md](../../../docs/module-boundaries.md)

**القاعدة الذهبية:** لا تصل هذه الوحدة إلى جداول وحدة أخرى مباشرةً — التواصل عبر الخدمات/الأحداث فقط.

## إدارة الموظفين (Issue #13)
- `Models/Employee` (+ Factory) · `Services/EmployeeService` (create/update/archive + Audit) · `Http/Controllers/EmployeeController` · `Http/Requests/{Store,Update}EmployeeRequest`.
- جدول `employees` (docs/02 §4): يرتبط بالفرع والقسم ومدير مباشر (علاقة ذاتية).
- نقاط النهاية (`/api/employees`، محميّة بـ `employees.*`): `index` (بحث/تصفية/ترقيم) · `show` · `store` · `update` · `destroy` (أرشفة Soft Delete).
- حالات الموظف: `active` / `on_leave` / `suspended` / `terminated`.
- حماية على مستوى الحقل: الراتب/الحساب البنكي يُخفيان إلا بصلاحية `employees.salary.view`.
- Audit: `employee_created` / `employee_updated` / `employee_archived`.

## السجلات الأساسية (Issue #14)
- **المسميات الوظيفية** (`Position`): كتالوج + CRUD (`/api/positions`) + ربط `employees.position_id`.
- **عقود التوظيف** (`EmployeeContract`): سجل/تاريخ عقود لكل موظف (`/api/employees/{id}/contracts`) بحالات active/expired/terminated.
- **مستندات الموظف** (`EmployeeDocument`): بيانات وصفية + مسار + تنبيه انتهاء (`/api/employees/{id}/documents`)، حذف = أرشفة.
- **سجل الموظف** (History): `/api/employees/{id}/history` من `audit_logs`.
- الحماية: قراءة `employees.view` · كتابة `employees.update`. Audit: `position_*` · `employee_contract_added/updated` · `employee_document_added/removed`.

## ربط الهوية بالحساب (Issue #47) — أساس الخدمة الذاتية (Epic 9)

طبقة الهوية فقط — **لا نقاط نهاية خدمة ذاتية هنا** (تُبنى في #48–#52 فوق هذا الأساس).

- **العمود:** `employees.user_id` (nullable, **unique**, FK → users **nullOnDelete**) — علاقة **1:1 اختيارية للطرفين**.
- **العلاقات:** `User::employee()` (hasOne) · `Employee::user()` (belongsTo) → الوصول دائماً عبر `Auth::user()->employee`.
- **`Employee::isOwnedBy(User)`:** أساس عزل الخدمة الذاتية (لا وصول لبيانات موظف آخر).
- **`EmployeeIdentityService`:** `link` / `unlink` مع فرض **1:1** صارم:
  - مستخدم مرتبط بموظف آخر → 422 · موظف مرتبط بحساب آخر → 422 (إعادة الربط تتطلب `unlink` أولاً — بلا استبدال صامت) · نفس الزوج → idempotent.
  - Audit: `employee_identity_linked` · `employee_identity_unlinked` (عند تمرير Request).
- **Middleware `employee.linked`** (`EnsureLinkedEmployee`): يتطلب موظفاً مرتبطاً بالمستخدم الحالي، وإلا **403** (`NO_LINKED_EMPLOYEE`) — حارس يُعاد استخدامه في #48–#52.
- **الصلاحيات (كتالوج RBAC):** `dashboard.view_own` · `payslip.view_own` · `attendance.view_own` · `leave.view_own` · `profile.update_own` (مستقلة لكل ميزة).
- **دورة حياة نظيفة:** حذف المستخدم (soft) أو فكّ الربط **لا يفسد سجل الموظف**؛ إعادة الربط (A→فكّ→B) بلا بيانات معلّقة.

**اعتماد على Core:** يقرأ الفروع/الأقسام عبر نماذجها المشتركة؛ يستخدم `RecordsAudit`.
الحالة: **إدارة الموظفين + السجلات الأساسية + الحضور/البصمة/الإجازات + ربط الهوية (#47) جاهزة.**
