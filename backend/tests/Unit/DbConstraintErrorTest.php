<?php

namespace Tests\Unit;

use App\Support\DbConstraintError;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * تصنيف أخطاء قاعدة البيانات إلى رسالة عربية آمنة ورمز حالة مناسب — دون تسريب SQL.
 * يغطّي مساري الكشف: SQLSTATE الخاصّ بـ PostgreSQL، وأنماط رسالة SQLite/MySQL.
 */
class DbConstraintErrorTest extends TestCase
{
    /** يبني QueryException مُفبركاً بـ SQLSTATE ورسالة سائق محدّدين. */
    private function queryException(string $sqlState, string $driverMessage, string $class = QueryException::class): QueryException
    {
        $pdo = new PDOException($driverMessage);
        $pdo->errorInfo = [$sqlState, 1, $driverMessage];

        return new $class('pgsql', 'insert into "cases" ("internal_number") values (?)', ['CASE-1'], $pdo);
    }

    public function test_non_database_exception_is_not_classified(): void
    {
        $this->assertNull(DbConstraintError::classify(new RuntimeException('خطأ عادي')));
    }

    public function test_postgres_unique_violation_maps_to_409_duplicate(): void
    {
        $e = $this->queryException('23505', 'duplicate key value violates unique constraint "cases_internal_number_unique"', UniqueConstraintViolationException::class);

        [$status, $code, $message] = DbConstraintError::classify($e);

        $this->assertSame(409, $status);
        $this->assertSame('CONFLICT_DUPLICATE', $code);
        $this->assertStringContainsString('مستخدمة مسبقاً', $message);
    }

    public function test_sqlite_unique_violation_maps_to_409_duplicate(): void
    {
        // على SQLite يكون SQLSTATE عامّاً (23000) — يُكشف بنمط الرسالة.
        $e = $this->queryException('23000', 'UNIQUE constraint failed: cases.internal_number');

        [$status, $code] = DbConstraintError::classify($e);

        $this->assertSame(409, $status);
        $this->assertSame('CONFLICT_DUPLICATE', $code);
    }

    public function test_postgres_foreign_key_violation_maps_to_409_reference(): void
    {
        $e = $this->queryException('23503', 'update or delete on table "clients" violates foreign key constraint');

        [$status, $code, $message] = DbConstraintError::classify($e);

        $this->assertSame(409, $status);
        $this->assertSame('CONFLICT_REFERENCE', $code);
        $this->assertStringContainsString('ارتباط', $message);
    }

    public function test_sqlite_foreign_key_violation_maps_to_409_reference(): void
    {
        $e = $this->queryException('23000', 'FOREIGN KEY constraint failed');

        [$status, $code] = DbConstraintError::classify($e);

        $this->assertSame(409, $status);
        $this->assertSame('CONFLICT_REFERENCE', $code);
    }

    public function test_postgres_not_null_violation_maps_to_422(): void
    {
        $e = $this->queryException('23502', 'null value in column "title" violates not-null constraint');

        [$status, $code] = DbConstraintError::classify($e);

        $this->assertSame(422, $status);
        $this->assertSame('VALIDATION_ERROR', $code);
    }

    public function test_sqlite_not_null_violation_maps_to_422(): void
    {
        $e = $this->queryException('23000', 'NOT NULL constraint failed: cases.title');

        [$status, $code] = DbConstraintError::classify($e);

        $this->assertSame(422, $status);
        $this->assertSame('VALIDATION_ERROR', $code);
    }

    public function test_unknown_database_error_is_not_classified(): void
    {
        // خطأ قاعدة بيانات آخر (مثل مهلة/بناء جملة) → null ⇒ يُعامَل 500 عامّاً بلا تسريب.
        $e = $this->queryException('42601', 'syntax error at or near "SELCT"');

        $this->assertNull(DbConstraintError::classify($e));
    }

    public function test_returned_message_never_contains_sql_or_table_internals(): void
    {
        $e = $this->queryException('23505', 'duplicate key value violates unique constraint "cases_internal_number_unique"', UniqueConstraintViolationException::class);

        [, , $message] = DbConstraintError::classify($e);

        $this->assertStringNotContainsString('cases_internal_number_unique', $message);
        $this->assertStringNotContainsString('insert into', $message);
        $this->assertStringNotContainsString('constraint', $message);
    }
}
