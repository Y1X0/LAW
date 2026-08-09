<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

/**
 * تصنيف آمن لاستثناءات قاعدة البيانات إلى رسالة عربية واضحة ورمز حالة مناسب،
 * دون تسريب أي SQL أو أسماء جداول/أعمدة أو أثر داخلي إلى الواجهة.
 *
 * يكشف نوع الانتهاك بطريقتين معاً ليعمل على PostgreSQL (الإنتاج) وSQLite (الاختبارات):
 *   • عبر SQLSTATE الخاصّ بـ PostgreSQL (23505 / 23503 / 23502).
 *   • وعبر أنماط رسالة السائق (UNIQUE / FOREIGN KEY / NOT NULL …) على SQLite/MySQL
 *     حيث يكون SQLSTATE عامّاً (23000).
 *
 * الرسالة المُعادة للمستخدم عامّة وآمنة؛ التفاصيل التقنية تبقى في السجلّات فقط.
 */
class DbConstraintError
{
    /**
     * @return array{0:int,1:string,2:string}|null [status, code, message] أو null إن لم يكن
     *                                             استثناء قاعدة بيانات معروفاً (يُعامَل 500 عامّاً).
     */
    public static function classify(Throwable $e): ?array
    {
        if (! $e instanceof QueryException) {
            return null;
        }

        $sqlState = self::sqlState($e);
        // رسالة السائق الخام — تُستخدم لكشف النمط فقط، ولا تُعاد للمستخدم أبداً.
        $raw = $e->getMessage();

        // انتهاك تفرّد (قيمة مكرّرة لمفتاح فريد).
        if ($e instanceof UniqueConstraintViolationException
            || $sqlState === '23505'
            || str_contains($raw, 'UNIQUE constraint failed')
            || str_contains($raw, 'Duplicate entry')
            || str_contains($raw, 'duplicate key value')) {
            return [409, 'CONFLICT_DUPLICATE', 'القيمة المُدخلة مستخدمة مسبقاً. يرجى استخدام قيمة مختلفة.'];
        }

        // انتهاك مفتاح أجنبي (ارتباط بسجلّات أخرى أو مرجع غير موجود).
        if ($sqlState === '23503'
            || str_contains($raw, 'FOREIGN KEY constraint failed')
            || stripos($raw, 'foreign key constraint') !== false) {
            return [409, 'CONFLICT_REFERENCE', 'تعذّر إتمام العملية بسبب ارتباط هذا السجلّ بسجلّات أخرى.'];
        }

        // انتهاك NOT NULL (حقل مطلوب مفقود على مستوى قاعدة البيانات).
        if ($sqlState === '23502'
            || str_contains($raw, 'NOT NULL constraint failed')
            || stripos($raw, 'cannot be null') !== false
            || stripos($raw, 'null value in column') !== false) {
            return [422, 'VALIDATION_ERROR', 'هناك حقل مطلوب مفقود. يرجى تعبئة جميع الحقول المطلوبة.'];
        }

        // استثناء قاعدة بيانات آخر → لا نصنّفه؛ يُعالَج كخطأ 500 عامّ بلا تسريب.
        return null;
    }

    /** SQLSTATE من PDO errorInfo (قد يكون '23000' عامّاً على SQLite/MySQL). */
    private static function sqlState(QueryException $e): ?string
    {
        $info = $e->errorInfo ?? null;

        return is_array($info) ? ($info[0] ?? null) : null;
    }
}
