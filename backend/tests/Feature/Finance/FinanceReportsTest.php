<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\Finance\Models\ExpenseCategory;
use Modules\Finance\Models\FinancialAccount;
use Modules\Finance\Seeders\ChartOfAccountsSeeder;
use Modules\Finance\Services\ExpenseService;
use Modules\Finance\Services\InvoiceService;
use Modules\Finance\Services\PaymentService;
use Modules\Legal\Models\Client;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * التقارير المالية (Phase 6 · PR-10): ميزان المراجعة، المستحقات، والأرباح/الخسائر —
 * كلها من القيود المُرحَّلة والفواتير، محروسة بـ finance.reports.
 */
class FinanceReportsTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->buildActivity();
    }

    private function request(User $user): Request
    {
        $request = Request::create('/api/finance', 'POST');
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    /** فاتورة 1000 (معتمدة) + دفعة 400 + مصروف 200 نقداً. */
    private function buildActivity(): void
    {
        $actor = User::factory()->create();
        $req = $this->request($actor);
        $client = Client::factory()->create(['name' => 'شركة الأمل']);
        $cash = FinancialAccount::factory()->create(['type' => 'cash', 'current_balance' => 0]);
        $category = ExpenseCategory::factory()->create();

        $invoice = app(InvoiceService::class)->approve(
            app(InvoiceService::class)->create(['client_id' => $client->id, 'items' => [['description' => 'أتعاب', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0]]], $req),
            $req,
        );
        app(PaymentService::class)->record($invoice, ['amount' => 400, 'method' => 'cash', 'account_id' => $cash->id], null, $req);
        app(ExpenseService::class)->record(['category_id' => $category->id, 'amount' => 200, 'method' => 'cash', 'account_id' => $cash->id], $req);
    }

    public function test_trial_balance_is_balanced(): void
    {
        $user = $this->userWithPermissions(['finance.reports']);
        $this->actingAsToken($user)
            ->getJson('/api/finance/reports/trial-balance')
            ->assertOk()
            ->assertJsonPath('data.total_debit', '1600.00')
            ->assertJsonPath('data.total_credit', '1600.00');
    }

    public function test_receivables_lists_outstanding_per_client(): void
    {
        $user = $this->userWithPermissions(['finance.reports']);
        $this->actingAsToken($user)
            ->getJson('/api/finance/reports/receivables')
            ->assertOk()
            ->assertJsonPath('data.total_outstanding', '600.00')
            ->assertJsonPath('data.clients.0.client_name', 'شركة الأمل')
            ->assertJsonPath('data.clients.0.outstanding', '600.00');
    }

    public function test_income_expense_summary(): void
    {
        $user = $this->userWithPermissions(['finance.reports']);
        $this->actingAsToken($user)
            ->getJson('/api/finance/reports/income-expense')
            ->assertOk()
            ->assertJsonPath('data.revenue', '1000.00')
            ->assertJsonPath('data.expenses', '200.00')
            ->assertJsonPath('data.net', '800.00');
    }

    public function test_reports_require_finance_reports_permission(): void
    {
        $user = $this->userWithPermissions(['invoices.view']);
        $this->actingAsToken($user)->getJson('/api/finance/reports/trial-balance')->assertStatus(403);
        $this->actingAsToken($user)->getJson('/api/finance/reports/receivables')->assertStatus(403);
        $this->actingAsToken($user)->getJson('/api/finance/reports/income-expense')->assertStatus(403);
    }

    public function test_capabilities_includes_reports_flag(): void
    {
        $user = $this->userWithPermissions(['finance.reports']);
        $this->actingAsToken($user)
            ->getJson('/api/finance/capabilities')
            ->assertOk()
            ->assertJsonPath('data.can_view_reports', true);
    }
}
