<?php

namespace Tests\Feature\Finance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Finance\Models\ExpenseCategory;
use Modules\Finance\Models\FinancialAccount;
use Modules\Finance\Models\Tax;
use Modules\Finance\Seeders\FinanceReferenceSeeder;
use Tests\TestCase;

/**
 * أساس وحدة Finance (Phase 6 · PR-1): الجداول المرجعية + البذرة الآمنة Idempotent.
 */
class FinanceFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_create_finance_reference_tables(): void
    {
        $this->assertTrue(Schema::hasTable('financial_accounts'));
        $this->assertTrue(Schema::hasTable('expense_categories'));
        $this->assertTrue(Schema::hasTable('taxes'));
    }

    public function test_reference_seeder_seeds_expected_defaults(): void
    {
        $this->seed(FinanceReferenceSeeder::class);

        $vat = Tax::where('name', Tax::VAT)->first();
        $this->assertNotNull($vat);
        $this->assertSame('15.00', (string) $vat->rate);
        $this->assertTrue($vat->is_active);

        $cash = FinancialAccount::where('name', FinanceReferenceSeeder::DEFAULT_CASH_ACCOUNT)->first();
        $this->assertNotNull($cash);
        $this->assertSame('cash', $cash->type);
        $this->assertSame('SAR', $cash->currency);

        $this->assertSame(
            count(FinanceReferenceSeeder::EXPENSE_CATEGORIES),
            ExpenseCategory::count(),
        );
    }

    public function test_reference_seeder_is_idempotent(): void
    {
        $this->seed(FinanceReferenceSeeder::class);
        $this->seed(FinanceReferenceSeeder::class);

        $this->assertSame(1, Tax::where('name', Tax::VAT)->count());
        $this->assertSame(1, FinancialAccount::where('name', FinanceReferenceSeeder::DEFAULT_CASH_ACCOUNT)->count());
        $this->assertSame(count(FinanceReferenceSeeder::EXPENSE_CATEGORIES), ExpenseCategory::count());
    }

    public function test_models_apply_casts_and_defaults(): void
    {
        $account = FinancialAccount::factory()->create();
        $this->assertIsBool($account->is_active);
        $this->assertSame('SAR', $account->currency);
        $this->assertSame('0.00', (string) $account->opening_balance);

        $bank = FinancialAccount::factory()->bank()->create();
        $this->assertSame('bank', $bank->type);

        $tax = Tax::factory()->create();
        $this->assertSame('15.00', (string) $tax->rate);
        $this->assertIsBool($tax->is_active);

        $category = ExpenseCategory::factory()->create();
        $this->assertIsBool($category->is_active);
    }
}
