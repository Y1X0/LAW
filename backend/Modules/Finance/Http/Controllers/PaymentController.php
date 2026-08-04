<?php

namespace Modules\Finance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Finance\Http\Requests\StorePaymentRequest;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\Payment;
use Modules\Finance\Services\PaymentService;

/**
 * سندات القبض (Finance / Phase 6 · PR-6).
 * قراءة: invoices.view · تسجيل/عكس: payments.create. لا تُحسب مبالغ هنا — كلها من PaymentService.
 * الـIdempotency عبر ترويسة Idempotency-Key.
 */
class PaymentController
{
    public function __construct(private readonly PaymentService $service) {}

    /** GET /api/invoices/{invoice}/payments — سندات الفاتورة. */
    public function index(Invoice $invoice): JsonResponse
    {
        return $this->ok($invoice->payments()->orderByDesc('id')->get());
    }

    /** POST /api/invoices/{invoice}/payments — تسجيل سند قبض. */
    public function store(StorePaymentRequest $request, Invoice $invoice): JsonResponse
    {
        $payment = $this->service->record(
            $invoice,
            $request->validated(),
            $request->header('Idempotency-Key'),
            $request,
        );

        return $this->ok($payment, 201);
    }

    /** POST /api/payments/{payment}/reverse — عكس سند (لا حذف). */
    public function reverse(Request $request, Payment $payment): JsonResponse
    {
        return $this->ok($this->service->reverse($payment, $request), 201);
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}
