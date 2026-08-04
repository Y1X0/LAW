<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\Finance\Models\FinancialAccount;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\JournalEntry;
use Modules\Finance\Models\Payment;
use Modules\Finance\Seeders\ChartOfAccountsSeeder;
use Modules\Finance\Services\InvoiceService;
use Modules\Finance\Support\AccountResolver;
use Modules\Finance\Support\AccountRole;
use Modules\Legal\Models\Client;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * سندات القبض Backend (Phase 6 · PR-6). يثبت الخمسة: Idempotency، القيد الصحيح،
 * تحديث حالة الفاتورة، العكس لا الحذف، وحارس الصلاحية.
 */
class PaymentTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private FinancialAccount $cash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->cash = FinancialAccount::factory()->create(['type' => 'cash', 'current_balance' => 0]);
    }

    private function request(User $user): Request
    {
        $request = Request::create('/api/finance', 'POST');
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    /** فاتورة معتمدة (sent) بإجمالي محدّد وبلا ضريبة، عبر الخدمة. */
    private function sentInvoice(float $total = 1000): Invoice
    {
        $actor = User::factory()->create();
        $client = Client::factory()->create();
        $service = app(InvoiceService::class);
        $invoice = $service->create([
            'client_id' => $client->id,
            'items' => [['description' => 'أتعاب', 'quantity' => 1, 'unit_price' => $total, 'tax_rate' => 0]],
        ], $this->request($actor));

        return $service->approve($invoice, $this->request($actor));
    }

    private function payload(float $amount): array
    {
        return ['amount' => $amount, 'method' => 'cash', 'account_id' => $this->cash->id];
    }

    public function test_record_payment_posts_receipt_journal_and_marks_partial(): void
    {
        $user = $this->userWithPermissions(['payments.create', 'invoices.view']);
        $invoice = $this->sentInvoice(1000);

        $this->actingAsToken($user)
            ->postJson("/api/invoices/{$invoice->id}/payments", $this->payload(400))
            ->assertCreated()
            ->assertJsonPath('data.receipt_no', 'RCP-000001')
            ->assertJsonPath('data.amount', '400.00');

        $invoice->refresh();
        $this->assertSame('400.00', $invoice->paid_amount);
        $this->assertSame('600.00', $invoice->balance);
        $this->assertSame('partial', $invoice->status);

        // القيد: مدين الصندوق 400 = دائن ذمم العملاء 400.
        $payment = Payment::firstWhere('invoice_id', $invoice->id);
        $entry = JournalEntry::with('lines')->find($payment->journal_entry_id);
        $accounts = new AccountResolver;
        $lineFor = fn (string $role) => $entry->lines->firstWhere('account_id', $accounts->id($role));
        $this->assertSame('400.00', $lineFor(AccountRole::CASH)->debit);
        $this->assertSame('400.00', $lineFor(AccountRole::ACCOUNTS_RECEIVABLE)->credit);
        $this->assertSame((float) $entry->lines->sum('debit'), (float) $entry->lines->sum('credit'));

        $this->assertSame('400.00', $this->cash->refresh()->current_balance);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment_recorded']);
    }

    public function test_full_payment_marks_paid(): void
    {
        $user = $this->userWithPermissions(['payments.create']);
        $invoice = $this->sentInvoice(1000);

        $this->actingAsToken($user)->postJson("/api/invoices/{$invoice->id}/payments", $this->payload(1000))->assertCreated();

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('0.00', $invoice->balance);
    }

    public function test_overpayment_is_rejected(): void
    {
        $user = $this->userWithPermissions(['payments.create']);
        $invoice = $this->sentInvoice(1000);

        $this->actingAsToken($user)
            ->postJson("/api/invoices/{$invoice->id}/payments", $this->payload(1500))
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VALIDATION_ERROR');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_cannot_pay_a_draft_invoice(): void
    {
        $user = $this->userWithPermissions(['payments.create']);
        $client = Client::factory()->create();
        $draft = app(InvoiceService::class)->create([
            'client_id' => $client->id,
            'items' => [['description' => 'أتعاب', 'quantity' => 1, 'unit_price' => 500, 'tax_rate' => 0]],
        ], $this->request(User::factory()->create()));

        $this->actingAsToken($user)
            ->postJson("/api/invoices/{$draft->id}/payments", $this->payload(100))
            ->assertStatus(422);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_recording_requires_payments_permission(): void
    {
        $invoice = $this->sentInvoice(1000);

        // بلا صلاحية payments.create (يملك القراءة فقط) → 403 (حارس العزل).
        $viewer = $this->userWithPermissions(['invoices.view']);
        $this->actingAsToken($viewer)
            ->postJson("/api/invoices/{$invoice->id}/payments", $this->payload(100))
            ->assertStatus(403);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_recording_requires_authentication(): void
    {
        $invoice = $this->sentInvoice(1000);

        $this->postJson("/api/invoices/{$invoice->id}/payments", $this->payload(100))->assertStatus(401);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_idempotency_key_prevents_duplicate_payment_and_journal(): void
    {
        $user = $this->userWithPermissions(['payments.create']);
        $invoice = $this->sentInvoice(1000);

        $first = $this->actingAsToken($user)
            ->withHeaders(['Idempotency-Key' => 'abc-123'])
            ->postJson("/api/invoices/{$invoice->id}/payments", $this->payload(400))
            ->assertCreated()
            ->json('data');

        $second = $this->actingAsToken($user)
            ->withHeaders(['Idempotency-Key' => 'abc-123'])
            ->postJson("/api/invoices/{$invoice->id}/payments", $this->payload(400))
            ->assertCreated()
            ->json('data');

        $this->assertSame($first['id'], $second['id']); // نفس السند
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('journal_entries', 2); // قيد الفاتورة + قيد قبض واحد فقط

        $invoice->refresh();
        $this->assertSame('400.00', $invoice->paid_amount); // لم يُحصَّل مرّتين
    }

    public function test_reverse_creates_negative_receipt_and_restores_invoice(): void
    {
        $user = $this->userWithPermissions(['payments.create']);
        $invoice = $this->sentInvoice(1000);

        $paymentId = $this->actingAsToken($user)
            ->postJson("/api/invoices/{$invoice->id}/payments", $this->payload(400))
            ->json('data.id');

        $this->actingAsToken($user)
            ->postJson("/api/payments/{$paymentId}/reverse")
            ->assertCreated()
            ->assertJsonPath('data.amount', '-400.00')
            ->assertJsonPath('data.reversal_of_id', $paymentId);

        // الأصل باقٍ تاريخيّاً؛ الفاتورة عادت لِـ sent برصيد كامل؛ الصندوق صفر.
        $original = Payment::find($paymentId);
        $this->assertNotNull($original);
        $this->assertSame('400.00', $original->amount);
        $this->assertTrue($original->isReversed());

        $invoice->refresh();
        $this->assertSame('0.00', $invoice->paid_amount);
        $this->assertSame('1000.00', $invoice->balance);
        $this->assertSame('sent', $invoice->status);
        $this->assertSame('0.00', $this->cash->refresh()->current_balance);

        $this->assertDatabaseCount('journal_entries', 3); // فاتورة + قبض + عكس القبض
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment_reversed']);
    }

    public function test_cannot_reverse_a_payment_twice(): void
    {
        $user = $this->userWithPermissions(['payments.create']);
        $invoice = $this->sentInvoice(1000);
        $paymentId = $this->actingAsToken($user)->postJson("/api/invoices/{$invoice->id}/payments", $this->payload(400))->json('data.id');

        $this->actingAsToken($user)->postJson("/api/payments/{$paymentId}/reverse")->assertCreated();

        $this->actingAsToken($user)
            ->postJson("/api/payments/{$paymentId}/reverse")
            ->assertStatus(422);
    }
}
