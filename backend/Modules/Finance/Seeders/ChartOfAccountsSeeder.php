<?php

namespace Modules\Finance\Seeders;

use Illuminate\Database\Seeder;
use Modules\Finance\Models\Account;
use Modules\Finance\Support\AccountRole;

/**
 * دليل الحسابات الافتراضي (Finance / Phase 6 · PR-2) — مرجعي، آمن للإنتاج، Idempotent.
 *
 * شجرة حسابات قياسية لمكتب محاماة (سعودي). الأرقام مرجعية وقابلة للتوسعة؛ الحسابات
 * النظامية تُوسَم بـ system_role كي يحلّها المحرّك بالدور لا بالرقم (انظر AccountResolver).
 * تُشغَّل من DatabaseSeeder. updateOrCreate على `code` يجعلها آمنة للتكرار.
 */
class ChartOfAccountsSeeder extends Seeder
{
    /**
     * الحسابات الافتراضية: [code, name, type, parentCode|null, systemRole|null].
     * الآباء (المجموعات) قبل الأبناء كي يُحلّ parent_id بالرمز.
     *
     * @var list<array{0:string,1:string,2:string,3:?string,4:?string}>
     */
    public const ACCOUNTS = [
        // الأصول
        ['1000', 'الأصول', 'asset', null, null],
        ['1010', 'الصندوق', 'asset', '1000', AccountRole::CASH],
        ['1020', 'البنك', 'asset', '1000', AccountRole::BANK],
        ['1200', 'ذمم العملاء', 'asset', '1000', AccountRole::ACCOUNTS_RECEIVABLE],

        // الخصوم
        ['2000', 'الخصوم', 'liability', null, null],
        ['2100', 'ضريبة القيمة المضافة المستحقة', 'liability', '2000', AccountRole::VAT_PAYABLE],

        // حقوق الملكية
        ['3000', 'حقوق الملكية', 'equity', null, null],
        ['3100', 'رأس المال', 'equity', '3000', null],

        // الإيرادات
        ['4000', 'الإيرادات', 'revenue', null, null],
        ['4100', 'إيرادات الأتعاب المهنية', 'revenue', '4000', AccountRole::FEE_REVENUE],
        ['4900', 'إيرادات أخرى', 'revenue', '4000', null],

        // المصروفات
        ['5000', 'المصروفات', 'expense', null, null],
        ['5100', 'مصروفات عامة', 'expense', '5000', AccountRole::GENERAL_EXPENSE],
        ['5200', 'رواتب وأجور', 'expense', '5000', null],
        ['5300', 'إيجارات', 'expense', '5000', null],
        ['5400', 'رسوم محكمة', 'expense', '5000', null],
        ['5900', 'مصروفات أخرى', 'expense', '5000', null],
    ];

    public function run(): void
    {
        foreach (self::ACCOUNTS as [$code, $name, $type, $parentCode, $role]) {
            $parentId = $parentCode !== null
                ? Account::where('code', $parentCode)->value('id')
                : null;

            Account::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'type' => $type, 'parent_id' => $parentId, 'system_role' => $role, 'is_active' => true],
            );
        }
    }
}
