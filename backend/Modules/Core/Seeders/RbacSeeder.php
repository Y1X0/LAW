<?php

namespace Modules\Core\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;

/**
 * تهيئة بيانات RBAC الأساسية (Issue #12): كتالوج الصلاحيات (docs/05 §2)
 * + الأدوار النظامية، مع منح المدير العام (admin) كامل الصلاحيات.
 * Idempotent — يمكن تشغيله بأمان أكثر من مرة.
 */
class RbacSeeder extends Seeder
{
    /** كتالوج الصلاحيات الذرّية (docs/05 §2). */
    public const PERMISSIONS = [
        'employees.view', 'employees.create', 'employees.update', 'employees.delete', 'employees.salary.view',
        'attendance.view', 'attendance.manual', 'attendance.approve', 'attendance.devices',
        'leaves.request', 'leaves.approve', 'leaves.view_all',
        'payroll.view', 'payroll.create', 'payroll.approve', 'payroll.pay', 'payslip.view_own',
        'dashboard.view_own', 'attendance.view_own', 'leave.view_own', 'leave.request_own', 'profile.update_own',
        'cases.view_own', 'cases.view_all', 'cases.create', 'cases.update', 'cases.assign', 'cases.close',
        'hearings.manage',
        'clients.view', 'clients.create', 'clients.update',
        'contracts.view', 'contracts.manage',
        'invoices.view', 'invoices.create', 'invoices.approve', 'payments.create', 'expenses.create', 'journal.post', 'finance.reports',
        'leads.view', 'leads.manage', 'campaigns.manage', 'leads.convert',
        'tasks.view_own', 'tasks.view_all', 'tasks.create', 'tasks.assign', 'tasks.complete',
        'worklog.view_own', 'worklog.submit_own', 'worklog.view_all',
        'documents.upload', 'documents.delete',
        'archive.view', 'archive.create', 'archive.update', 'archive.delete',
        'users.manage', 'roles.manage', 'settings.manage', 'audit.view', 'backup.manage', 'org.manage', 'org.view',
        'reports.hr', 'reports.finance', 'reports.cases', 'reports.marketing',
        'dashboard.view_management', // لوحة المؤشرات الإدارية الشاملة (Phase 7)
    ];

    /** الأدوار النظامية (docs/05 §3). */
    public const SYSTEM_ROLES = [
        'admin' => 'المدير العام',
        'hr' => 'الموارد البشرية',
        'lawyer' => 'محامٍ',
        'accountant' => 'المالية',
        'marketing' => 'التسويق',
        'secretary' => 'السكرتارية',
        'employee' => 'موظف',
    ];

    /**
     * صلاحيات دور «محامٍ» — المجال القانوني الذي تحتاجه بوابة المحامي (Lawyer Portal).
     * يغطّي: اللوحة والقضايا بعزل view_own، وتبويبات ملف القضية (ترث عزل القضية)،
     * والأرشيف، والمهام (عرض/إكمال)، والإنجاز اليومي (عرض/تسجيل).
     * (لا يُغيّر نموذج الصلاحيات — يربط صلاحيات موجودة فقط.)
     */
    public const LAWYER_PERMISSIONS = [
        'cases.view_own',
        'archive.view',
        'tasks.view_own',
        'tasks.complete',
        'worklog.view_own',
        'worklog.submit_own',
    ];

    /**
     * صلاحيات دور «موظف» — الخدمة الذاتية (docs/05). أي موظف (ومنه المحامي حين
     * يُسنَد الدورين) يرى لوحته وحضوره وإجازاته وكشوفه ويحدّث ملفه — بياناته وحده.
     */
    public const EMPLOYEE_PERMISSIONS = [
        'dashboard.view_own',
        'attendance.view_own',
        'leave.view_own',
        'leave.request_own',
        'payslip.view_own',
        'profile.update_own',
    ];

    /**
     * صلاحيات دور «المالية» (accountant) — المجال المالي (Finance / Phase 6). يغطّي الفواتير
     * والقبض والصرف والقيود والتقارير المالية، إضافة إلى قراءة العملاء (سياق الفوترة). تُوصَل
     * مرّة واحدة؛ نقاط النهاية تصل تدريجيّاً عبر PRات المالية. تُضمّ إليها الخدمة الذاتية أدناه.
     * (لا يُغيّر نموذج الصلاحيات — يربط صلاحيات موجودة في الكتالوج فقط.)
     */
    public const ACCOUNTANT_PERMISSIONS = [
        'invoices.view', 'invoices.create', 'invoices.approve',
        'payments.create', 'expenses.create', 'journal.post', 'finance.reports',
        'clients.view',
    ];

    /**
     * صلاحيات دور «الموارد البشرية» — بوابة HR: إدارة الموظفين والحضور والإجازات وعرض
     * الرواتب، إضافة إلى الخدمة الذاتية. لا صلاحيات إدارة نظام (لا users/roles/settings).
     * المصدر الوحيد لهذه القائمة (يعيد استخدامها DemoSeeder) — لتفادي التباعد.
     */
    public const HR_PERMISSIONS = [
        'employees.view', 'employees.create', 'employees.update',
        'employees.delete', 'employees.salary.view',
        'org.view', // قراءة الفروع/الأقسام لتغذية نماذج الموظفين (لا كتابة تنظيمية).
        'attendance.view', 'attendance.manual', 'attendance.approve',
        'leaves.request', 'leaves.approve', 'leaves.view_all',
        'payroll.view',
        'dashboard.view_own', 'profile.update_own', 'leave.view_own', 'leave.request_own',
        'attendance.view_own', 'payslip.view_own',
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(
                ['name' => $name],
                ['module' => Str::before($name, '.')]
            );
        }

        // تقليم أي صلاحية خارج الكتالوج (صلاحيات يتيمة سابقة لا يفرضها أي مسار). حذفها
        // يزيل صفوف الربط المرتبطة (FK) تلقائياً، ويبقي «المدير يملك كل شيء» صحيحاً في
        // الإنتاج لا في الاختبار فقط. Idempotent — لا أثر إن لم يوجد ما يُقلَّم.
        Permission::whereNotIn('name', self::PERMISSIONS)->delete();

        foreach (self::SYSTEM_ROLES as $name => $displayName) {
            Role::firstOrCreate(['name' => $name], ['display_name' => $displayName, 'is_system' => true]);
        }

        // منح الصلاحيات لكل دور نظامي. القاعدة الثابتة: لا دور فارغ — كل دور يصل إلى
        // بوابة عاملة. المتخصّصة (admin/hr/lawyer) تأخذ مجالها؛ وبقية أدوار الموظّفين
        // تأخذ الخدمة الذاتية الأساسية (كل موظّف مهما كان قسمه يسجّل حضوره ويطلب إجازته
        // ويرى كشفه ويحدّث ملفه) — إلى أن تُبنى وحداتها المتخصّصة فتُضاف صلاحياتها.
        // محامٍ يجمع المجال القانوني + الخدمة الذاتية كي يعمل الدور وحده (بلا اشتراط «موظف»).
        $grants = [
            'admin' => self::PERMISSIONS, // كل الصلاحيات
            'hr' => self::HR_PERMISSIONS,
            'lawyer' => array_values(array_unique([...self::LAWYER_PERMISSIONS, ...self::EMPLOYEE_PERMISSIONS])),
            'employee' => self::EMPLOYEE_PERMISSIONS,
            'secretary' => self::EMPLOYEE_PERMISSIONS,
            'accountant' => array_values(array_unique([...self::ACCOUNTANT_PERMISSIONS, ...self::EMPLOYEE_PERMISSIONS])),
            'marketing' => self::EMPLOYEE_PERMISSIONS,
        ];

        foreach ($grants as $roleName => $permissionNames) {
            Role::where('name', $roleName)->first()
                ?->permissions()->sync(Permission::whereIn('name', $permissionNames)->pluck('id'));
        }
    }
}
