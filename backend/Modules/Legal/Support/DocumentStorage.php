<?php

namespace Modules\Legal\Support;

use Illuminate\Support\Str;

/**
 * قرار التخزين الخادمي لمستندات القضايا (Legal / Phase 5).
 *
 * مصدر الحقيقة الوحيد للقرص والمسار — لا يُشتقّ أيّ منهما من مدخلات العميل (P0).
 * القرص افتراضاً `r2` (يُستبدَل في الاختبارات/التطوير عبر LEGAL_DOCUMENTS_DISK).
 * المسار: cases/{caseId}/{uuid}.{ext} — بلا اسم الملف الأصلي (يُحفظ في DB فقط).
 */
final class DocumentStorage
{
    public static function disk(): string
    {
        return (string) env('LEGAL_DOCUMENTS_DISK', 'r2');
    }

    /**
     * مسار تخزين خادمي فريد للقضية. الامتداد يُنظَّف إلى أحرف/أرقام صغيرة فقط،
     * ويُهمَل إن كان فارغاً — لا ثقة بامتداد قادم من العميل.
     */
    public static function pathFor(int $caseId, ?string $extension): string
    {
        $ext = strtolower((string) $extension);
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?? '';
        $file = (string) Str::uuid();
        if ($ext !== '') {
            $file .= '.'.$ext;
        }

        return "cases/{$caseId}/{$file}";
    }
}
