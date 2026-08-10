<?php

namespace Modules\Backup\Exceptions;

use RuntimeException;

/**
 * يُرمى عندما يفشل التحقّق من صلاحية أرتيفاكت النسخة (F3): pg_dump انتهى دون خطأ عملية
 * لكنّ الناتج فارغ أو أرشيف غير صالح بنيويّاً. يمنع وسم نسخة غير قابلة للاستعادة كـ«مكتملة».
 */
class BackupValidationException extends RuntimeException {}
