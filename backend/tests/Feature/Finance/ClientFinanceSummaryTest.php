<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\Finance\Models\FinancialAccount;
use Modules\Finance\Seeders\ChartOfAccountsSeeder;
use Modules\Finance\Services\InvoiceService;
use Modules\Finance\Services\PaymentService;
use Modules\Legal\Models\Client;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * الملخّص المالي للعميل + قائمة الحسابات (Phase 6 · PR-7) — مجاميع من الخادم للواجهة.
 */
class ClientFinanceSummaryTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    private function request(User $user): Request
    {
        $request = Request::create('/api/finance', 'POST');
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    public function test_summary_aggregates_issued_invoices_and_payments(): void
    {
        $actor = User::factory()->create();
        $client = Client::factory()->create();
        $account = FinancialAccount::factory()->create(['type' => 'cash']);
        $invoices = app(InvoiceService::class);
        $payments = app(PaymentService::class);

        // فاتورتان مُصدَرتان (1000 + 500) ودفعة 400 على الأولى، ومسودّة لا تُحتسب.
        $inv1 = $invoices->approve($invoices->create(['client_id' => $client->id, 'items' => [['description' => 'أ', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0]]], $this->request($actor)), $this->request($actor));
        $invoices->approve($invoices->create(['client_id' => $client->id, 'items' => [['description' => 'ب', 'quantity' => 1, 'unit_price' => 500, 'tax_rate' => 0]]], $this->request($actor)), $this->request($actor));
        $invoices->create(['client_id' => $client->id, 'items' => [['description' => 'مسودّة', 'quantity' => 1, 'unit_price' => 9999, 'tax_rate' => 0]]], $this->request($actor));
        $payments->record($inv1, ['amount' => 400, 'method' => 'cash', 'account_id' => $account->id], null, $this->request($actor));

        $viewer = $this->userWithPermissions(['invoices.view']);
        $this->actingAsToken($viewer)
            ->getJson("/api/finance/clients/{$client->id}/summary")
            ->assertOk()
            ->assertJsonPath('data.invoice_count', 2)
            ->assertJsonPath('data.total_invoiced', '1500.00')
            ->assertJsonPath('data.total_paid', '400.00')
            ->assertJsonPath('data.outstanding', '1100.00');
    }

    public function test_summary_requires_invoices_view(): void
    {
        $client = Client::factory()->create();
        $user = $this->userWithPermissions(['payslip.view_own']);
        $this->actingAsToken($user)->getJson("/api/finance/clients/{$client->id}/summary")->assertStatus(403);
    }

    public function test_accounts_list_returns_active_accounts_for_payment_permission(): void
    {
        FinancialAccount::factory()->create(['name' => 'الصندوق', 'type' => 'cash']);
        FinancialAccount::factory()->inactive()->create(['name' => 'قديم']);

        $user = $this->userWithPermissions(['payments.create']);
        $this->actingAsToken($user)
            ->getJson('/api/finance/accounts')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'الصندوق')
            ->assertJsonCount(1, 'data');
    }

    public function test_accounts_list_requires_payment_permission(): void
    {
        $user = $this->userWithPermissions(['invoices.view']);
        $this->actingAsToken($user)->getJson('/api/finance/accounts')->assertStatus(403);
    }

    public function test_capabilities_includes_payment_flag(): void
    {
        $user = $this->userWithPermissions(['payments.create']);
        $this->actingAsToken($user)
            ->getJson('/api/finance/capabilities')
            ->assertOk()
            ->assertJsonPath('data.can_record_payment', true);
    }
}
