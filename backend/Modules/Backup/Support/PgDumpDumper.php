<?php

namespace Modules\Backup\Support;

use Modules\Backup\Contracts\DatabaseDumper;
use Symfony\Component\Process\Process;

/**
 * تفريغ Postgres عبر pg_dump بصيغة custom (‎-Fc‎، مضغوطة وقابلة لـ pg_restore الانتقائي).
 * يقرأ بيانات الاتصال من إعداد قاعدة البيانات؛ كلمة المرور عبر بيئة العملية (لا في سطر الأوامر).
 */
class PgDumpDumper implements DatabaseDumper
{
    public function dump(string $targetPath): void
    {
        $c = config('database.connections.'.config('database.default'));

        $process = new Process([
            'pg_dump',
            '-Fc',
            '--no-owner',
            '--no-privileges',
            '--host='.($c['host'] ?? '127.0.0.1'),
            '--port='.(string) ($c['port'] ?? 5432),
            '--username='.($c['username'] ?? ''),
            '--dbname='.($c['database'] ?? ''),
            '--file='.$targetPath,
        ]);
        $process->setEnv(['PGPASSWORD' => (string) ($c['password'] ?? '')]);
        $process->setTimeout(1800); // 30 دقيقة — كافٍ لقاعدة شركة واحدة

        $process->mustRun();
    }
}
