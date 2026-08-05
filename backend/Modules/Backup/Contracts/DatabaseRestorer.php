<?php

namespace Modules\Backup\Contracts;

/**
 * عقد استعادة قاعدة البيانات من ملف نسخة — يعزل خطوة pg_restore ليكون قابلاً للاختبار
 * (مُستعيد وهمي في اختبارات الوحدة؛ pg_restore حقيقي في اختبار الاستعادة الحيّ).
 */
interface DatabaseRestorer
{
    /** يستعيد قاعدة البيانات من ملف النسخة المحلّي (عملية مدمِّرة — يرمي عند الفشل). */
    public function restore(string $sourcePath): void;
}
