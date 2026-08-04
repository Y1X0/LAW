<?php

namespace Modules\Finance\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\JournalEntry;
use Modules\Finance\Models\JournalLine;

/**
 * @extends Factory<JournalLine>
 */
class JournalLineFactory extends Factory
{
    protected $model = JournalLine::class;

    public function definition(): array
    {
        return [
            'journal_entry_id' => JournalEntry::factory(),
            'account_id' => Account::factory(),
            'debit' => 0,
            'credit' => 0,
            'notes' => null,
        ];
    }
}
