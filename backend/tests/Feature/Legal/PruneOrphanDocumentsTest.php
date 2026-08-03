<?php

namespace Tests\Feature\Legal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Legal\Models\CaseDocument;
use Tests\TestCase;

class PruneOrphanDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('r2');
    }

    public function test_dry_run_reports_but_keeps_orphans(): void
    {
        Storage::disk('r2')->put('cases/99/orphan.pdf', 'x');

        $this->artisan('legal:documents:prune-orphans')
            ->expectsOutputToContain('cases/99/orphan.pdf')
            ->assertSuccessful();

        Storage::disk('r2')->assertExists('cases/99/orphan.pdf');
    }

    public function test_force_deletes_orphans_but_keeps_known_files(): void
    {
        // ملف معروف (له صفّ) — يجب أن يبقى.
        Storage::disk('r2')->put('cases/1/known.pdf', 'k');
        CaseDocument::factory()->create(['storage_disk' => 'r2', 'storage_path' => 'cases/1/known.pdf']);
        // ملف يتيم — يجب أن يُحذف.
        Storage::disk('r2')->put('cases/1/orphan.pdf', 'o');

        $this->artisan('legal:documents:prune-orphans --force')->assertSuccessful();

        Storage::disk('r2')->assertExists('cases/1/known.pdf');
        Storage::disk('r2')->assertMissing('cases/1/orphan.pdf');
    }

    public function test_reports_none_when_clean(): void
    {
        Storage::disk('r2')->put('cases/1/known.pdf', 'k');
        CaseDocument::factory()->create(['storage_disk' => 'r2', 'storage_path' => 'cases/1/known.pdf']);

        $this->artisan('legal:documents:prune-orphans --force')
            ->expectsOutputToContain('لا توجد ملفات يتيمة.')
            ->assertSuccessful();
    }
}
