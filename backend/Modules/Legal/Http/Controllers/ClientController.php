<?php

namespace Modules\Legal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Legal\Http\Requests\StoreClientRequest;
use Modules\Legal\Http\Requests\UpdateClientRequest;
use Modules\Legal\Models\Client;
use Modules\Legal\Services\ClientService;

/**
 * إدارة العملاء (Legal / LC-1). قراءة: clients.view · إنشاء: clients.create · تعديل/حالة: clients.update.
 * لا حذف فعلي — التعطيل عبر تغيير الحالة.
 */
class ClientController
{
    public function __construct(private readonly ClientService $service) {}

    /** GET /api/clients — قائمة مع بحث وتصفية وترقيم (تُخفي المعطّلين افتراضياً). */
    public function index(Request $request): JsonResponse
    {
        $query = Client::query();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('national_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        } elseif (! $request->boolean('include_inactive')) {
            $query->where('status', 'active');
        }

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
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

    /** GET /api/clients/{client} */
    public function show(Client $client): JsonResponse
    {
        return $this->ok($client);
    }

    /** POST /api/clients */
    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = $this->service->create($request->validated(), $request);

        return $this->ok($client, 201);
    }

    /** PUT /api/clients/{client} */
    public function update(UpdateClientRequest $request, Client $client): JsonResponse
    {
        $client = $this->service->update($client, $request->validated(), $request);

        return $this->ok($client);
    }

    /** PATCH /api/clients/{client}/status — تفعيل/تعطيل (بديل الحذف). */
    public function setStatus(Request $request, Client $client): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Client::STATUSES)],
        ]);
        $client = $this->service->setStatus($client, $data['status'], $request);

        return $this->ok($client);
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}
