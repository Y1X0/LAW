<?php

namespace Modules\Finance\Exceptions;

use RuntimeException;

/**
 * يُرمى عندما يخالف قيدٌ قواعد الصحّة قبل الترحيل (Finance / Phase 6 · PR-3):
 * اختلال التوازن، أقل من سطرين، قيم سالبة/صفرية، أو حساب غير صالح/غير نشط.
 */
class InvalidJournalEntryException extends RuntimeException {}
