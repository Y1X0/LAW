<?php

namespace Modules\Backup\Support;

use Modules\Backup\Contracts\DatabaseRestorer;
use Symfony\Component\Process\Process;

/**
 * استعادة Postgres عبر pg_restore من نسخة بصيغة custom (‎-Fc‎). ‎--clean --if-exists‎ تُسقط
 * الكائنات الموجودة قبل إعادة إنشائها (استبدال كامل). كلمة المرور عبر بيئة العملية.
 *
 * منع «النجاح الكاذب» (M4): افتراضياً يتابع pg_restore بعد أخطاء العناصر ويخرج بالرمز 0،
 * فتُستعاد قاعدة ناقصة وتُعتبر ناجحة. لذا:
 *  • ‎--single-transaction‎: كل الاستعادة في معاملة واحدة — إمّا تنجح بالكامل أو تُلغى
 *    ولا تُطبَّق أي تغييرات، فلا تبقى قاعدة نصف مُدمَّرة عند الفشل (يتضمّن هذا ضمناً
 *    ‎--exit-on-error‎). آمن هنا لأنّ النسخة ‎-Fc‎ لقاعدة واحدة لا تحوي أوامر خارج المعاملة
 *    (لا CREATE DATABASE ولا CREATE INDEX CONCURRENTLY).
 *  • ‎--exit-on-error‎: صريحة أيضاً — أي خطأ يوقف الاستعادة ويُرجِع رمز خروج غير صفري
 *    (يلتقطه mustRun فيُرمى استثناء بدل نجاح كاذب).
 */
class PgRestoreRestorer implements DatabaseRestorer
{
    public function restore(string $sourcePath): void
    {
        $c = config('database.connections.'.config('database.default'));

        $process = new Process([
            'pg_restore',
            '--clean',
            '--if-exists',
            '--single-transaction', // ذرّية: كل شيء أو لا شيء (يتضمّن exit-on-error)
            '--exit-on-error',      // صريحة: أي خطأ ⇒ خروج غير صفري (لا نجاح كاذب)
            '--no-owner',
            '--no-privileges',
            '--host='.($c['host'] ?? '127.0.0.1'),
            '--port='.(string) ($c['port'] ?? 5432),
            '--username='.($c['username'] ?? ''),
            '--dbname='.($c['database'] ?? ''),
            $sourcePath,
        ]);
        $process->setEnv(['PGPASSWORD' => (string) ($c['password'] ?? '')]);
        $process->setTimeout(1800);

        $process->mustRun();
    }
}
