<?php

namespace Modules\Legal\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Legal\Models\CaseArchiveLocation;
use Modules\Legal\Models\LegalCase;

/**
 * @extends Factory<CaseArchiveLocation>
 */
class CaseArchiveLocationFactory extends Factory
{
    protected $model = CaseArchiveLocation::class;

    public function definition(): array
    {
        return [
            'case_id' => LegalCase::factory(),
            'file_title' => $this->faker->randomElement(['الملف الأصلي', 'العقود', 'الأحكام', 'ملفات التنفيذ']),
            'archive_room' => 'غرفة '.$this->faker->numberBetween(1, 3),
            'cabinet' => $this->faker->randomElement(['A', 'B', 'C']),
            'shelf' => (string) $this->faker->numberBetween(1, 6),
            'drawer' => (string) $this->faker->numberBetween(1, 4),
            'file_number' => (string) $this->faker->numberBetween(1, 200),
        ];
    }
}
