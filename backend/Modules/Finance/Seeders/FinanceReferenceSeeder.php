<?php

namespace Modules\Finance\Seeders;

use Illuminate\Database\Seeder;
use Modules\Finance\Models\ExpenseCategory;
use Modules\Finance\Models\FinancialAccount;
use Modules\Finance\Models\Tax;

/**
 * البيانات المرجعية المالية الأساسية (Finance / Phase 6 · PR-1) — آمنة للإنتاج وIdempotent.
 *
 * تُنشئ: ضريبة القيمة المضافة الافتراضية (15٪)، الصندوق الرئيسي، وتصنيفات المصروفات
 * الشائعة. لا بيانات تجريبية. يُشغَّل من DatabaseSeeder. يمكن تشغيله مراراً بأمان.
 */
class FinanceReferenceSeeder extends Seeder
{
    /** تصنيفات المصروفات الافتراضية. */
    public const EXPENSE_CATEGORIES = ['رسوم محكمة', 'إيجار', 'رواتب', 'قرطاسية', 'مواصلات', 'أخرى'];

    /** النسبة الافتراضية لضريبة القيمة المضافة (٪). */
    public const DEFAULT_VAT_RATE = 15.00;

    /** اسم الصندوق النقدي الافتراضي. */
    public const DEFAULT_CASH_ACCOUNT = 'الصندوق الرئيسي';

    public function run(): void
    {
        Tax::updateOrCreate(
            ['name' => Tax::VAT],
            ['rate' => self::DEFAULT_VAT_RATE, 'is_active' => true],
        );

        FinancialAccount::firstOrCreate(
            ['name' => self::DEFAULT_CASH_ACCOUNT],
            ['type' => 'cash', 'currency' => 'SAR', 'opening_balance' => 0, 'current_balance' => 0, 'is_active' => true],
        );

        foreach (self::EXPENSE_CATEGORIES as $name) {
            ExpenseCategory::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
