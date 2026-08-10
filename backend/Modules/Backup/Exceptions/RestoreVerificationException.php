<?php

namespace Modules\Backup\Exceptions;

use RuntimeException;

/**
 * يُرمى عندما تفشل التحقّقات ما بعد الاستعادة (M4): الاستعادة انتهت دون خطأ عملية لكنّها
 * ناقصة (جدول أساسي مفقود / مخطّط فارغ). يمنع الإبلاغ عن «نجاح كاذب».
 */
class RestoreVerificationException extends RuntimeException {}
