<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Support\Str;
use Modules\Core\Models\AuthToken;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;

/**
 * مساعدات اختبار للمصادقة عبر توكن (يتوافق مع middleware auth.token) وإسناد الصلاحيات.
 */
trait AuthenticatesApi
{
    /** يصدر توكن وصول صالح للمستخدم ويضبط ترويسة Authorization. */
    protected function actingAsToken(User $user): static
    {
        $plain = Str::random(64);

        AuthToken::create([
            'user_id' => $user->id,
            'access_token_hash' => hash('sha256', $plain),
            'refresh_token_hash' => hash('sha256', Str::random(64)),
            'access_expires_at' => now()->addMinutes(15),
            'refresh_expires_at' => now()->addDays(14),
        ]);

        return $this->withHeaders(['Authorization' => "Bearer {$plain}"]);
    }

    /** ينشئ مستخدماً بدور يملك الصلاحيات المطلوبة. */
    protected function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'role-'.Str::random(6), 'display_name' => 'اختبار']);

        foreach ($permissions as $name) {
            $permission = Permission::firstOrCreate(['name' => $name], ['module' => Str::before($name, '.')]);
            $role->permissions()->attach($permission->id);
        }

        $user->assignRole($role);

        return $user;
    }
}
