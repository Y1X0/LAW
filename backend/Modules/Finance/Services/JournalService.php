<?php

namespace Modules\Finance\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Core\Concerns\RecordsAudit;
use Modules\Finance\Exceptions\InvalidJournalEntryException;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\JournalEntry;

/**
 * محرّك القيد المزدوج (Finance / Phase 6 · PR-3).
 *
 * ثلاث ضمانات غير قابلة للتفاوض، مفروضة داخل الخدمة نفسها لا في الواجهة:
 *  1) الترحيل ذرّي: الرأس وكل السطور داخل Transaction واحدة — لا قيد بلا سطوره ولا العكس.
 *  2) لا تعديل بعد الترحيل: القيد المُرحَّل نهائي (يفرضه النموذج) — التصحيح بقيد عكس فقط.
 *  3) التحقق داخل الخدمة: مدين=دائن، سطران على الأقل، لا قيم سالبة/صفرية، حسابات صالحة ونشطة.
 *
 * الحسابات تُمرَّر بمعرّفاتها (account_id)؛ المستدعي يحصل عليها عبر AccountResolver بالدور
 * لا بالرقم — فيبقى المحرّك مستقلاً عن ترقيم دليل الحسابات.
 */
class JournalService
{
    use RecordsAudit;

    /**
     * ترحيل قيد متوازن بشكل ذرّي. يرمي InvalidJournalEntryException عند مخالفة أي قاعدة.
     *
     * @param  array{entry_date?:string, description?:string, reference_type?:?string, reference_id?:?int, reversal_of_id?:?int}  $meta
     * @param  array<int, array{account_id:int, debit?:int|float|string, credit?:int|float|string, notes?:?string}>  $lines
     */
    public function post(array $meta, array $lines, ?Request $request = null): JournalEntry
    {
        $normalized = $this->validate($lines);

        return DB::transaction(function () use ($meta, $normalized, $request): JournalEntry {
            $actorId = $request?->user()?->id;

            $entry = JournalEntry::create([
                'entry_no' => null,
                'entry_date' => $meta['entry_date'] ?? now()->toDateString(),
                'description' => $meta['description'] ?? null,
                'reference_type' => $meta['reference_type'] ?? null,
                'reference_id' => $meta['reference_id'] ?? null,
                'reversal_of_id' => $meta['reversal_of_id'] ?? null,
                'posted' => false,
                'created_by' => $actorId,
            ]);

            foreach ($normalized as $line) {
                $entry->lines()->create($line);
            }

            // ترقيم من المعرّف (فريد بلا سباق) + ختم الترحيل — آخر تعديل مسموح قبل التجميد.
            $entry->forceFill([
                'entry_no' => $this->formatEntryNo($entry->id),
                'posted' => true,
                'posted_at' => now(),
                'posted_by' => $actorId,
            ])->save();

            if ($request !== null) {
                $this->recordAudit($request, 'journal_posted', JournalEntry::class, $entry->id, [
                    'entry_no' => $entry->entry_no,
                    'reference_type' => $entry->reference_type,
                    'reference_id' => $entry->reference_id,
                ]);
            }

            return $entry->load('lines');
        });
    }

    /**
     * عكس قيد مُرحَّل بإنشاء قيد جديد يقلب المدين/الدائن ويشير إلى الأصل. الأصل يبقى كما هو
     * (لا يُلمَس حفاظاً على مناعته). يُرفض العكس المزدوج أو عكس قيد غير مُرحَّل.
     */
    public function reverse(JournalEntry $entry, ?Request $request = null, ?string $reason = null): JournalEntry
    {
        if (! $entry->posted) {
            throw new InvalidJournalEntryException('لا يمكن عكس قيد غير مُرحَّل.');
        }

        if ($entry->isReversed()) {
            throw new InvalidJournalEntryException('القيد مُعكوس مسبقاً.');
        }

        $mirroredLines = $entry->lines->map(fn ($line) => [
            'account_id' => $line->account_id,
            'debit' => $line->credit,
            'credit' => $line->debit,
            'notes' => $line->notes,
        ])->all();

        $description = 'عكس قيد '.$entry->entry_no.($reason !== null && $reason !== '' ? ' — '.$reason : '');

        return DB::transaction(function () use ($entry, $mirroredLines, $description, $request): JournalEntry {
            $reversal = $this->post([
                'entry_date' => now()->toDateString(),
                'description' => $description,
                'reference_type' => $entry->reference_type,
                'reference_id' => $entry->reference_id,
                'reversal_of_id' => $entry->id,
            ], $mirroredLines, $request);

            if ($request !== null) {
                $this->recordAudit($request, 'journal_reversed', JournalEntry::class, $entry->id, [
                    'reversed_by_entry' => $reversal->entry_no,
                ]);
            }

            return $reversal;
        });
    }

    /**
     * يتحقّق من القواعد الثلاث ويُعيد السطور مطبَّعة. لا يمسّ قاعدة البيانات إلا لفحص الحسابات.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array{account_id:int, debit:float, credit:float, notes:?string}>
     */
    private function validate(array $lines): array
    {
        if (count($lines) < 2) {
            throw new InvalidJournalEntryException('يجب أن يحتوي القيد على سطرين على الأقل.');
        }

        $normalized = [];
        $debitCents = 0;
        $creditCents = 0;
        $accountIds = [];

        foreach ($lines as $index => $line) {
            $accountId = $line['account_id'] ?? null;
            if (! is_int($accountId) && ! (is_string($accountId) && ctype_digit($accountId))) {
                throw new InvalidJournalEntryException("سطر {$index}: معرّف حساب غير صالح.");
            }
            $accountId = (int) $accountId;

            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);

            if ($debit < 0 || $credit < 0) {
                throw new InvalidJournalEntryException("سطر {$index}: لا يُسمح بقيم سالبة.");
            }
            if ($debit > 0 && $credit > 0) {
                throw new InvalidJournalEntryException("سطر {$index}: السطر إمّا مدين أو دائن، لا كلاهما.");
            }
            if ($debit === 0.0 && $credit === 0.0) {
                throw new InvalidJournalEntryException("سطر {$index}: لا يُسمح بسطر بقيمة صفرية.");
            }

            $debitCents += (int) round($debit * 100);
            $creditCents += (int) round($credit * 100);
            $accountIds[] = $accountId;

            $normalized[] = [
                'account_id' => $accountId,
                'debit' => $debit,
                'credit' => $credit,
                'notes' => $line['notes'] ?? null,
            ];
        }

        if ($debitCents !== $creditCents) {
            throw new InvalidJournalEntryException('القيد غير متوازن: مجموع المدين لا يساوي مجموع الدائن.');
        }

        $this->assertAccountsUsable($accountIds);

        return $normalized;
    }

    /** كل الحسابات المستخدمة موجودة ونشطة. */
    private function assertAccountsUsable(array $accountIds): void
    {
        $uniqueIds = array_values(array_unique($accountIds));
        $accounts = Account::whereIn('id', $uniqueIds)->get(['id', 'is_active']);

        if ($accounts->count() !== count($uniqueIds)) {
            throw new InvalidJournalEntryException('أحد الحسابات المستخدمة غير موجود.');
        }

        if ($accounts->contains(fn (Account $account) => ! $account->is_active)) {
            throw new InvalidJournalEntryException('لا يمكن الترحيل إلى حساب غير نشط.');
        }
    }

    private function formatEntryNo(int $id): string
    {
        return 'JE-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }
}
