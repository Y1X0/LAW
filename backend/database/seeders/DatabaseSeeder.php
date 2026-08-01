<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Core\Seeders\RbacSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * البذرة الأساسية الآمنة للإنتاج: الأدوار والصلاحيات النظامية فقط (Idempotent).
     * لا تُنشئ أي حساب تجريبي. بيانات العرض تُشغَّل صراحةً عبر DemoSeeder فقط.
     */
    public function run(): void
    {
        // الأدوار والصلاحيات النظامية — مطلوبة كي يعمل RBAC (Gate::before) أصلاً.
        $this->call(RbacSeeder::class);

        // حساب تجريبي في بيئات التطوير/الاختبار فقط — لا يُنشأ في الإنتاج.
        if (app()->environment('local', 'testing')) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }
    }
}
