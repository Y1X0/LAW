<?php

namespace Modules\Backup\Support;

use Modules\Backup\Contracts\DatabaseRestorer;
use Symfony\Component\Process\Process;

/**
 * استعادة Postgres عبر pg_restore من نسخة بصيغة custom (‎-Fc‎). ‎--clean --if-exists‎ تُسقط
 * الكائنات الموجودة قبل إعادة إنشائها (استبدال كامل). كلمة المرور عبر بيئة العملية.
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
