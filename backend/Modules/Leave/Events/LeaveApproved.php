<?php

namespace Modules\Leave\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Leave\Models\LeaveRequest;

/**
 * حدث اعتماد إجازة (Issue #17) — نقطة تكامل: تستهلكه وحدة الحضور لاحقاً لتعليم
 * أيام الإجازة كـ leave (docs/03 أ-2). التواصل عبر الأحداث احتراماً لحدود الوحدات.
 */
class LeaveApproved
{
    use Dispatchable;

    public function __construct(public readonly LeaveRequest $request) {}
}
