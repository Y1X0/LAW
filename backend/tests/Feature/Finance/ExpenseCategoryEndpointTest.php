<?php

namespace Tests\Feature\Finance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Models\ExpenseCategory;
use Modules\Finance\Models\FinancialAccount;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * مُمكِّنات واجهة المصروفات (Phase 6 · PR-9): التصنيفات، القدرة، والحسابات لمسجّل الصرف.
 */
class ExpenseCategoryEndpointTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_categories_list_returns_active_for_expense_permission(): void
    {
        ExpenseCategory::factory()->create(['name' => 'رسوم محكمة']);
        ExpenseCategory::factory()->inactive()->create(['name' => 'قديم']);

        $user = $this->userWithPermissions(['expenses.create']);
        $this->actingAsToken($user)
            ->getJson('/api/finance/expense-categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'رسوم محكمة');
    }

    public function test_categories_list_requires_expense_permission(): void
    {
        $user = $this->userWithPermissions(['invoices.view']);
        $this->actingAsToken($user)->getJson('/api/finance/expense-categories')->assertStatus(403);
    }

    public function test_accounts_list_is_available_to_expense_recorders(): void
    {
        FinancialAccount::factory()->create(['name' => 'الصندوق', 'type' => 'cash']);

        // مستخدم يملك expenses.create فقط (لا payments.create) يصل إلى الحسابات.
        $user = $this->userWithPermissions(['expenses.create']);
        $this->actingAsToken($user)->getJson('/api/finance/accounts')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_capabilities_includes_expense_flag(): void
    {
        $user = $this->userWithPermissions(['expenses.create']);
        $this->actingAsToken($user)
            ->getJson('/api/finance/capabilities')
            ->assertOk()
            ->assertJsonPath('data.can_record_expense', true);
    }
}
