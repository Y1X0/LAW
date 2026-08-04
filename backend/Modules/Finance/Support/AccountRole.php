<?php

namespace Modules\Finance\Support;

/**
 * الأدوار الدلالية للحسابات النظامية (Finance / Phase 6 · PR-2).
 *
 * مفاتيح ثابتة يرحّل إليها محرّك القيد المزدوج، مستقلّة عن ترقيم دليل الحسابات.
 * الخدمات اللاحقة (الفواتير/القبض/الصرف) تحلّ الحساب عبر AccountResolver بهذه الأدوار
 * لا بأرقام مثل 1200/4100 — فيمكن إعادة ترقيم الدليل دون تعديل منطق المحاسبة.
 */
final class AccountRole
{
    /** الصندوق (نقدية). */
    public const CASH = 'cash';

    /** البنك. */
    public const BANK = 'bank';

    /** ذمم العملاء (مدينون) — الطرف المدين عند إصدار فاتورة. */
    public const ACCOUNTS_RECEIVABLE = 'accounts_receivable';

    /** ضريبة القيمة المضافة المستحقة — الطرف الدائن لضريبة الفاتورة. */
    public const VAT_PAYABLE = 'vat_payable';

    /** إيرادات الأتعاب المهنية — الطرف الدائن لصافي الفاتورة. */
    public const FEE_REVENUE = 'fee_revenue';

    /** مصروف عام — الطرف المدين الافتراضي لسند الصرف. */
    public const GENERAL_EXPENSE = 'general_expense';

    /** كل الأدوار المعروفة. */
    public const ALL = [
        self::CASH,
        self::BANK,
        self::ACCOUNTS_RECEIVABLE,
        self::VAT_PAYABLE,
        self::FEE_REVENUE,
        self::GENERAL_EXPENSE,
    ];
}
