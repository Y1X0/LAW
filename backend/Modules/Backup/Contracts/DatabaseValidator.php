<?php

namespace Modules\Backup\Contracts;

/**
 * عقد التحقّق من صلاحية أرتيفاكت النسخة قبل اعتبارها مكتملة (F3) — يعزل خطوة الفحص
 * (pg_restore --list) ليكون قابلاً للاختبار (تُستبدَل بمُدقّق وهمي في اختبارات الوحدة).
 */
interface DatabaseValidator
{
    /** يتأكّد أنّ الملف أرشيف نسخة صالح بنيويّاً (يرمي عند التلف/البتر). */
    public function validate(string $path): void;
}
