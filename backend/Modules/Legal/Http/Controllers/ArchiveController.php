<?php

namespace Modules\Legal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Legal\Concerns\AuthorizesCaseAccess;
use Modules\Legal\Http\Requests\StoreArchiveLocationRequest;
use Modules\Legal\Http\Requests\UpdateArchiveLocationRequest;
use Modules\Legal\Models\CaseArchiveLocation;
use Modules\Legal\Models\LegalCase;
use Modules\Legal\Services\ArchiveService;

/**
 * فهرسة الأرشيف الورقي (Legal / LG-3).
 * صلاحيات مستقلة (archive.*) + العزل يرث القضية (الحارس المشترك).
 */
class ArchiveController
{
    use AuthorizesCaseAccess;

    public function __construct(private readonly ArchiveService $service) {}

    /** GET /api/cases/{case}/archive-locations — مواقع أرشيف القضية (بحارس رؤية القضية). */
    public function index(Request $request, LegalCase $case): JsonResponse
    {
        if ($denied = $this->guardCaseView($request->user(), $case)) {
            return $denied;
        }

        return $this->ok($case->archiveLocations()->orderBy('id')->get());
    }

    /** POST /api/cases/{case}/archive-locations — إضافة موقع (يمكن أكثر من موقع للقضية). */
    public function store(StoreArchiveLocationRequest $request, LegalCase $case): JsonResponse
    {
        if ($denied = $this->guardCaseView($request->user(), $case)) {
            return $denied;
        }
        $location = $this->service->create($case, $request->validated(), $request);

        return $this->ok($location, 201);
    }

    /** PUT /api/archive-locations/{location} */
    public function update(UpdateArchiveLocationRequest $request, CaseArchiveLocation $location): JsonResponse
    {
        if ($denied = $this->guardCaseView($request->user(), $location->case)) {
            return $denied;
        }
        $location = $this->service->update($location, $request->validated(), $request);

        return $this->ok($location);
    }

    /** DELETE /api/archive-locations/{location} — بصلاحية archive.delete + رؤية القضية. */
    public function destroy(Request $request, CaseArchiveLocation $location): JsonResponse
    {
        if ($denied = $this->guardCaseView($request->user(), $location->case)) {
            return $denied;
        }
        $this->service->delete($location, $request);

        return $this->ok(['message' => 'تم حذف موقع الأرشيف.']);
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}
