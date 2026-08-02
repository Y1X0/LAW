<?php

namespace Modules\Core\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Department;

/**
 * الهيكل التنظيمي الأساسي (Idempotent): فرع رئيسي واحد + الأقسام النظامية.
 *
 * إعدادات أساسية فقط — لا بيانات تجريبية ولا حسابات. آمنة للإنتاج، ومطلوبة كي
 * يعمل استيراد الموظفين (يطابق الفرع/القسم بالاسم قبل الإنشاء). لا يمسّ أي API
 * أو منطق أو مخطّط. متوافق مع DemoSeeder (نفس المفاتيح الطبيعية — لا تكرار).
 */
class OrgStructureSeeder extends Seeder
{
    /** الفرع الرئيسي — المفتاح الطبيعي: code (فريد في جدول branches). */
    public const BRANCH_CODE = 'HQ';

    public const BRANCH_NAME = 'المكتب الرئيسي';

    /** الأقسام النظامية تحت الفرع الرئيسي (المفتاح الطبيعي: branch_id + name). */
    public const DEPARTMENTS = [
        'الموارد البشرية',
        'التقاضي',
        'الاستشارات',
        'المالية',
        'الإدارة',
        'تقنية المعلومات',
    ];

    public function run(): void
    {
        $branch = Branch::firstOrCreate(
            ['code' => self::BRANCH_CODE],
            ['name' => self::BRANCH_NAME, 'is_active' => true],
        );

        foreach (self::DEPARTMENTS as $name) {
            Department::firstOrCreate(
                ['branch_id' => $branch->id, 'name' => $name],
                ['is_active' => true],
            );
        }
    }
}
