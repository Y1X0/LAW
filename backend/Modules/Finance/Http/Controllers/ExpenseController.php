<?php

namespace Modules\Finance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Finance\Http\Requests\StoreExpenseRequest;
use Modules\Finance\Models\Expense;
use Modules\Finance\Services\ExpenseService;
use Modules\Legal\Concerns\AuthorizesCaseAccess;
use Modules\Legal\Models\LegalCase;

/**
 * سندات الصرف (Finance / Phase 6 · PR-8). قراءة/تسجيل/عكس: expenses.create.
 * عند ربط المصروف بقضية يُطبَّق عزل القضايا نفسه — لا ربط بقضية بلا وصول.
 */
class ExpenseController
{
    use AuthorizesCaseAccess;

    public function __construct(private readonly ExpenseService $service) {}

    /** GET /api/expenses — قائمة مع تصفية وترقيم. */
    public function index(Request $request): JsonResponse
    {
        $query = Expense::query()->with('category:id,name');

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->query('category_id'));
        }
        if ($request->filled('case_id')) {
            $query->where('case_id', (int) $request->query('case_id'));
        }

        $perPage = min((int) $request->query('per_page', 15), 100);
        $page = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'total_pages' => $page->lastPage(),
            ],
            'errors' => null,
        ]);
    }

    /** POST /api/expenses — تسجيل سند صرف (مع عزل القضية إن رُبط). */
    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (! empty($data['case_id'])) {
            $case = LegalCase::findOrFail($data['case_id']);
            if ($denied = $this->guardCaseView($request->user(), $case)) {
                return $denied;
            }
        }

        return $this->ok($this->service->record($data, $request), 201);
    }

    /** POST /api/expenses/{expense}/reverse — عكس سند (لا حذف). */
    public function reverse(Request $request, Expense $expense): JsonResponse
    {
        return $this->ok($this->service->reverse($expense, $request), 201);
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}
