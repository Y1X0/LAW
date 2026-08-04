<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Core\Seeders\OrgStructureSeeder;
use Modules\Core\Seeders\RbacSeeder;
use Modules\Finance\Seeders\FinanceReferenceSeeder;

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

        // الهيكل التنظيمي الأساسي (فرع رئيسي + أقسام نظامية) — Idempotent وآمن للإنتاج.
        // مطلوب كي ينجح استيراد الموظفين (يطابق الفرع/القسم بالاسم). لا بيانات تجريبية.
        $this->call(OrgStructureSeeder::class);

        // البيانات المرجعية المالية (ضريبة/صندوق/تصنيفات مصروفات) — Idempotent وآمنة للإنتاج.
        $this->call(FinanceReferenceSeeder::class);

        // أوّل مالك منصّة عند النشر: يُنشأ مرّة إذا ضُبط INITIAL_ADMIN_EMAIL و
        // INITIAL_ADMIN_PASSWORD (متغيّرا بيئة في لوحة Render) — يغني عن Shell/tinker.
        // idempotent (firstOrCreate)، وكلمة المرور تُجزّأ عبر cast 'hashed' (بلا bcrypt يدوي).
        $this->seedInitialAdmin();

        // حساب تجريبي في بيئات التطوير/الاختبار فقط — لا يُنشأ في الإنتاج.
        if (app()->environment('local', 'testing')) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }
    }

    private function seedInitialAdmin(): void
    {
        $email = env('INITIAL_ADMIN_EMAIL');
        $password = env('INITIAL_ADMIN_PASSWORD');
        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            return;
        }

        $admin = User::firstOrCreate(
            ['email' => $email],
            ['name' => 'مالك المنصّة', 'password' => $password, 'status' => 'active'],
        );

        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }
    }
}
