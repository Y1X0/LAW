<?php

namespace Tests\Feature\HR;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\HR\Models\Employee;
use Modules\HR\Services\EmployeeIdentityService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

class EmployeeIdentityTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // مسارات اختبار فقط لتفعيل الحارس دون شحن أي API خدمة ذاتية في هذا الـ Issue.
        Route::middleware(['api', 'auth.token', 'employee.linked'])
            ->get('/api/_test/self-guard', fn () => response()->json(['data' => 'ok']));

        Route::middleware(['api', 'auth.token'])
            ->get('/api/_test/employees/{employee}/owned', function (Employee $employee, Request $request) {
                abort_unless($employee->isOwnedBy($request->user()), 403);

                return response()->json(['data' => 'ok']);
            });
    }

    private function service(): EmployeeIdentityService
    {
        return new EmployeeIdentityService;
    }

    /** يربط موظفاً بمستخدم عبر الخدمة (يعيد الموظف محدّثاً). */
    private function linkedEmployee(?User $user = null): array
    {
        $user ??= User::factory()->create();
        $employee = Employee::factory()->create();
        $this->service()->link($employee, $user);

        return [$employee->fresh(), $user];
    }

    // (1) مستخدم مرتبط بموظف → يمرّ من الحارس.
    public function test_linked_user_passes_self_guard(): void
    {
        [, $user] = $this->linkedEmployee();

        $this->actingAsToken($user)->getJson('/api/_test/self-guard')
            ->assertOk()->assertJsonPath('data', 'ok');
    }

    // (2) موظف بلا حساب → سجلّ صحيح، user() = null، بلا أخطاء.
    public function test_employee_without_account_is_valid(): void
    {
        $employee = Employee::factory()->create();

        $this->assertNull($employee->user_id);
        $this->assertNull($employee->user);
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'user_id' => null]);
    }

    // (3) مستخدم بلا موظف مرتبط → 403 في مسارات الخدمة الذاتية.
    public function test_user_without_employee_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAsToken($user)->getJson('/api/_test/self-guard')
            ->assertStatus(403)->assertJsonPath('errors.code', 'NO_LINKED_EMPLOYEE');
    }

    // (4) محاولة الوصول لبيانات موظف آخر → 403.
    public function test_cannot_access_another_employees_record(): void
    {
        [$mine, $me] = $this->linkedEmployee();
        [$other] = $this->linkedEmployee();

        // سجلّي → 200
        $this->actingAsToken($me)->getJson("/api/_test/employees/{$mine->id}/owned")->assertOk();
        // سجلّ موظف آخر → 403
        $this->actingAsToken($me)->getJson("/api/_test/employees/{$other->id}/owned")->assertStatus(403);
    }

    // (5) حذف المستخدم (soft) لا يفسد سجل الموظف.
    public function test_deleting_user_does_not_corrupt_employee(): void
    {
        [$employee, $user] = $this->linkedEmployee();

        $user->delete(); // soft delete

        $this->assertDatabaseHas('employees', ['id' => $employee->id]);
        $this->assertNotSoftDeleted('employees', ['id' => $employee->id]);
        // سجل الموظف سليم (لم يُحذف بالتتابع).
        $this->assertNotNull(Employee::find($employee->id));
    }

    // (5-ب) فكّ الربط يُبقي سجل الموظف سليماً (user_id = null).
    public function test_unlink_keeps_employee_record(): void
    {
        [$employee, $user] = $this->linkedEmployee();

        $this->service()->unlink($employee);

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'user_id' => null]);
        $this->assertNull($employee->fresh()->user_id);
    }

    // (6) مستخدم واحد لا يُربط بأكثر من موظف → 422.
    public function test_user_cannot_link_to_multiple_employees(): void
    {
        [, $user] = $this->linkedEmployee();
        $another = Employee::factory()->create();

        try {
            $this->service()->link($another, $user);
            $this->fail('توقّعنا رفض ربط المستخدم بموظف ثانٍ.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    // (7) موظف مرتبط لا يُربط بمستخدم ثانٍ بلا فكّ الربط → 422.
    public function test_employee_cannot_link_to_second_user_without_unlink(): void
    {
        [$employee] = $this->linkedEmployee();
        $second = User::factory()->create();

        try {
            $this->service()->link($employee, $second);
            $this->fail('توقّعنا رفض ربط الموظف بحساب ثانٍ قبل فكّ الربط.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    // (8) إعادة الربط: A↔X ثم فكّ ثم B↔X → ينجح بلا بيانات معلّقة.
    public function test_reassignment_flow_is_clean(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $employee = Employee::factory()->create();

        $this->service()->link($employee, $userA);
        $this->service()->unlink($employee->fresh());
        $this->service()->link($employee->fresh(), $userB);

        $fresh = $employee->fresh();
        $this->assertSame($userB->id, $fresh->user_id);
        // لا بيانات معلّقة: A لم يعد مرتبطاً بأي موظف.
        $this->assertNull($userA->fresh()->employee);
        $this->assertSame($employee->id, $userB->fresh()->employee->id);
    }

    // العلاقات في الاتجاهين.
    public function test_relationships_resolve_both_directions(): void
    {
        [$employee, $user] = $this->linkedEmployee();

        $this->assertSame($employee->id, $user->fresh()->employee->id);
        $this->assertSame($user->id, $employee->fresh()->user->id);
    }

    // قيد الفرادة على مستوى قاعدة البيانات (شبكة أمان تحت منطق الخدمة).
    public function test_unique_constraint_enforced_at_database(): void
    {
        $user = User::factory()->create();
        Employee::factory()->create(['user_id' => $user->id]);

        $this->expectException(QueryException::class);
        Employee::factory()->create(['user_id' => $user->id]); // نفس user_id → انتهاك unique
    }

    // إعادة ربط نفس الزوج → idempotent (بلا خطأ).
    public function test_linking_same_pair_is_idempotent(): void
    {
        [$employee, $user] = $this->linkedEmployee();

        $this->service()->link($employee->fresh(), $user); // مرة ثانية
        $this->assertSame($user->id, $employee->fresh()->user_id);
    }

    // تدقيق الربط عند تمرير طلب (يستفيد منه أي مسار مستقبلي).
    public function test_link_records_audit_when_request_given(): void
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create();

        $this->service()->link($employee, $user, Request::create('/'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'employee_identity_linked',
            'auditable_id' => $employee->id,
        ]);
    }
}
