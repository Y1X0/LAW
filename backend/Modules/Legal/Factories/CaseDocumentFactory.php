<?php

namespace Modules\Legal\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Legal\Models\CaseDocument;
use Modules\Legal\Models\LegalCase;

/**
 * @extends Factory<CaseDocument>
 */
class CaseDocumentFactory extends Factory
{
    protected $model = CaseDocument::class;

    public function definition(): array
    {
        return [
            'case_id' => LegalCase::factory(),
            'title' => $this->faker->randomElement(['مذكرة دفاع', 'عقد توريد', 'وكالة', 'صورة الحكم']),
            'document_type' => $this->faker->randomElement(['مذكرة', 'لائحة', 'عقد', 'حكم', 'وكالة', 'إثبات']),
            'description' => $this->faker->sentence(),
            'storage_disk' => 'local',
            'storage_path' => 'legal/cases/'.$this->faker->uuid().'.pdf',
        ];
    }
}
