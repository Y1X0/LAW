<?php

namespace Modules\Finance\Exceptions;

use RuntimeException;

/**
 * يُرمى عند محاولة تعديل أو حذف قيد مُرحَّل أو أحد سطوره (Finance / Phase 6 · PR-3).
 * القيد المُرحَّل نهائي — التصحيح يكون بقيد عكس فقط.
 */
class ImmutableJournalEntryException extends RuntimeException {}
