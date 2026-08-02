<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Http\Middleware\AuthenticateToken;
use Modules\Core\Http\Middleware\EnsurePermission;
use Modules\HR\Http\Middleware\EnsureLinkedEmployee;

/**
 * ModuleServiceProvider — نواة الـ Modular Monolith.
 *
 * يكتشف الوحدات تلقائياً داخل مجلد Modules/ ويحمّل لكل وحدة:
 *  - مسارات الـ API      (Modules/<Name>/routes/api.php)  تحت البادئة /api
 *  - الترحيلات (Migrations) (Modules/<Name>/database/migrations)
 *
 * هذا يحقّق حدود الوحدات المعرّفة في docs/module-boundaries.md دون أي تبعية خارجية،
 * ويجعل إضافة وحدة جديدة = إنشاء مجلد جديد فقط (Open/Closed).
 */
class ModuleServiceProvider extends ServiceProvider
{
    /** المسار الجذري للوحدات. */
    protected string $modulesPath;

    public function register(): void
    {
        $this->modulesPath = base_path('Modules');
    }

    public function boot(): void
    {
        $this->modulesPath = base_path('Modules');

        // أسماء الـ middleware المتاحة للوحدات.
        $router = $this->app['router'];
        $router->aliasMiddleware('auth.token', AuthenticateToken::class);   // مصادقة (#11)
        $router->aliasMiddleware('permission', EnsurePermission::class);    // RBAC (#12)
        $router->aliasMiddleware('employee.linked', EnsureLinkedEmployee::class); // خدمة ذاتية (#47)

        // تكامل RBAC مع Gate: أي صلاحية يملكها المستخدم تُمنح تلقائياً (docs/05 §4).
        // إرجاع null يترك المجال للسياسات الصريحة للفصل.
        Gate::before(fn ($user, string $ability) => $user->hasPermission($ability) ? true : null);

        // رابط إعادة تعيين كلمة المرور يشير لواجهة العميل (API-only، بلا مسار ويب password.reset).
        ResetPassword::createUrlUsing(function ($notifiable, string $token): string {
            $frontend = rtrim((string) config('app.frontend_url', config('app.url')), '/');

            return "{$frontend}/reset-password?token={$token}&email=".urlencode($notifiable->getEmailForPasswordReset());
        });

        if (! is_dir($this->modulesPath)) {
            return;
        }

        foreach (glob($this->modulesPath.'/*', GLOB_ONLYDIR) as $modulePath) {
            $this->loadModuleRoutes($modulePath);
            $this->loadModuleMigrations($modulePath);
            $this->loadModuleCommands($modulePath);
        }
    }

    /** تحميل مسارات الـ API الخاصة بالوحدة تحت البادئة /api مع middleware المناسب. */
    protected function loadModuleRoutes(string $modulePath): void
    {
        $routeFile = $modulePath.'/routes/api.php';

        if (is_file($routeFile)) {
            Route::middleware('api')
                ->prefix('api')
                ->group($routeFile);
        }
    }

    /** تسجيل ترحيلات الوحدة ليتعرّف عليها artisan migrate. */
    protected function loadModuleMigrations(string $modulePath): void
    {
        $migrationsPath = $modulePath.'/database/migrations';

        if (is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }
    }

    /** اكتشاف أوامر Artisan الخاصة بالوحدة (Modules/<Name>/Console/Commands) وتسجيلها. */
    protected function loadModuleCommands(string $modulePath): void
    {
        $commandsPath = $modulePath.'/Console/Commands';

        if (! is_dir($commandsPath)) {
            return;
        }

        $moduleName = basename($modulePath);
        $commands = [];

        foreach (glob($commandsPath.'/*.php') as $file) {
            $class = "Modules\\{$moduleName}\\Console\\Commands\\".basename($file, '.php');
            if (class_exists($class)) {
                $commands[] = $class;
            }
        }

        if ($commands !== []) {
            $this->commands($commands);
        }
    }
}
