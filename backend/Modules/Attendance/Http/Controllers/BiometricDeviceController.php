<?php

namespace Modules\Attendance\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Attendance\Biometric\BiometricAdapterFactory;
use Modules\Attendance\Jobs\SyncBiometricDeviceJob;
use Modules\Attendance\Models\AttendanceLog;
use Modules\Attendance\Models\BiometricDevice;
use Modules\Core\Concerns\RecordsAudit;
use Modules\HR\Models\Employee;

/**
 * إدارة أجهزة البصمة (Issue #16): تسجيل/تحديث الأجهزة، تسجيل المستخدمين،
 * إطلاق المزامنة، وعرض سجلات Staging (بما فيها قائمة غير المطابَق لـ HR).
 * محميّة بصلاحية attendance.devices.
 */
class BiometricDeviceController
{
    use RecordsAudit;

    public function __construct(private readonly BiometricAdapterFactory $factory) {}

    /** GET /api/biometric/devices — قائمة الأجهزة (بلا كشف التوكن). */
    public function index(Request $request): JsonResponse
    {
        $query = BiometricDevice::query()->with('branch:id,name');

        foreach (['vendor', 'status', 'branch_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        $perPage = min((int) $request->query('per_page', 20), 100);
        $page = $query->orderBy('name')->paginate($perPage);

        return $this->paginated($page);
    }

    /** POST /api/biometric/devices — تسجيل جهاز جديد (يعيد التوكن مرة واحدة للإعداد). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'vendor' => ['required', Rule::in(BiometricDevice::VENDORS)],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'api_mode' => ['required', Rule::in(BiometricDevice::API_MODES)],
            'ip_address' => ['nullable', 'ip'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'serial_number' => ['nullable', 'string', 'max:80', 'unique:biometric_devices,serial_number'],
        ]);

        // توكن الجهاز يُولَّد ويُخزَّن مشفّراً؛ يُعرَض هنا فقط ليُضبط على الجهاز.
        $plainToken = Str::random(48);
        $data['auth_token'] = $plainToken;
        $data['is_active'] = true;

        $device = BiometricDevice::create($data);

        $this->recordAudit($request, 'biometric_device_registered', BiometricDevice::class, $device->id, [
            'vendor' => $device->vendor, 'api_mode' => $device->api_mode,
        ]);

        return response()->json([
            'data' => array_merge($device->load('branch:id,name')->toArray(), ['auth_token' => $plainToken]),
            'meta' => ['note' => 'التوكن يُعرَض مرة واحدة فقط — احفظه واضبطه على الجهاز.'],
            'errors' => null,
        ], 201);
    }

    /** PUT /api/biometric/devices/{device} — تحديث بيانات الجهاز. */
    public function update(Request $request, BiometricDevice $device): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'api_mode' => ['sometimes', Rule::in(BiometricDevice::API_MODES)],
            'ip_address' => ['nullable', 'ip'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $device->update($data);

        $this->recordAudit($request, 'biometric_device_updated', BiometricDevice::class, $device->id, $data);

        return $this->ok($device->fresh(['branch:id,name']));
    }

    /** POST /api/biometric/devices/{device}/enroll — دفع موظف (biometric_user_id) للجهاز. */
    public function enroll(Request $request, BiometricDevice $device): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
        ]);
        $employee = Employee::findOrFail($data['employee_id']);

        abort_if($employee->biometric_user_id === null, 422, 'لا يوجد biometric_user_id للموظف.');

        $enrolled = $this->factory->make($device)->enrollUser($employee);

        $this->recordAudit($request, 'biometric_user_enrolled', BiometricDevice::class, $device->id, [
            'employee_id' => $employee->id,
            'biometric_user_id' => $employee->biometric_user_id,
            'enrolled' => $enrolled,
        ]);

        return $this->ok(['device_id' => $device->id, 'employee_id' => $employee->id, 'enrolled' => $enrolled]);
    }

    /** POST /api/biometric/devices/{device}/sync — إطلاق مزامنة يدوية (Pull). */
    public function sync(Request $request, BiometricDevice $device): JsonResponse
    {
        SyncBiometricDeviceJob::dispatch($device->id);

        $this->recordAudit($request, 'biometric_sync_triggered', BiometricDevice::class, $device->id);

        return $this->ok(['device_id' => $device->id, 'dispatched' => true]);
    }

    /** GET /api/biometric/logs — سجلات Staging مع تصفية (status=unmatched = قائمة عمل HR). */
    public function logs(Request $request): JsonResponse
    {
        $query = AttendanceLog::query()->with(['device:id,name', 'employee:id,full_name_ar,employee_no']);

        foreach (['device_id', 'status', 'employee_id', 'biometric_user_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }
        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('punch_time', [$request->query('from'), $request->query('to')]);
        }

        $perPage = min((int) $request->query('per_page', 20), 100);
        $page = $query->orderByDesc('punch_time')->paginate($perPage);

        return $this->paginated($page);
    }

    private function ok($data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => null, 'errors' => null], $status);
    }

    private function paginated($page): JsonResponse
    {
        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'page' => $page->currentPage(), 'per_page' => $page->perPage(),
                'total' => $page->total(), 'total_pages' => $page->lastPage(),
            ],
            'errors' => null,
        ]);
    }
}
