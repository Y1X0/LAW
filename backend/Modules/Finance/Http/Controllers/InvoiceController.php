<?php

namespace Modules\Finance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Finance\Http\Requests\StoreInvoiceRequest;
use Modules\Finance\Http\Requests\UpdateInvoiceRequest;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Services\InvoiceService;

/**
 * الفواتير (Finance / Phase 6 · PR-4).
 * قراءة: invoices.view · إنشاء/تعديل مسودّة/إلغاء: invoices.create · اعتماد: invoices.approve.
 * لا تُحسب أي مبالغ هنا — كلها من InvoiceService.
 */
class InvoiceController
{
    public function __construct(private readonly InvoiceService $service) {}

    /** GET /api/invoices — قائمة مع تصفية وترقيم. */
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::query()->with('client:id,name');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', (int) $request->query('client_id'));
        }
        if ($request->filled('case_id')) {
            $query->where('case_id', (int) $request->query('case_id'));
        }
        if ($search = $request->query('search')) {
            $query->whereRaw('LOWER(invoice_no) LIKE ?', ['%'.mb_strtolower($search).'%']);
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

    /** GET /api/invoices/{invoice} */
    public function show(Invoice $invoice): JsonResponse
    {
        return $this->ok($invoice->load('items', 'client:id,name', 'journalEntry'));
    }

    /** POST /api/invoices — إنشاء مسودّة. */
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        return $this->ok($this->service->create($request->validated(), $request), 201);
    }

    /** PUT /api/invoices/{invoice} — تعديل مسودّة (استبدال البنود). */
    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        return $this->ok($this->service->update($invoice, $request->validated(), $request));
    }

    /** POST /api/invoices/{invoice}/approve — اعتماد وترحيل قيد الإيراد. */
    public function approve(Request $request, Invoice $invoice): JsonResponse
    {
        return $this->ok($this->service->approve($invoice, $request));
    }

    /** POST /api/invoices/{invoice}/cancel — إلغاء مسودّة. */
    public function cancel(Request $request, Invoice $invoice): JsonResponse
    {
        return $this->ok($this->service->cancel($invoice, $request));
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}
