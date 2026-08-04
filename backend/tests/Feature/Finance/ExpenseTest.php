<?php

namespace Tests\Feature\Finance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Models\Expense;
use Modules\Finance\Models\ExpenseCategory;
use Modules\Finance\Models\FinancialAccount;
use Modules\Finance\Models\JournalEntry;
use Modules\Finance\Seeders\ChartOfAccountsSeeder;
use Modules\Finance\Support\AccountResolver;
use Modules\Finance\Support\AccountRole;
use Modules\Legal\Models\LegalCase;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * سندات الصرف Backend (Phase 6 · PR-8): القيد الآلي، العكس لا الحذف، عزل القضية، الصلاحية.
 */
class ExpenseTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private FinancialAccount $cash;

    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->cash = FinancialAccount::factory()->create(['type' => 'cash', 'current_balance' => 0]);
        $this->category = ExpenseCategory::factory()->create(['name' => 'رسوم محكمة']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category_id' => $this->category->id,
            'amount' => 500,
            'method' => 'cash',
            'account_id' => $this->cash->id,
            'beneficiary' => 'المحكمة',
        ], $overrides);
    }

    public function test_record_posts_expense_journal_and_decrements_account(): void
    {
        $user = $this->userWithPermissions(['expenses.create']);

        $this->actingAsToken($user)
            ->postJson('/api/expenses', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.voucher_no', 'EXP-000001')
            ->assertJsonPath('data.amount', '500.00');

        // القيد: مدين حساب المصروف 500 = دائن الصندوق 500.
        $expense = Expense::firstWhere('voucher_no', 'EXP-000001');
        $entry = JournalEntry::with('lines')->find($expense->journal_entry_id);
        $accounts = new AccountResolver;
        $lineFor = fn (string $role) => $entry->lines->firstWhere('account_id', $accounts->id($role));
        $this->assertSame('500.00', $lineFor(AccountRole::GENERAL_EXPENSE)->debit);
        $this->assertSame('500.00', $lineFor(AccountRole::CASH)->credit);
        $this->assertSame((float) $entry->lines->sum('debit'), (float) $entry->lines->sum('credit'));

        $this->assertSame('-500.00', $this->cash->refresh()->current_balance);
        $this->assertDatabaseHas('audit_logs', ['action' => 'expense_recorded']);
    }

    public function test_recording_requires_expenses_permission(): void
    {
        $user = $this->userWithPermissions(['invoices.view']);
        $this->actingAsToken($user)->postJson('/api/expenses', $this->payload())->assertStatus(403);
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_recording_requires_authentication(): void
    {
        $this->postJson('/api/expenses', $this->payload())->assertStatus(401);
    }

    public function test_validation_rejects_unknown_category_and_account(): void
    {
        $user = $this->userWithPermissions(['expenses.create']);
        $this->actingAsToken($user)->postJson('/api/expenses', $this->payload(['category_id' => 999999]))->assertStatus(422);
        $this->actingAsToken($user)->postJson('/api/expenses', $this->payload(['account_id' => 999999]))->assertStatus(422);
    }

    public function test_case_linked_expense_requires_case_access(): void
    {
        $case = LegalCase::factory()->create();

        // بلا وصول للقضية (لا view_all ولا موظف مرتبط) → 403، ولا يُنشأ سند.
        $blocked = $this->userWithPermissions(['expenses.create']);
        $this->actingAsToken($blocked)
            ->postJson('/api/expenses', $this->payload(['case_id' => $case->id]))
            ->assertStatus(403);
        $this->assertDatabaseCount('expenses', 0);

        // مع cases.view_all → يُسمح بالربط.
        $allowed = $this->userWithPermissions(['expenses.create', 'cases.view_all']);
        $this->actingAsToken($allowed)
            ->postJson('/api/expenses', $this->payload(['case_id' => $case->id]))
            ->assertCreated()
            ->assertJsonPath('data.case_id', $case->id);
    }

    public function test_reverse_creates_negative_voucher_and_restores_account(): void
    {
        $user = $this->userWithPermissions(['expenses.create']);
        $expenseId = $this->actingAsToken($user)->postJson('/api/expenses', $this->payload())->json('data.id');

        $this->actingAsToken($user)
            ->postJson("/api/expenses/{$expenseId}/reverse")
            ->assertCreated()
            ->assertJsonPath('data.amount', '-500.00')
            ->assertJsonPath('data.reversal_of_id', $expenseId);

        $original = Expense::find($expenseId);
        $this->assertSame('500.00', $original->amount); // الأصل باقٍ
        $this->assertTrue($original->isReversed());
        $this->assertSame('0.00', $this->cash->refresh()->current_balance); // الرصيد عاد
        $this->assertDatabaseCount('journal_entries', 2); // صرف + عكس
        $this->assertDatabaseHas('audit_logs', ['action' => 'expense_reversed']);
    }

    public function test_cannot_reverse_an_expense_twice(): void
    {
        $user = $this->userWithPermissions(['expenses.create']);
        $expenseId = $this->actingAsToken($user)->postJson('/api/expenses', $this->payload())->json('data.id');

        $this->actingAsToken($user)->postJson("/api/expenses/{$expenseId}/reverse")->assertCreated();
        $this->actingAsToken($user)->postJson("/api/expenses/{$expenseId}/reverse")->assertStatus(422);
    }
}
