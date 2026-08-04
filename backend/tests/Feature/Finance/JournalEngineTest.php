<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Modules\Finance\Exceptions\ImmutableJournalEntryException;
use Modules\Finance\Exceptions\InvalidJournalEntryException;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\JournalEntry;
use Modules\Finance\Seeders\ChartOfAccountsSeeder;
use Modules\Finance\Services\JournalService;
use Modules\Finance\Support\AccountResolver;
use Modules\Finance\Support\AccountRole;
use Tests\TestCase;

/**
 * محرّك القيد المزدوج (Phase 6 · PR-3): الترحيل الذرّي، المناعة بعد الترحيل،
 * والتحقق داخل الخدمة (توازن، سطران، لا سالب/صفري، حسابات صالحة ونشطة).
 */
class JournalEngineTest extends TestCase
{
    use RefreshDatabase;

    private JournalService $service;

    private AccountResolver $accounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ChartOfAccountsSeeder::class);
        $this->accounts = new AccountResolver;
        $this->service = new JournalService;
    }

    /** قيد فاتورة نموذجي متوازن: مدين ذمم = دائن إيراد + ضريبة. */
    private function invoiceLines(float $net = 100, float $vat = 15): array
    {
        return [
            ['account_id' => $this->accounts->id(AccountRole::ACCOUNTS_RECEIVABLE), 'debit' => $net + $vat],
            ['account_id' => $this->accounts->id(AccountRole::FEE_REVENUE), 'credit' => $net],
            ['account_id' => $this->accounts->id(AccountRole::VAT_PAYABLE), 'credit' => $vat],
        ];
    }

    public function test_post_creates_balanced_entry_atomically(): void
    {
        $entry = $this->service->post(
            ['description' => 'فاتورة تجريبية', 'reference_type' => 'invoice', 'reference_id' => 7],
            $this->invoiceLines(),
        );

        $this->assertTrue($entry->posted);
        $this->assertSame('JE-'.str_pad((string) $entry->id, 6, '0', STR_PAD_LEFT), $entry->entry_no);
        $this->assertNotNull($entry->posted_at);
        $this->assertCount(3, $entry->lines);
        $this->assertSame('invoice', $entry->reference_type);

        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseCount('journal_lines', 3);
    }

    public function test_post_records_actor_and_audit(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/api/finance/test', 'POST');
        $request->setUserResolver(fn () => $user);

        $entry = $this->service->post(['description' => 'مع مستخدم'], $this->invoiceLines(), $request);

        $this->assertSame($user->id, $entry->created_by);
        $this->assertSame($user->id, $entry->posted_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'journal_posted', 'auditable_id' => $entry->id]);
    }

    public function test_post_rejects_unbalanced_entry_and_persists_nothing(): void
    {
        try {
            $this->service->post([], [
                ['account_id' => $this->accounts->id(AccountRole::ACCOUNTS_RECEIVABLE), 'debit' => 100],
                ['account_id' => $this->accounts->id(AccountRole::FEE_REVENUE), 'credit' => 90],
            ]);
            $this->fail('كان يجب رفض القيد غير المتوازن.');
        } catch (InvalidJournalEntryException) {
            // متوقّع.
        }

        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('journal_lines', 0);
    }

    public function test_post_rejects_fewer_than_two_lines(): void
    {
        $this->expectException(InvalidJournalEntryException::class);
        $this->service->post([], [
            ['account_id' => $this->accounts->id(AccountRole::ACCOUNTS_RECEIVABLE), 'debit' => 100],
        ]);
    }

    public function test_post_rejects_negative_amount(): void
    {
        $this->expectException(InvalidJournalEntryException::class);
        $this->service->post([], [
            ['account_id' => $this->accounts->id(AccountRole::ACCOUNTS_RECEIVABLE), 'debit' => -100],
            ['account_id' => $this->accounts->id(AccountRole::FEE_REVENUE), 'credit' => -100],
        ]);
    }

    public function test_post_rejects_line_with_both_debit_and_credit(): void
    {
        $this->expectException(InvalidJournalEntryException::class);
        $this->service->post([], [
            ['account_id' => $this->accounts->id(AccountRole::ACCOUNTS_RECEIVABLE), 'debit' => 100, 'credit' => 100],
            ['account_id' => $this->accounts->id(AccountRole::FEE_REVENUE), 'credit' => 100],
        ]);
    }

    public function test_post_rejects_zero_only_line(): void
    {
        $this->expectException(InvalidJournalEntryException::class);
        $this->service->post([], [
            ['account_id' => $this->accounts->id(AccountRole::ACCOUNTS_RECEIVABLE), 'debit' => 0, 'credit' => 0],
            ['account_id' => $this->accounts->id(AccountRole::FEE_REVENUE), 'credit' => 0],
        ]);
    }

    public function test_post_rejects_unknown_account(): void
    {
        $this->expectException(InvalidJournalEntryException::class);
        $this->service->post([], [
            ['account_id' => 999999, 'debit' => 100],
            ['account_id' => $this->accounts->id(AccountRole::FEE_REVENUE), 'credit' => 100],
        ]);
    }

    public function test_post_rejects_inactive_account(): void
    {
        $inactive = Account::factory()->inactive()->create();

        $this->expectException(InvalidJournalEntryException::class);
        $this->service->post([], [
            ['account_id' => $inactive->id, 'debit' => 100],
            ['account_id' => $this->accounts->id(AccountRole::FEE_REVENUE), 'credit' => 100],
        ]);
    }

    public function test_posted_entry_cannot_be_updated(): void
    {
        $entry = $this->service->post([], $this->invoiceLines());

        $this->expectException(ImmutableJournalEntryException::class);
        $entry->update(['description' => 'محاولة تعديل']);
    }

    public function test_posted_entry_cannot_be_deleted(): void
    {
        $entry = $this->service->post([], $this->invoiceLines());

        $this->expectException(ImmutableJournalEntryException::class);
        $entry->delete();
    }

    public function test_posted_line_cannot_be_updated(): void
    {
        $entry = $this->service->post([], $this->invoiceLines());
        $line = $entry->lines->first();

        $this->expectException(ImmutableJournalEntryException::class);
        $line->update(['debit' => 999]);
    }

    public function test_reverse_creates_mirror_entry_and_leaves_original_intact(): void
    {
        $original = $this->service->post(['reference_type' => 'invoice', 'reference_id' => 3], $this->invoiceLines());

        $reversal = $this->service->reverse($original);

        // القيد العكسي يقلب المدين/الدائن ويشير إلى الأصل، ومتوازن بحكم البناء.
        $this->assertSame($original->id, $reversal->reversal_of_id);
        $this->assertTrue($reversal->posted);
        $this->assertCount(3, $reversal->lines);

        $originalDebit = $original->lines->sum('debit');
        $reversalCredit = $reversal->lines->sum('credit');
        $this->assertSame((float) $originalDebit, (float) $reversalCredit);

        // صافي الأثر لكل حساب صفر (الأصل + العكس).
        $arId = $this->accounts->id(AccountRole::ACCOUNTS_RECEIVABLE);
        $net = JournalEntry::whereIn('id', [$original->id, $reversal->id])
            ->with('lines')->get()->flatMap->lines
            ->where('account_id', $arId)
            ->sum(fn ($l) => (float) $l->debit - (float) $l->credit);
        $this->assertSame(0.0, $net);

        // الأصل لم يُمَس ويظهر كمُعكوس.
        $this->assertTrue($original->fresh()->posted);
        $this->assertTrue($original->fresh()->isReversed());
    }

    public function test_reverse_rejects_double_reversal(): void
    {
        $original = $this->service->post([], $this->invoiceLines());
        $this->service->reverse($original);

        $this->expectException(InvalidJournalEntryException::class);
        $this->service->reverse($original->fresh());
    }
}
