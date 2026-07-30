<?php

namespace Modules\Leave\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Leave\Models\LeaveRequest;

/**
 * حدث إلغاء إجازة معتمدة (Issue #17) — نقطة تكامل: تستهلكه وحدة الحضور لاحقاً
 * لإزالة تعليم أيام الإجازة (docs/10 §2.2: إلغاء إجازة معتمدة → تصحيح الحضور).
 */
class LeaveCancelled
{
    use Dispatchable;

    public function __construct(public readonly LeaveRequest $request) {}
}
