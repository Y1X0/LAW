<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Modules\Backup\Contracts\DatabaseDumper;
use Modules\Backup\Models\Backup;
use Modules\Core\Models\Role;
use Modules\Core\Seeders\RbacSeeder;
use Modules\Finance\Seeders\ChartOfAccountsSeeder;
use Modules\HR\Models\Employee;
use Modules\Notifications\Mail\NotificationMail;
use Tests\Concerns\AuthenticatesApi;
use Tests\TestCase;

/**
 * اختبار القبول الشامل (B6 · UAT) — يُثبت أن المكتب يعمل **طرفاً لطرف عبر الـ API** من أوّل دقيقة
 * إلى آخرها: تأسيس (فرع/قسم/أدوار)، حضور، قضية، مستند، إسناد، مهمة، worklog، فاتورة+اعتماد،
 * جدولة تذكيرات، إشعار داخلي + بريد، نسخة احتياطيّة، سجلّ تدقيق، وحدود صلاحيّات كل دور — كلها في
 * رحلة واحدة تنتهي بنجاح بلا أي استثناء. يوازي دليل القبول اليدوي docs/UAT.md.
 */
class AcceptanceJourneyTest extends TestCase
{
    use AuthenticatesApi, RefreshDatabase;

    private function login(string $email, string $password): string
    {
        return $this->postJson('/api/auth/login', ['email' => $email, 'password' => $password])
            ->assertOk()->json('data.tokens.access_token');
    }

    /** يُنشئ موظّفاً + حساباً مربوطاً بدور، ويعيد التوكن بعد تسجيل الدخول (يثبت دخول كل دور). */
    private function onboard(string $ownerToken, array $emp, string $email, string $password, string $role): array
    {
        $employee = $this->withToken($ownerToken)->postJson('/api/employees', $emp)
            ->assertStatus(201)->json('data');

        $roleId = Role::where('name', $role)->value('id');
        $user = $this->withToken($ownerToken)->postJson('/api/users', [
            'name' => $emp['full_name_ar'], 'email' => $email, 'password' => $password, 'role_ids' => [$roleId],
        ])->assertStatus(201)->json('data');

        $this->withToken($ownerToken)->postJson("/api/users/{$user['id']}/employee", ['employee_id' => $employee['id']])
            ->assertOk();

        return ['employee' => $employee, 'user' => $user, 'token' => $this->login($email, $password)];
    }

    public function test_full_company_lifecycle_end_to_end(): void
    {
        // بيئة القبول: كتالوج الأدوار/الصلاحيات + دليل الحسابات المالي + تزييف التخزين والبريد.
        $this->seed(RbacSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        Storage::fake('r2');
        Storage::fake('backups');
        Mail::fake();
        // نسخة احتياطيّة تعمل على SQLite عبر مُفرِّغ مزيّف (بلا pg_dump حقيقي).
        $this->app->instance(DatabaseDumper::class, new class implements DatabaseDumper
        {
            public function dump(string $targetPath): void
            {
                file_put_contents($targetPath, 'DUMP-BYTES');
            }
        });

        // ===== 1) المالك: إنشاء الحساب وتسجيل الدخول =====
        User::factory()->create(['email' => 'owner@firm.test', 'password' => 'OwnerPass12!', 'status' => 'active'])
            ->assignRole('admin');
        $owner = $this->login('owner@firm.test', 'OwnerPass12!');

        // ===== 2) فرع + قسم =====
        $hq = $this->withToken($owner)->postJson('/api/branches', ['name' => 'المكتب الرئيسي', 'code' => 'HQ'])
            ->assertStatus(201)->json('data');
        $dept = $this->withToken($owner)->postJson('/api/departments', ['branch_id' => $hq['id'], 'name' => 'التقاضي'])
            ->assertStatus(201)->json('data');

        $baseEmp = fn (string $name, string $nid) => [
            'branch_id' => $hq['id'], 'department_id' => $dept['id'],
            'full_name_ar' => $name, 'national_id' => $nid, 'status' => 'active',
        ];

        // ===== 3-8) الأدوار: HR + محامٍ + محاسب + موظّف (إنشاء + ربط + دور + تسجيل دخول كلٍّ) =====
        $hr = $this->onboard($owner, $baseEmp('منى مدير الموارد', '1000001'), 'hr@firm.test', 'HrPass12345!', 'hr');
        $lawyer = $this->onboard($owner, $baseEmp('أحمد المحامي', '1000002'), 'lawyer@firm.test', 'LawyerPass12!', 'lawyer');
        $accountant = $this->onboard($owner, $baseEmp('سعاد المحاسبة', '1000003'), 'acct@firm.test', 'AcctPass1234!', 'accountant');
        $employee = $this->onboard($owner, $baseEmp('خالد الموظّف', '1000004'), 'emp@firm.test', 'EmpPass1234!', 'employee');

        // تأكيد الربط الظاهر (HR يرى موظّفاً بحساب ودور).
        $this->withToken($hr['token'])->getJson("/api/employees/{$hr['employee']['id']}")
            ->assertOk()->assertJsonPath('data.has_account', true)
            ->assertJsonPath('data.account.roles.0.name', 'hr');

        // ===== 9) الحضور: HR يسجّل حضوراً يدويّاً =====
        $this->withToken($hr['token'])->postJson('/api/attendance/manual', [
            'employee_id' => $lawyer['employee']['id'], 'work_date' => '2026-01-06',
            'status' => 'present', 'notes' => 'دوام كامل',
        ])->assertCreated();

        // ===== 10) عميل + قضية (المالك) =====
        $client = $this->withToken($owner)->postJson('/api/clients', [
            'name' => 'شركة المستقبل للتجارة', 'type' => 'company', 'phone' => '0790000000',
            'email' => 'info@future.example', 'national_id' => '2001234567', 'address' => 'عمّان',
        ])->assertStatus(201)->json('data');

        $case = $this->withToken($owner)->postJson('/api/cases', [
            'internal_number' => 'CASE-2026-100', 'title' => 'مطالبة مالية بموجب عقد توريد',
            'client_id' => $client['id'], 'case_type' => 'تجاري', 'value' => 150000,
        ])->assertStatus(201)->json('data');

        // ===== 11) إسناد القضية للمحامي =====
        $this->withToken($owner)->postJson("/api/cases/{$case['id']}/assign", [
            'employee_id' => $lawyer['employee']['id'], 'role' => 'lead',
        ])->assertCreated();

        // ===== 12) رفع مستند (multipart، قرص r2 مزيّف) — إدارة القضية بيد المالك/المدير =====
        // (نموذج الأدوار: المحامي «عامل» على القضية المُسنَدة؛ إدارة الملف بيد المالك.)
        $file = UploadedFile::fake()->create('مذكرة دفاع.pdf', 120, 'application/pdf');
        $doc = $this->withToken($owner)->post("/api/cases/{$case['id']}/documents", [
            'title' => 'مذكرة دفاع', 'document_type' => 'مذكرة', 'file' => $file,
        ])->assertCreated()->json('data');
        Storage::disk('r2')->assertExists($doc['storage_path']);

        // ===== 13) جلسة قادمة (لتذكير hearing_upcoming) =====
        $this->withToken($owner)->postJson("/api/cases/{$case['id']}/hearings", [
            'scheduled_at' => now()->addDay()->toDateTimeString(), 'type' => 'مرافعة',
        ])->assertCreated();

        // ===== 14) مهمة على القضية (ينشئها المالك ويُسنِدها للمحامي) =====
        $task = $this->withToken($owner)->postJson('/api/tasks', [
            'title' => 'إعداد مذكرة الرد', 'assigned_to' => $lawyer['employee']['id'], 'priority' => 'high',
        ])->assertStatus(201)->json('data');

        // ===== 14ب) المحامي يُكمل المهمّة المُسنَدة إليه (دوره الفعلي) =====
        $this->withToken($lawyer['token'])->patchJson("/api/tasks/{$task['id']}/complete")
            ->assertOk()->assertJsonPath('data.status', 'done');

        // ===== 15) Worklog يومي (المحامي المرتبط بموظّف) =====
        $this->withToken($lawyer['token'])->postJson('/api/me/worklog', [
            'done_today' => 'مراجعة ملف القضية وإكمال المهمّة', 'plan_tomorrow' => 'حضور الجلسة',
        ])->assertCreated();

        // ===== 16-17) فاتورة + اعتماد (المحاسب) — due_date قريب ليُنتج تذكير الاستحقاق =====
        $invoice = $this->withToken($accountant['token'])->postJson('/api/invoices', [
            'client_id' => $client['id'], 'due_date' => now()->addDays(2)->toDateString(),
            'items' => [['description' => 'أتعاب محاماة', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0]],
        ])->assertCreated()->json('data');

        $this->withToken($accountant['token'])->postJson("/api/invoices/{$invoice['id']}/approve")
            ->assertOk()->assertJsonPath('data.status', 'sent');

        // ===== 18) تشغيل المُجدوِل (تذكيرات) =====
        $this->artisan('notifications:remind')->assertExitCode(0);

        // ===== 19) إشعارات داخل النظام أُنشئت =====
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $accountant['user']['id'], 'type' => 'invoice_due_soon',
            'related_type' => 'Invoice', 'related_id' => $invoice['id'],
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $lawyer['user']['id'], 'type' => 'hearing_upcoming',
        ]);

        // ===== 20) بريد أُرسِل للأنواع المُدرَجة بالقائمة البيضاء =====
        Mail::assertSent(NotificationMail::class, fn (NotificationMail $m) => $m->notification->type === 'invoice_due_soon');
        Mail::assertSent(NotificationMail::class, fn (NotificationMail $m) => $m->notification->type === 'hearing_upcoming');

        // ===== 21) نسخة احتياطيّة (المالك) =====
        $backup = $this->withToken($owner)->postJson('/api/admin/backups')
            ->assertStatus(201)->assertJsonPath('data.status', 'completed')->json('data');
        Storage::disk('backups')->assertExists(Backup::find($backup['id'])->path);

        // ===== 22) سجلّ التدقيق يحوي العمليّات الحسّاسة =====
        foreach (['case_created', 'case_lawyer_assigned', 'case_document_added', 'worklog_submitted', 'backup_created'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action]);
        }

        // ===== 23) حدود صلاحيّات كل دور (RBAC) =====
        // المحامي لا يرى الرواتب.
        $this->withToken($lawyer['token'])->getJson('/api/payroll-periods')->assertStatus(403);
        // الموظّف العادي لا يعتمد فاتورة.
        $this->withToken($employee['token'])->postJson("/api/invoices/{$invoice['id']}/approve")->assertStatus(403);

        // النتيجة: رحلة المكتب كاملة نجحت بلا أي استثناء.
        $this->assertSame(4, Employee::whereNotNull('user_id')->count());
    }
}
