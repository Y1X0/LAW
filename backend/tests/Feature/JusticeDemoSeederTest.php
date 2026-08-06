<?php

namespace Tests\Feature;

use Database\Seeders\JusticeDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\JournalEntry;
use Modules\Legal\Models\Client;
use Modules\Legal\Models\LegalCase;
use Tests\TestCase;

/**
 * يتحقّق أن بذرة العرض «مكتب العدالة» غنيّة وواقعيّة، **idempotent** (تشغيل مزدوج بلا تكرار)،
 * والمالية متزنة عبر الخدمات، والحارس يمنع التشغيل على الإنتاج.
 */
class JusticeDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_rich_and_balanced_demo_and_is_idempotent(): void
    {
        $this->seed(JusticeDemoSeeder::class);

        // ثراء البيانات.
        $this->assertSame(20, LegalCase::where('internal_number', 'like', 'JUS-C-2026-%')->count());
        $this->assertDatabaseHas('users', ['email' => 'owner@justice.law']);
        $this->assertDatabaseHas('users', ['email' => 'lawyer1@justice.law']);
        $this->assertGreaterThanOrEqual(7, Client::count());
        $this->assertSame(6, Invoice::count());
        $this->assertGreaterThanOrEqual(3, Invoice::where('status', 'paid')->count());

        // المالية متزنة: كل قيد مرحّل مجموع مدينه = مجموع دائنه.
        foreach (JournalEntry::with('lines')->get() as $entry) {
            $this->assertEqualsWithDelta(
                (float) $entry->lines->sum('debit'), (float) $entry->lines->sum('credit'), 0.001,
                "قيد غير متزن: {$entry->entry_no}",
            );
        }

        // Idempotent: تشغيل ثانٍ لا يكرّر القضايا ولا الفواتير.
        $this->seed(JusticeDemoSeeder::class);
        $this->assertSame(20, LegalCase::where('internal_number', 'like', 'JUS-C-2026-%')->count());
        $this->assertSame(6, Invoice::count(), 'حارس المالية يمنع تكرار الفواتير عند إعادة التشغيل.');
    }

    public function test_blocked_on_production_by_default(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        // نستدعي البذرة مباشرةً (لا عبر أمر db:seed) لاختبار حارسنا لا تأكيد Laravel الإنتاجي.
        $seeder = new JusticeDemoSeeder;
        $seeder->setContainer($this->app);
        $seeder->run();

        $this->assertDatabaseMissing('users', ['email' => 'owner@justice.law']);
        $this->assertSame(0, LegalCase::where('internal_number', 'like', 'JUS-C-2026-%')->count());
    }
}
