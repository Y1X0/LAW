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
        'employees.view', 'employees.view_all', 'employees.create', 'employees.update', 'employees.delete', 'employees.salary.view',
        'attendance.view', 'attendance.manual', 'attendance.approve', 'attendance.report', 'attendance.devices',
        'leaves.request', 'leaves.approve', 'leaves.view_all',
        'payroll.view', 'payroll.create', 'payroll.approve', 'payroll.pay', 'payslip.view_own',
        'dashboard.view_own', 'attendance.view_own', 'leave.view_own', 'leave.request_own', 'profile.update_own',
        'cases.view', 'cases.view_all', 'cases.create', 'cases.update', 'cases.close', 'cases.delete',
        'hearings.view', 'hearings.manage',
        'clients.view', 'clients.create', 'clients.update', 'clients.delete',
        'contracts.view', 'contracts.manage',
        'invoices.view', 'invoices.create', 'invoices.approve', 'payments.create', 'expenses.create', 'journal.post', 'finance.reports',
        'leads.view', 'leads.manage', 'campaigns.manage', 'leads.convert',
        'tasks.view', 'tasks.create', 'tasks.assign',
        'documents.view', 'documents.upload', 'documents.view_confidential', 'documents.delete',
        'users.manage', 'roles.manage', 'settings.manage', 'audit.view', 'backup.manage',
        'reports.hr', 'reports.finance', 'reports.cases', 'reports.marketing',
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

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(
                ['name' => $name],
                ['module' => Str::before($name, '.')]
            );
        }

        foreach (self::SYSTEM_ROLES as $name => $displayName) {
            Role::firstOrCreate(['name' => $name], ['display_name' => $displayName, 'is_system' => true]);
        }

        // المدير العام يملك كل الصلاحيات.
        Role::where('name', 'admin')->first()
            ?->permissions()->sync(Permission::pluck('id'));
    }
}
