<?php

namespace Modules\Attendance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Attendance\Models\BiometricDevice;
use Modules\Attendance\Services\BiometricSyncService;
use Modules\Core\Concerns\RecordsAudit;

/**
 * إدارة أجهزة البصمة ومزامنتها (Issue #16). محمي بصلاحية attendance.devices.
 */
class BiometricDeviceController
{
    use RecordsAudit;

    public function __construct(private readonly BiometricSyncService $sync) {}

    public function index(): JsonResponse
    {
        return $this->ok(BiometricDevice::orderBy('name')->get());
    }

    public function show(BiometricDevice $device): JsonResponse
    {
        return $this->ok($device);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $device = BiometricDevice::create($data);
        $this->recordAudit($request, 'biometric_device_created', BiometricDevice::class, $device->id, [
            'name' => $device->name, 'vendor' => $device->vendor,
        ]);

        return $this->ok($device, 201);
    }

    public function update(Request $request, BiometricDevice $device): JsonResponse
    {
        $data = $this->validated($request, false);
        $device->update($data);
        $this->recordAudit($request, 'biometric_device_updated', BiometricDevice::class, $device->id);

        return $this->ok($device);
    }

    public function destroy(Request $request, BiometricDevice $device): JsonResponse
    {
        $id = $device->id;
        $device->delete();
        $this->recordAudit($request, 'biometric_device_deleted', BiometricDevice::class, $id);

        return $this->ok(['message' => 'تم حذف الجهاز.']);
    }

    /** POST /api/biometric/devices/{device}/sync — سحب يدوي (Pull) + معالجة. */
    public function sync(Request $request, BiometricDevice $device): JsonResponse
    {
        return $this->ok($this->sync->pull($device, $request));
    }

    private function validated(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:100'],
            'vendor' => [$creating ? 'required' : 'sometimes', Rule::in(BiometricDevice::VENDORS)],
            'api_mode' => ['nullable', Rule::in(BiometricDevice::API_MODES)],
            'serial_number' => ['nullable', 'string', 'max:80', Rule::unique('biometric_devices', 'serial_number')->ignore($request->route('device')?->id)],
            'ip_address' => ['nullable', 'ip'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'secret' => [$creating ? 'required' : 'sometimes', 'string', 'min:8', 'max:128'],
            'is_active' => ['boolean'],
        ]);
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }
}
