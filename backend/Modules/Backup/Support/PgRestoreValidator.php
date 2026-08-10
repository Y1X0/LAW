<?php

namespace Modules\Backup\Support;

use Modules\Backup\Contracts\DatabaseValidator;
use Modules\Backup\Exceptions\BackupValidationException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * تحقّق من صلاحية أرشيف نسخة Postgres بصيغة custom (‎-Fc‎) عبر ‎pg_restore --list‎: يقرأ
 * فهرس المحتويات (TOC) من الملف فقط — بلا اتصال بقاعدة بيانات وبلا أي أثر جانبي. يفشل على
 * الملف الفارغ أو المبتور أو غير-الأرشيف، فنُميّز «التفريغ الناجح ظاهريّاً لكنّه تالف» قبل
 * وسمه مكتملاً (F3). لا يفكّ الضغط ولا يستعيد — فحص بنية خفيف فقط.
 */
class PgRestoreValidator implements DatabaseValidator
{
    public function validate(string $path): void
    {
        $process = new Process(['pg_restore', '--list', $path]);
        $process->setTimeout(120);

        try {
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            throw new BackupValidationException(
                'الأرتيفاكت ليس أرشيف نسخة صالحاً (فشل pg_restore --list): '.mb_substr($e->getMessage(), 0, 300),
                previous: $e,
            );
        }
    }
}
