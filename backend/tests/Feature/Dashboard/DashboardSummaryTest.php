<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\Finance\Models\ExpenseCategory;
use Modules\Finance\Models\FinancialAccount;
use Modules\Finance\Seeders\ChartOfAccountsSeeder;
use Modules\Finance\Services\ExpenseService;
use Modules\Finance\Services\InvoiceService;
use Modules\Finance\Services\PaymentService;
use Modules\HR\Models\Employee;
use Modules\Legal\Models\CaseTask;
use Modules\Legal\Models\Client;
use Modules\Legal\Models\Hearing;
use Modules\Legal\Models\LegalCase;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * لوحة المؤشرات الإدارية (Phase 7 · PR-1) — تجميع قراءة فقط عبر الوحدات + حارس الصلاحية.
 */
class DashboardSummaryTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function request(User $user): Request
    {
        $request = Request::create('/api/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function buildActivity(): Client
    {
        $client = Client::factory()->create(['status' => 'active']);
        $caseA = LegalCase::factory()->create(['status' => 'open', 'client_id' => $client->id, 'responsible_lawyer_id' => null]);
        LegalCase::factory()->create(['status' => 'closed', 'client_id' => $client->id, 'responsible_lawyer_id' => null]);
        Hearing::factory()->create(['case_id' => $caseA->id, 'status' => 'scheduled', 'scheduled_at' => now()->addDays(3)]);
        CaseTask::factory()->create(['case_id' => $caseA->id, 'status' => 'open', 'due_date' => now()->subDays(2)->toDateString()]);
        Employee::factory()->create(['status' => 'active']);

        // مالية: فاتورة 1000 معتمدة + دفعة 400 + مصروف 200 → مستحقات 600، إيراد 1000، مصروف 200.
        $actor = User::factory()->create();
        $req = $this->request($actor);
        $cash = FinancialAccount::factory()->create(['type' => 'cash', 'current_balance' => 0]);
        $category = ExpenseCategory::factory()->create();
        $invoice = app(InvoiceService::class)->approve(
            app(InvoiceService::class)->create(['client_id' => $client->id, 'items' => [['description' => 'أتعاب', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0]]], $req),
            $req,
        );
        app(PaymentService::class)->record($invoice, ['amount' => 400, 'method' => 'cash', 'account_id' => $cash->id], null, $req);
        app(ExpenseService::class)->record(['category_id' => $category->id, 'amount' => 200, 'method' => 'cash', 'account_id' => $cash->id], $req);

        return $client;
    }

    public function test_summary_aggregates_firm_wide_kpis(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);
        $this->buildActivity();

        // العدّادات التي قد تنشئها المصانع ضمنيّاً (عملاء/موظفون) تُقارَن بحالة القاعدة الحيّة
        // — يُثبت أن النقطة تُجمِّع الاستعلام الصحيح؛ أرقام القضايا/المالية مضبوطة حرفيّاً.
        $activeClients = Client::where('status', 'active')->count();
        $activeEmployees = Employee::where('status', 'active')->count();

        $user = $this->userWithPermissions(['dashboard.view_management']);
        $this->actingAsToken($user)
            ->getJson('/api/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.legal.cases_total', 2)
            ->assertJsonPath('data.legal.cases_open', 1)
            ->assertJsonPath('data.legal.cases_closed', 1)
            ->assertJsonPath('data.legal.hearings_upcoming', 1)
            ->assertJsonPath('data.legal.tasks_overdue', 1)
            ->assertJsonPath('data.clients.active', $activeClients)
            ->assertJsonPath('data.hr.employees_active', $activeEmployees)
            ->assertJsonPath('data.finance.outstanding', '600.00')
            ->assertJsonPath('data.finance.revenue', '1000.00')
            ->assertJsonPath('data.finance.expenses', '200.00')
            ->assertJsonPath('data.finance.net', '800.00');
    }

    public function test_summary_requires_management_permission(): void
    {
        $user = $this->userWithPermissions(['invoices.view']);
        $this->actingAsToken($user)->getJson('/api/dashboard/summary')->assertStatus(403);
    }

    public function test_summary_requires_authentication(): void
    {
        $this->getJson('/api/dashboard/summary')->assertStatus(401);
    }
}
