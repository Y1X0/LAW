<?php

namespace Modules\Core\Concerns;

use Illuminate\Http\Request;
use Modules\Core\Models\AuditLog;

/**
 * مساعد تسجيل أحداث التدقيق (Append-Only) من المتحكمات/الخدمات.
 */
trait RecordsAudit
{
    protected function recordAudit(
        Request $request,
        string $action,
        string $auditableType,
        int $auditableId,
        ?array $context = null,
    ): void {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'new_values' => $context,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
