<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * إنشاء أوّل مالك منصّة من متغيّرات البيئة عند النشر (بديل Shell/tinker).
 */
class InitialAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    private function setAdminEnv(?string $email, ?string $password): void
    {
        foreach (['INITIAL_ADMIN_EMAIL' => $email, 'INITIAL_ADMIN_PASSWORD' => $password] as $k => $v) {
            if ($v === null) {
                putenv($k);
                unset($_ENV[$k], $_SERVER[$k]);
            } else {
                putenv("$k=$v");
                $_ENV[$k] = $v;
                $_SERVER[$k] = $v;
            }
        }
    }

    protected function tearDown(): void
    {
        $this->setAdminEnv(null, null);
        parent::tearDown();
    }

    public function test_creates_active_admin_with_working_password_from_env(): void
    {
        $this->setAdminEnv('owner@firm.test', 'Str0ngPass!');

        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'owner@firm.test')->first();
        $this->assertNotNull($admin);
        $this->assertSame('active', $admin->status);
        $this->assertTrue($admin->hasRole('admin'));
        // كلمة المرور مجزّأة مرّة واحدة (cast 'hashed') — لا bcrypt يدوي ولا تجزئة مزدوجة.
        $this->assertTrue(Hash::check('Str0ngPass!', $admin->password));
        $this->assertSame(1, User::where('email', 'owner@firm.test')->count());
    }

    public function test_creates_no_admin_when_env_absent(): void
    {
        $this->setAdminEnv(null, null);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->count());
    }
}
