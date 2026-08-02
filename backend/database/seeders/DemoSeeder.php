<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Department;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Seeders\RbacSeeder;
use Modules\HR\Models\Employee;
use Modules\Leave\Models\LeaveBalance;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveType;
use Modules\Legal\Seeders\PilotDemoSeeder;

/**
 * بذرة العرض الكاملة (Demo) — تجهيز بيانات وحسابات فقط للعرض الحيّ.
 * لا تغيّر أي API أو منطق أو مخطّط — تُنشئ حسابات معروفة وبيانات مرتّبة كي
 * يرى صاحب المكتب النظام يعمل بلا صفحات فارغة. Idempotent (آمنة للتكرار).
 *
 * التشغيل: php artisan db:seed --class="Database\\Seeders\\DemoSeeder"
 *
 * حسابات العرض (كلمة المرور للجميع: Passw0rd!):
 *   admin@demo.law     — مالك المنصّة (وحدة تحكّم Super Admin) — دور admin بكل الصلاحيات
 *   hr@demo.law        — مدير الموارد البشرية (بوابة HR)
 *   lawyer@demo.law    — محامٍ (بوابة المحامي) — من PilotDemoSeeder
 *   employee@demo.law  — موظف عادي (الخدمة الذاتية)
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'Passw0rd!';

    public function run(): void
    {
        // 1) الأدوار والصلاحيات الأساسية (Idempotent).
        $this->call(RbacSeeder::class);

        // 2) منح دور hr صلاحياته (بيانات role_permission فقط) كي تعمل بوابته.
        Role::where('name', 'hr')->first()
            ?->permissions()->syncWithoutDetaching(
                Permission::whereIn('name', RbacSeeder::HR_PERMISSIONS)->pluck('id')
            );

        // 3) قضية المحامي التجريبية الكاملة (Idempotent) — lawyer@demo.law.
        $this->call(PilotDemoSeeder::class);

        // 4) فرع + أقسام العرض.
        $branch = Branch::firstOrCreate(['code' => 'HQ'], ['name' => 'المكتب الرئيسي']);
        $departments = [];
        foreach (['التقاضي', 'الاستشارات', 'المالية', 'الموارد البشرية', 'الإدارة'] as $name) {
            $departments[$name] = Department::firstOrCreate(['branch_id' => $branch->id, 'name' => $name], []);
        }

        // 4b) حساب مالك المنصّة (Super Admin) — دور admin النظامي يملك كل الصلاحيات
        //     (بما فيها roles.manage الذي يكشفه الـ probe لعرض وحدة التحكّم). ليس موظفاً.
        $this->makeUser('admin@demo.law', 'مالك المكتب', ['admin']);

        // 5) حساب مدير الموارد البشرية + موظفه.
        $hrUser = $this->makeUser('hr@demo.law', 'أحمد المصري', ['hr', 'employee']);
        $this->linkEmployee($hrUser, [
            'full_name_ar' => 'أحمد المصري',
            'employee_no' => 'EMP-1001',
            'job_title' => 'مدير الموارد البشرية',
            'department_id' => $departments['الموارد البشرية']->id,
            'branch_id' => $branch->id,
        ]);

        // 6) حساب موظف عادي + موظفه.
        $empUser = $this->makeUser('employee@demo.law', 'سارة العتيبي', ['employee']);
        $employeeRecord = $this->linkEmployee($empUser, [
            'full_name_ar' => 'سارة العتيبي',
            'employee_no' => 'EMP-1002',
            'job_title' => 'أخصائية موارد بشرية',
            'department_id' => $departments['الموارد البشرية']->id,
            'branch_id' => $branch->id,
        ]);

        // 7) مجموعة موظفين للعرض (قائمة واقعية).
        $pool = [
            ['محمد أحمد العتيبي', 'محامٍ أول', 'التقاضي', 'active'],
            ['خالد الشهري', 'سكرتير', 'الإدارة', 'active'],
            ['نورة الدوسري', 'محامية', 'التقاضي', 'active'],
            ['ليان القحطاني', 'محاسب', 'المالية', 'active'],
            ['عمر الزهراني', 'مساعد قانوني', 'الاستشارات', 'on_leave'],
            ['فهد المطيري', 'موظف استقبال', 'الإدارة', 'active'],
            ['ريم السبيعي', 'مستشار قانوني', 'الاستشارات', 'active'],
            ['سلطان الحربي', 'محامٍ', 'التقاضي', 'suspended'],
        ];
        $employees = [$employeeRecord];
        foreach ($pool as $i => [$name, $job, $dept, $status]) {
            $no = 'EMP-20'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
            $employees[] = Employee::firstOrCreate(
                ['employee_no' => $no],
                Employee::factory()->make([
                    'employee_no' => $no, // مفتاح ثابت (لا يُستبدل بقيمة المصنع العشوائية) — يضمن idempotency.
                    'full_name_ar' => $name,
                    'job_title' => $job,
                    'department_id' => $departments[$dept]->id,
                    'branch_id' => $branch->id,
                    'status' => $status,
                ])->getAttributes()
            );
        }

        // 8) أنواع الإجازات.
        $annual = LeaveType::firstOrCreate(['code' => 'annual'], ['name' => 'سنوية', 'is_paid' => true, 'consumes_balance' => true, 'default_annual_days' => 30, 'is_active' => true]);
        $sick = LeaveType::firstOrCreate(['code' => 'sick'], ['name' => 'مرضية', 'is_paid' => true, 'consumes_balance' => true, 'default_annual_days' => 15, 'is_active' => true]);
        $emergency = LeaveType::firstOrCreate(['code' => 'emergency'], ['name' => 'اضطرارية', 'is_paid' => true, 'consumes_balance' => true, 'default_annual_days' => 5, 'is_active' => true]);

        // 9) أرصدة الإجازات لكل موظف عرض.
        $year = (int) now()->format('Y');
        foreach ($employees as $emp) {
            LeaveBalance::firstOrCreate(['employee_id' => $emp->id, 'leave_type_id' => $annual->id, 'year' => $year], ['entitled_days' => 30, 'consumed_days' => 12]);
            LeaveBalance::firstOrCreate(['employee_id' => $emp->id, 'leave_type_id' => $sick->id, 'year' => $year], ['entitled_days' => 15, 'consumed_days' => 2]);
        }

        // 10) طلبات إجازة معلّقة (لعرض الاعتماد/الرفض في بوابة HR).
        $pending = [
            [$employees[1] ?? $employeeRecord, $annual, 5, 'سفر عائلي'],
            [$employees[3] ?? $employeeRecord, $sick, 2, 'ظرف صحي'],
            [$employees[2] ?? $employeeRecord, $emergency, 1, 'مراجعة رسمية'],
            [$employees[5] ?? $employeeRecord, $annual, 3, 'إجازة قصيرة'],
        ];
        foreach ($pending as $idx => [$emp, $type, $days, $reason]) {
            $start = now()->addDays(7 + $idx);
            // start_date مُحوَّل إلى datetime؛ نتحقّق بـ whereDate لضمان idempotency.
            $exists = LeaveRequest::where('employee_id', $emp->id)
                ->where('leave_type_id', $type->id)
                ->whereDate('start_date', $start->toDateString())
                ->exists();
            if (! $exists) {
                LeaveRequest::create([
                    'employee_id' => $emp->id,
                    'leave_type_id' => $type->id,
                    'start_date' => $start->toDateString(),
                    'end_date' => $start->copy()->addDays($days - 1)->toDateString(),
                    'days' => $days,
                    'reason' => $reason,
                    'status' => 'pending',
                ]);
            }
        }

        // 11) سجلات حضور لآخر ثلاثة أيام (بعضها بانتظار الاعتماد).
        $statuses = ['present', 'late', 'present', 'absent', 'present', 'leave'];
        foreach (array_slice($employees, 0, 6) as $i => $emp) {
            for ($d = 0; $d < 3; $d++) {
                $date = now()->subDays($d);
                $status = $statuses[($i + $d) % count($statuses)];
                $hasTime = in_array($status, ['present', 'late'], true);
                // work_date مُحوَّل إلى date؛ نتحقّق بـ whereDate لضمان idempotency (كـ LeaveRequest).
                $exists = AttendanceRecord::where('employee_id', $emp->id)
                    ->whereDate('work_date', $date->toDateString())
                    ->exists();
                if (! $exists) {
                    AttendanceRecord::create([
                        'employee_id' => $emp->id,
                        'work_date' => $date->toDateString(),
                        'branch_id' => $branch->id,
                        'check_in' => $hasTime ? $date->copy()->setTime($status === 'late' ? 8 : 8, $status === 'late' ? 25 : 2) : null,
                        'check_out' => $hasTime ? $date->copy()->setTime(17, 3) : null,
                        'late_minutes' => $status === 'late' ? 25 : 0,
                        'status' => $status,
                        'source' => 'manual',
                        // اليوم غير معتمد (لعرض زرّ الاعتماد)، والأيام الأقدم معتمدة.
                        'approved_at' => $d === 0 ? null : $date->copy()->setTime(18, 0),
                    ]);
                }
            }
        }
    }

    /** ينشئ/يحدّث مستخدماً بأدوار محدّدة (Idempotent). */
    private function makeUser(string $email, string $name, array $roles): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make(self::PASSWORD), 'status' => 'active', 'email_verified_at' => now()],
        );
        foreach ($roles as $role) {
            $user->assignRole($role);
        }

        return $user;
    }

    /** يربط المستخدم بسجلّ موظف (Idempotent). */
    private function linkEmployee(User $user, array $attributes): Employee
    {
        return Employee::firstOrCreate(
            ['user_id' => $user->id],
            Employee::factory()->make(array_merge(['user_id' => $user->id], $attributes))->getAttributes()
        );
    }
}
