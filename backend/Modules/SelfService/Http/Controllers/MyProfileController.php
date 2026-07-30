<?php

namespace Modules\SelfService\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\HR\Services\EmployeeService;

/**
 * ملف الموظف الذاتي (Issue #52). عرض/تعديل محدود بصلاحية profile.update_own.
 * التعديل يمرّ عبر HR (مالك الجدول) ويقتصر على حقول غير حسّاسة — الراتب/القسم/
 * الفرع/الوظيفة/المدير تبقى لـ HR حصراً.
 */
class MyProfileController
{
    public function __construct(private readonly EmployeeService $employees) {}

    /** GET /api/me/profile — ملفي (حقول للعرض + حقول قابلة للتعديل). */
    public function show(Request $request): JsonResponse
    {
        $e = $request->user()->employee;
        $e->loadMissing(['branch:id,name', 'department:id,name', 'manager:id,full_name_ar']);

        return response()->json([
            'data' => [
                // للعرض فقط (تديرها HR)
                'name' => $e->full_name_ar,
                'employee_number' => $e->employee_no,
                'job_title' => $e->job_title,
                'branch' => $e->branch?->name,
                'department' => $e->department?->name,
                'manager' => $e->manager?->full_name_ar,
                // قابلة للتعديل الذاتي
                'phone' => $e->phone,
                'address' => $e->address,
                'photo_path' => $e->photo_path,
                'emergency_contact_name' => $e->emergency_contact_name,
                'emergency_contact_phone' => $e->emergency_contact_phone,
            ],
            'meta' => null, 'errors' => null,
        ]);
    }

    /** PATCH /api/me/profile — تعديل حقولي غير الحسّاسة فقط. */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'photo_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
        ]);

        $employee = $this->employees->updateSelfProfile($request->user()->employee, $data, $request);

        return response()->json([
            'data' => [
                'phone' => $employee->phone,
                'address' => $employee->address,
                'photo_path' => $employee->photo_path,
                'emergency_contact_name' => $employee->emergency_contact_name,
                'emergency_contact_phone' => $employee->emergency_contact_phone,
            ],
            'meta' => null, 'errors' => null,
        ]);
    }
}
