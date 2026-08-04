<?php

namespace Modules\Finance\Support;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Modules\Finance\Models\Account;

/**
 * محلّل الحسابات النظامية (Finance / Phase 6 · PR-2).
 *
 * نقطة الوصول الوحيدة لحسابات الدفتر داخل منطق المحاسبة: تُعيد الحساب حسب دوره
 * الدلالي (AccountRole) لا حسب رقمه. بهذا تعتمد الفواتير/القبض/الصرف والمحرّك على
 * أدوار ثابتة، ويبقى ترقيم دليل الحسابات قابلاً للتغيير دون لمس أي منطق.
 *
 * يخزّن النتائج مؤقتاً ضمن العملية الواحدة، ويرمي استثناءً واضحاً إذا غاب الحساب
 * النظامي (دليل الحسابات غير مُهيّأ) بدل الترحيل إلى حساب خاطئ بصمت.
 */
class AccountResolver
{
    /** @var array<string, Account> */
    private array $cache = [];

    public function resolve(string $role): Account
    {
        if (! in_array($role, AccountRole::ALL, true)) {
            throw new InvalidArgumentException("دور حساب غير معروف: {$role}");
        }

        if (isset($this->cache[$role])) {
            return $this->cache[$role];
        }

        $account = Account::where('system_role', $role)->first();

        if ($account === null) {
            throw new ModelNotFoundException(
                "لا يوجد حساب نظامي للدور «{$role}» — شغّل ChartOfAccountsSeeder.",
            );
        }

        return $this->cache[$role] = $account;
    }

    /** مُعرّف الحساب حسب الدور — مختصر شائع عند بناء سطور القيد. */
    public function id(string $role): int
    {
        return $this->resolve($role)->id;
    }

    /** تفريغ الذاكرة المؤقتة (مفيد في الاختبارات بعد إعادة البذر). */
    public function flush(): void
    {
        $this->cache = [];
    }
}
