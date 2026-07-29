# وحدة HR

**الملكية (Owns):** employees, departments, contracts, allowances, deductions
**تُتيح (Exposes):** EmployeeService

> المرجع: [docs/module-boundaries.md](../../../docs/module-boundaries.md)

**القاعدة الذهبية:** لا تصل هذه الوحدة إلى جداول وحدة أخرى مباشرةً — التواصل عبر الخدمات/الأحداث فقط.

## إدارة الموظفين (Issue #13)
- `Models/Employee` (+ Factory) · `Services/EmployeeService` (create/update/archive + Audit) · `Http/Controllers/EmployeeController` · `Http/Requests/{Store,Update}EmployeeRequest`.
- جدول `employees` (docs/02 §4): يرتبط بالفرع والقسم ومدير مباشر (علاقة ذاتية).
- نقاط النهاية (`/api/employees`، محميّة بـ `employees.*`): `index` (بحث/تصفية/ترقيم) · `show` · `store` · `update` · `destroy` (أرشفة Soft Delete).
- حالات الموظف: `active` / `on_leave` / `suspended` / `terminated`.
- حماية على مستوى الحقل: الراتب/الحساب البنكي يُخفيان إلا بصلاحية `employees.salary.view`.
- Audit: `employee_created` / `employee_updated` / `employee_archived`.

**اعتماد على Core:** يقرأ الفروع/الأقسام عبر نماذجها المشتركة (بنية تنظيمية)؛ يستخدم `RecordsAudit`.
الحالة: **إدارة الموظفين جاهزة**. السجلات المتقدمة/العقود في Issue #14؛ الحضور/الإجازات في #15–#17.
