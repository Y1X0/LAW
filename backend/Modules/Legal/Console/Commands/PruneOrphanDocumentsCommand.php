<?php

namespace Modules\Legal\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Modules\Legal\Models\CaseDocument;
use Modules\Legal\Support\DocumentStorage;

/**
 * تنظيف ملفات الوثائق اليتيمة على التخزين (Legal / Phase 5 · PR-3).
 *
 * ملف يتيم = موجود على قرص المستندات تحت `cases/` بلا صفّ `case_documents` يشير إليه
 * (ينشأ نظريّاً من فشل جزئي نادر أو من حذوفات سابقة قبل ربط حذف الملف). آمن افتراضاً:
 * يبلّغ فقط (dry-run)، ولا يحذف إلا مع `--force`.
 */
class PruneOrphanDocumentsCommand extends Command
{
    protected $signature = 'legal:documents:prune-orphans {--force : احذف الملفات اليتيمة فعليّاً (بدونه يبلّغ فقط)}';

    protected $description = 'يكتشف ملفات وثائق القضايا اليتيمة على التخزين ويحذفها (مع --force)';

    public function handle(): int
    {
        $disk = DocumentStorage::disk();
        $known = CaseDocument::whereNotNull('storage_path')->pluck('storage_path')->all();
        $knownSet = array_flip($known);

        $files = Storage::disk($disk)->allFiles('cases');
        $orphans = array_values(array_filter($files, fn (string $path) => ! isset($knownSet[$path])));

        if ($orphans === []) {
            $this->info('لا توجد ملفات يتيمة.');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $this->warn(sprintf('%d ملف يتيم%s:', count($orphans), $force ? ' (سيُحذف)' : ' (تبليغ فقط — أضف --force للحذف)'));

        $deleted = 0;
        foreach ($orphans as $path) {
            $this->line("  - {$path}");
            if ($force) {
                Storage::disk($disk)->delete($path);
                $deleted++;
            }
        }

        if ($force) {
            $this->info("تم حذف {$deleted} ملف يتيم من القرص «{$disk}».");
        }

        return self::SUCCESS;
    }
}
