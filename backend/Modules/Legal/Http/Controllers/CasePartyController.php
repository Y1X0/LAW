<?php

namespace Modules\Legal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Legal\Concerns\AuthorizesCaseAccess;
use Modules\Legal\Http\Requests\StoreCasePartyRequest;
use Modules\Legal\Models\LegalCase;
use Modules\Legal\Services\CasePartyService;

/**
 * أطراف القضية (Legal / LG-2). القراءة ترث عزل القضية؛ الإضافة تحت cases.update.
 */
class CasePartyController
{
    use AuthorizesCaseAccess;

    public function __construct(private readonly CasePartyService $service) {}

    /** GET /api/cases/{case}/parties — أطراف القضية (بحارس رؤية القضية). */
    public function index(Request $request, LegalCase $case): JsonResponse
    {
        if ($denied = $this->guardCaseView($request->user(), $case)) {
            return $denied;
        }

        return $this->ok($case->parties()->orderBy('id')->get());
    }

    /** POST /api/cases/{case}/parties — إضافة طرف (يُسجَّل تلقائياً في الخط الزمني). */
    public function store(StoreCasePartyRequest $request, LegalCase $case): JsonResponse
    {
        if ($denied = $this->guardCaseView($request->user(), $case)) {
            return $denied;
        }
        $party = $this->service->create($case, $request->validated(), $request);

        return $this->ok($party, 201);
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}
