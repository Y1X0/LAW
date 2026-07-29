<?php

namespace Modules\Attendance\Concerns;

use Modules\Core\Models\AuditLog;

/**
 * تسجيل أحداث تدقيق من عمليات النظام (بلا طلب HTTP) — مثل مهام المزامنة
 * ومعالجة البصمة. user_id يبقى null دلالةً على أن الفاعل هو النظام.
 */
trait RecordsSystemAudit
{
    /**
     * @param  array<string,mixed>|null  $context
     */
    protected function systemAudit(string $action, string $auditableType, int $auditableId, ?array $context = null): void
    {
        AuditLog::create([
            'user_id' => null,
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'new_values' => $context,
        ]);
    }
}
