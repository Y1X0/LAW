<?php

namespace Modules\Backup\Contracts;

/**
 * عقد تفريغ قاعدة البيانات — يعزل خطوة pg_dump عن تنسيق النسخ ليكون قابلاً للاختبار
 * (تُستبدَل بمُفرِّغ وهمي في الاختبارات دون تشغيل pg_dump حقيقي).
 */
interface DatabaseDumper
{
    /** يكتب تفريغ قاعدة البيانات إلى المسار المحلّي المعطى (يرمي عند الفشل). */
    public function dump(string $targetPath): void;
}
