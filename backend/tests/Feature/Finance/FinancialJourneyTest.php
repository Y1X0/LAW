<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Seeders\RbacSeeder;
use Modules\Finance\Models\ExpenseCategory;
use Modules\Finance\Models\FinancialAccount;
use Modules\Finance\Seeders\ChartOfAccountsSeeder;
use Modules\Legal\Models\Client;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * الرحلة المالية الكاملة End-to-End (Phase 6 · PR-11 — Release Readiness).
 *
 * يثبت بدور «المالية» (accountant) عبر الـAPI أن الدورة كاملة تعمل وتتّسق أرقامها عبر
 * الدفتر والتقارير: فاتورة → اعتماد → قيد → تحصيل → مصروف → تقارير (ميزان/مستحقات/أ.خ)
 * + الملخّص المالي للعميل. مراجعة RBAC والرحلة في اختبار واحد.
 */
class FinancialJourneyTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    public function test_accountant_completes_the_full_financial_cycle_via_api(): void
    {
        $this->seed(RbacSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);

        $accountant = User::factory()->create();
        $accountant->assignRole('accountant');

        $client = Client::factory()->create(['name' => 'شركة الأمل']);
        $cash = FinancialAccount::factory()->create(['type' => 'cash', 'current_balance' => 0]);
        $category = ExpenseCategory::factory()->create(['name' => 'رسوم محكمة']);

        // 1) إنشاء فاتورة 1000 (invoices.create).
        $invoice = $this->actingAsToken($accountant)
            ->postJson('/api/invoices', ['client_id' => $client->id, 'items' => [['description' => 'أتعاب', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0]]])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->json('data');

        // 2) اعتماد الفاتورة → قيد إيراد مُرحَّل (invoices.approve).
        $this->actingAsToken($accountant)
            ->postJson("/api/invoices/{$invoice['id']}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.journal_entry_id', fn ($id) => $id !== null);

        // 3) تحصيل 400 → الفاتورة partial (payments.create).
        $this->actingAsToken($accountant)
            ->withHeaders(['Idempotency-Key' => 'journey-1'])
            ->postJson("/api/invoices/{$invoice['id']}/payments", ['amount' => 400, 'method' => 'cash', 'account_id' => $cash->id])
            ->assertCreated();

        // 4) تسجيل مصروف 200 (expenses.create).
        $this->actingAsToken($accountant)
            ->postJson('/api/expenses', ['category_id' => $category->id, 'amount' => 200, 'method' => 'cash', 'account_id' => $cash->id])
            ->assertCreated();

        // 5) ميزان المراجعة متوازن (finance.reports).
        $this->actingAsToken($accountant)
            ->getJson('/api/finance/reports/trial-balance')
            ->assertOk()
            ->assertJsonPath('data.total_debit', '1600.00')
            ->assertJsonPath('data.total_credit', '1600.00');

        // 6) المستحقات: العميل يدين بـ 600.
        $this->actingAsToken($accountant)
            ->getJson('/api/finance/reports/receivables')
            ->assertOk()
            ->assertJsonPath('data.total_outstanding', '600.00')
            ->assertJsonPath('data.clients.0.outstanding', '600.00');

        // 7) الأرباح/الخسائر: إيراد 1000 − مصروف 200 = صافي 800.
        $this->actingAsToken($accountant)
            ->getJson('/api/finance/reports/income-expense')
            ->assertOk()
            ->assertJsonPath('data.revenue', '1000.00')
            ->assertJsonPath('data.expenses', '200.00')
            ->assertJsonPath('data.net', '800.00');

        // 8) الملخّص المالي للعميل يتّسق مع ما سبق.
        $this->actingAsToken($accountant)
            ->getJson("/api/finance/clients/{$client->id}/summary")
            ->assertOk()
            ->assertJsonPath('data.total_invoiced', '1000.00')
            ->assertJsonPath('data.total_paid', '400.00')
            ->assertJsonPath('data.outstanding', '600.00');
    }
}
