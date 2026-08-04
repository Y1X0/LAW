<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Seeders\RbacSeeder;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\JournalEntry;
use Modules\Finance\Seeders\ChartOfAccountsSeeder;
use Modules\Finance\Support\AccountResolver;
use Modules\Finance\Support\AccountRole;
use Modules\Legal\Models\Client;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * الفواتير Backend (Phase 6 · PR-4): الإجماليات (ضريبة لكل بند + خصم)، دورة الحالة،
 * الاعتماد وترحيل القيد الآلي، الصلاحيات، والمناعة بعد الاعتماد.
 */
class InvoiceTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
    }

    private function client(): Client
    {
        return Client::factory()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'client_id' => $this->client()->id,
            'items' => [
                ['description' => 'أتعاب', 'quantity' => 2, 'unit_price' => 100, 'tax_rate' => 15],
                ['description' => 'رسوم', 'quantity' => 1, 'unit_price' => 50, 'tax_rate' => 0],
            ],
        ], $overrides);
    }

    public function test_create_computes_totals_with_per_line_tax(): void
    {
        $admin = $this->userWithPermissions(['invoices.create']);

        $this->actingAsToken($admin)
            ->postJson('/api/invoices', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.invoice_no', 'INV-000001')
            ->assertJsonPath('data.subtotal', '250.00')
            ->assertJsonPath('data.tax_amount', '30.00')
            ->assertJsonPath('data.total', '280.00')
            ->assertJsonPath('data.balance', '280.00');

        $this->assertDatabaseHas('invoice_items', ['description' => 'أتعاب', 'line_total' => 200]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice_created']);
    }

    public function test_create_applies_invoice_level_discount(): void
    {
        $admin = $this->userWithPermissions(['invoices.create']);

        $this->actingAsToken($admin)
            ->postJson('/api/invoices', $this->payload([
                'items' => [['description' => 'أتعاب', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 15]],
                'discount' => 20,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.subtotal', '100.00')
            ->assertJsonPath('data.discount', '20.00')
            ->assertJsonPath('data.tax_amount', '15.00')
            ->assertJsonPath('data.total', '95.00');
    }

    public function test_create_requires_permission(): void
    {
        $user = $this->userWithPermissions(['invoices.view']);
        $this->actingAsToken($user)->postJson('/api/invoices', $this->payload())->assertStatus(403);
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->postJson('/api/invoices', $this->payload())->assertStatus(401);
    }

    public function test_validation_rejects_missing_items(): void
    {
        $admin = $this->userWithPermissions(['invoices.create']);
        $this->actingAsToken($admin)
            ->postJson('/api/invoices', ['client_id' => $this->client()->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VALIDATION_ERROR');
    }

    public function test_validation_rejects_unknown_client_and_case(): void
    {
        $admin = $this->userWithPermissions(['invoices.create']);
        $this->actingAsToken($admin)
            ->postJson('/api/invoices', $this->payload(['client_id' => 999999]))
            ->assertStatus(422);

        $this->actingAsToken($admin)
            ->postJson('/api/invoices', $this->payload(['case_id' => 999999]))
            ->assertStatus(422);
    }

    public function test_approve_posts_balanced_journal_and_marks_sent(): void
    {
        $admin = $this->userWithPermissions(['invoices.create', 'invoices.approve']);
        $created = $this->actingAsToken($admin)->postJson('/api/invoices', $this->payload())->json('data');

        $this->actingAsToken($admin)
            ->postJson("/api/invoices/{$created['id']}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.journal_entry_id', fn ($id) => $id !== null);

        $invoice = Invoice::find($created['id']);
        $this->assertNotNull($invoice->approved_by);

        $entry = JournalEntry::with('lines')->find($invoice->journal_entry_id);
        $this->assertTrue($entry->posted);
        $this->assertSame('invoice', $entry->reference_type);
        $this->assertSame($invoice->id, $entry->reference_id);

        // مدين ذمم 280 = دائن إيراد 250 + دائن ضريبة 30.
        $accounts = new AccountResolver;
        $lineFor = fn (string $role) => $entry->lines->firstWhere('account_id', $accounts->id($role));
        $this->assertSame('280.00', $lineFor(AccountRole::ACCOUNTS_RECEIVABLE)->debit);
        $this->assertSame('250.00', $lineFor(AccountRole::FEE_REVENUE)->credit);
        $this->assertSame('30.00', $lineFor(AccountRole::VAT_PAYABLE)->credit);

        $this->assertSame(
            (float) $entry->lines->sum('debit'),
            (float) $entry->lines->sum('credit'),
        );
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice_approved']);
    }

    public function test_approve_requires_approve_permission(): void
    {
        $admin = $this->userWithPermissions(['invoices.create']);
        $created = $this->actingAsToken($admin)->postJson('/api/invoices', $this->payload())->json('data');

        $this->actingAsToken($admin)
            ->postJson("/api/invoices/{$created['id']}/approve")
            ->assertStatus(403);
    }

    public function test_cannot_approve_zero_total_invoice(): void
    {
        $admin = $this->userWithPermissions(['invoices.create', 'invoices.approve']);
        $created = $this->actingAsToken($admin)->postJson('/api/invoices', $this->payload([
            'items' => [['description' => 'بند صفري', 'quantity' => 1, 'unit_price' => 0, 'tax_rate' => 0]],
        ]))->json('data');

        $this->actingAsToken($admin)
            ->postJson("/api/invoices/{$created['id']}/approve")
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VALIDATION_ERROR');

        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_cannot_update_after_approval(): void
    {
        $admin = $this->userWithPermissions(['invoices.create', 'invoices.approve']);
        $created = $this->actingAsToken($admin)->postJson('/api/invoices', $this->payload())->json('data');
        $this->actingAsToken($admin)->postJson("/api/invoices/{$created['id']}/approve")->assertOk();

        $this->actingAsToken($admin)
            ->putJson("/api/invoices/{$created['id']}", $this->payload())
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VALIDATION_ERROR');
    }

    public function test_update_draft_replaces_items_and_recomputes(): void
    {
        $admin = $this->userWithPermissions(['invoices.create']);
        $created = $this->actingAsToken($admin)->postJson('/api/invoices', $this->payload())->json('data');

        $this->actingAsToken($admin)
            ->putJson("/api/invoices/{$created['id']}", $this->payload([
                'items' => [['description' => 'بند جديد', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0]],
            ]))
            ->assertOk()
            ->assertJsonPath('data.total', '100.00');

        $this->assertDatabaseMissing('invoice_items', ['description' => 'أتعاب']);
        $this->assertDatabaseHas('invoice_items', ['description' => 'بند جديد']);
    }

    public function test_cancel_draft(): void
    {
        $admin = $this->userWithPermissions(['invoices.create']);
        $created = $this->actingAsToken($admin)->postJson('/api/invoices', $this->payload())->json('data');

        $this->actingAsToken($admin)
            ->postJson("/api/invoices/{$created['id']}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_accountant_role_can_create_and_approve(): void
    {
        $this->seed(RbacSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('accountant');

        $created = $this->actingAsToken($user)->postJson('/api/invoices', $this->payload())->assertCreated()->json('data');
        $this->actingAsToken($user)->postJson("/api/invoices/{$created['id']}/approve")->assertOk();
    }
}
