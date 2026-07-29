<?php

namespace Modules\HR\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Department;
use Modules\HR\Models\Employee;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        $branch = Branch::firstOrCreate(['code' => 'HQ'], ['name' => 'المكتب الرئيسي']);
        $department = Department::firstOrCreate(
            ['branch_id' => $branch->id, 'name' => 'عام'],
            []
        );

        return [
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'employee_no' => 'EMP-'.$this->faker->unique()->numerify('#####'),
            'full_name_ar' => 'موظف '.$this->faker->numberBetween(1, 99999),
            'full_name_en' => $this->faker->name(),
            'national_id' => (string) $this->faker->unique()->numerify('##########'),
            'birth_date' => $this->faker->date('Y-m-d', '2000-01-01'),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'phone' => $this->faker->numerify('05########'),
            'email' => $this->faker->unique()->safeEmail(),
            'job_title' => $this->faker->jobTitle(),
            'hire_date' => $this->faker->date(),
            'contract_type' => 'permanent',
            'basic_salary' => $this->faker->numberBetween(4000, 20000),
            'status' => 'active',
        ];
    }
}
