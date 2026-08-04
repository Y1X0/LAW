<?php

namespace Tests\Feature\Finance;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Modules\Finance\Models\Account;
use Modules\Finance\Seeders\ChartOfAccountsSeeder;
use Modules\Finance\Support\AccountResolver;
use Modules\Finance\Support\AccountRole;
use Tests\TestCase;

/**
 * دليل الحسابات + المحلّل بالدور (Phase 6 · PR-2).
 */
class ChartOfAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_creates_chart_of_accounts_table(): void
    {
        $this->assertTrue(Schema::hasTable('chart_of_accounts'));
    }

    public function test_seeder_builds_default_tree_with_valid_types_and_parents(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);

        $this->assertSame(count(ChartOfAccountsSeeder::ACCOUNTS), Account::count());

        // كل الأنواع ضمن المجموعة المسموحة.
        foreach (Account::all() as $account) {
            $this->assertContains($account->type, Account::TYPES);
        }

        // مثال على التسلسل الهرمي: الصندوق (1010) تابع لمجموعة الأصول (1000).
        $cash = Account::where('code', '1010')->firstOrFail();
        $assetsGroup = Account::where('code', '1000')->firstOrFail();
        $this->assertSame($assetsGroup->id, $cash->parent_id);
        $this->assertNull($assetsGroup->parent_id);
    }

    public function test_every_system_role_resolves_to_expected_account_type(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);
        $resolver = new AccountResolver;

        $expectedTypes = [
            AccountRole::CASH => 'asset',
            AccountRole::BANK => 'asset',
            AccountRole::ACCOUNTS_RECEIVABLE => 'asset',
            AccountRole::VAT_PAYABLE => 'liability',
            AccountRole::FEE_REVENUE => 'revenue',
            AccountRole::GENERAL_EXPENSE => 'expense',
        ];

        foreach (AccountRole::ALL as $role) {
            $account = $resolver->resolve($role);
            $this->assertSame($role, $account->system_role);
            $this->assertSame($expectedTypes[$role], $account->type, "الدور {$role} نوعه غير متوقّع");
        }
    }

    public function test_system_roles_are_unique(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);

        $roles = Account::whereNotNull('system_role')->pluck('system_role');
        $this->assertSame($roles->count(), $roles->unique()->count());
        $this->assertSame(count(AccountRole::ALL), $roles->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        $this->assertSame(count(ChartOfAccountsSeeder::ACCOUNTS), Account::count());
        $this->assertSame(1, Account::where('code', '1200')->count());
    }

    public function test_resolver_throws_when_account_missing(): void
    {
        // بلا بذر — لا حسابات نظامية.
        $this->expectException(ModelNotFoundException::class);
        (new AccountResolver)->resolve(AccountRole::ACCOUNTS_RECEIVABLE);
    }

    public function test_resolver_rejects_unknown_role(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new AccountResolver)->resolve('not_a_real_role');
    }

    public function test_resolver_id_helper_returns_account_id(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);
        $resolver = new AccountResolver;

        $ar = Account::where('system_role', AccountRole::ACCOUNTS_RECEIVABLE)->firstOrFail();
        $this->assertSame($ar->id, $resolver->id(AccountRole::ACCOUNTS_RECEIVABLE));
    }
}
