<?php

namespace Modules\Legal\Services;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Core\Concerns\RecordsAudit;
use Modules\Legal\Models\Hearing;
use Modules\Legal\Models\LegalCase;

/**
 * منطق أعمال الجلسات (Legal / LC-3).
 * التأجيل يحافظ على السجل ويُنشئ جلسة جديدة؛ ولا يُعدَّل سجل جلسة held.
 */
class HearingService
{
    use RecordsAudit;

    public function create(LegalCase $case, array $data, Request $request): Hearing
    {
        $data['case_id'] = $case->id;
        $data['created_by'] = $request->user()?->id;
        $hearing = Hearing::create($data);
        $this->recordAudit($request, 'hearing_created', Hearing::class, $hearing->id, ['case_id' => $case->id]);

        return $hearing;
    }

    public function update(Hearing $hearing, array $data, Request $request): Hearing
    {
        $this->guardNotHeld($hearing);
        $hearing->update($data);
        $this->recordAudit($request, 'hearing_updated', Hearing::class, $hearing->id);

        return $hearing;
    }

    /**
     * تأجيل الجلسة: تُصبح القديمة postponed (+ الموعد الجديد والسبب) وتُنشأ جلسة scheduled جديدة.
     *
     * @return array{0: Hearing, 1: Hearing} [القديمة، الجديدة]
     */
    public function postpone(Hearing $hearing, string $newDate, ?string $reason, Request $request): array
    {
        $this->guardNotHeld($hearing);

        $hearing->update([
            'status' => 'postponed',
            'postponed_to' => $newDate,
            'postponed_reason' => $reason,
        ]);

        $new = Hearing::create([
            'case_id' => $hearing->case_id,
            'scheduled_at' => $newDate,
            'type' => $hearing->type,
            'location' => $hearing->location,
            'status' => 'scheduled',
            'created_by' => $request->user()?->id,
        ]);

        $this->recordAudit($request, 'hearing_postponed', Hearing::class, $hearing->id, [
            'new_hearing_id' => $new->id,
            'scheduled_at' => $newDate,
        ]);

        return [$hearing, $new];
    }

    public function cancel(Hearing $hearing, Request $request): Hearing
    {
        $this->guardNotHeld($hearing);
        $hearing->update(['status' => 'cancelled']);
        $this->recordAudit($request, 'hearing_cancelled', Hearing::class, $hearing->id);

        return $hearing;
    }

    /** لا يجوز تعديل/تأجيل/إلغاء جلسة انتهت فعلاً (held) — حفاظاً على السجل القانوني. */
    private function guardNotHeld(Hearing $hearing): void
    {
        if ($hearing->status === 'held') {
            throw ValidationException::withMessages([
                'status' => ['لا يمكن تعديل جلسة منتهية (held). أنشئ جلسة جديدة عند الحاجة.'],
            ]);
        }
    }
}
