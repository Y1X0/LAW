<?php

namespace Modules\Legal\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Seeders\RbacSeeder;
use Modules\HR\Models\Employee;
use Modules\Legal\Models\CaseArchiveLocation;
use Modules\Legal\Models\CaseAssignment;
use Modules\Legal\Models\CaseDocument;
use Modules\Legal\Models\CaseParty;
use Modules\Legal\Models\CaseTask;
use Modules\Legal\Models\CaseTimelineEvent;
use Modules\Legal\Models\Client;
use Modules\Legal\Models\DailyWorklog;
use Modules\Legal\Models\Hearing;
use Modules\Legal\Models\LegalCase;

/**
 * بذرة عرض الـ Pilot — تجهيز تشغيل (لا Feature).
 * تُنشئ محامياً تجريبياً حيّاً بقضية مسندة كاملة كي يرى صاحب المكتب النظام يعمل،
 * لا صفحات فارغة. Idempotent (يمكن تشغيلها أكثر من مرة بأمان).
 *
 * التشغيل: php artisan db:seed --class="Modules\\Legal\\Seeders\\PilotDemoSeeder"
 */
class PilotDemoSeeder extends Seeder
{
    public const EMAIL = 'lawyer@demo.law';

    public const PASSWORD = 'Passw0rd!';

    public function run(): void
    {
        // 1) الأدوار والصلاحيات (Idempotent).
        $this->call(RbacSeeder::class);

        // 2) المستخدم المحامي التجريبي (بيانات دخول معروفة) + دورَي محامٍ/موظف.
        $user = User::updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'المحامي التجريبي',
                'password' => Hash::make(self::PASSWORD),
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );
        $user->assignRole('lawyer');
        $user->assignRole('employee');

        // 3) ربطه بموظف.
        $employee = Employee::firstOrCreate(
            ['user_id' => $user->id],
            Employee::factory()->make([
                'user_id' => $user->id,
                'full_name_ar' => 'المحامي التجريبي',
                'job_title' => 'محامٍ أول',
            ])->getAttributes(),
        );

        // 4) عميل + قضية مسندة.
        $client = Client::firstOrCreate(
            ['name' => 'شركة الأمل التجارية'],
            Client::factory()->make(['name' => 'شركة الأمل التجارية'])->getAttributes(),
        );

        $case = LegalCase::firstOrCreate(
            ['internal_number' => 'DEMO-1'],
            LegalCase::factory()->make([
                'internal_number' => 'DEMO-1',
                'court_case_number' => '2026/101',
                'title' => 'نزاع عقد إيجار تجاري',
                'client_id' => $client->id,
                'responsible_lawyer_id' => $employee->id,
                'case_type' => 'تجاري',
                'court_name' => 'محكمة الرياض التجارية',
                'status' => 'open',
                'progress' => 40,
                'opened_date' => now()->subMonth()->toDateString(),
                'description' => 'نزاع حول فسخ عقد إيجار محل تجاري والمطالبة بالتعويض.',
            ])->getAttributes(),
        );

        CaseAssignment::firstOrCreate(
            ['case_id' => $case->id, 'employee_id' => $employee->id],
            ['role' => 'lead'],
        );

        // 5) جلسة قادمة.
        Hearing::firstOrCreate(
            ['case_id' => $case->id, 'type' => 'مرافعة'],
            ['scheduled_at' => now()->addDays(5)->setTime(9, 30), 'status' => 'scheduled', 'location' => 'قاعة 3'],
        );

        // 6) أحداث الخط الزمني (Append-Only).
        $events = [
            ['فتح القضية', 'filing', now()->subMonth(), 'استلام التوكيل وفتح ملف القضية.'],
            ['تقديم لائحة الدعوى', 'memo', now()->subDays(20), 'تقديم لائحة الدعوى للمحكمة التجارية.'],
            ['تحديد أول جلسة', 'hearing', now()->subDays(10), 'تحديد موعد أول جلسة مرافعة.'],
        ];
        foreach ($events as [$title, $type, $date, $desc]) {
            CaseTimelineEvent::firstOrCreate(
                ['case_id' => $case->id, 'title' => $title],
                ['event_type' => $type, 'event_date' => $date->toDateString(), 'description' => $desc],
            );
        }

        // 7) أطراف · مستند · موقع أرشيف (لإثراء التبويبات).
        CaseParty::firstOrCreate(
            ['case_id' => $case->id, 'name' => 'خالد الشهري'],
            ['type' => 'defendant', 'phone' => '0555555555', 'notes' => 'المدّعى عليه — مستأجر المحل.'],
        );
        CaseDocument::firstOrCreate(
            ['case_id' => $case->id, 'title' => 'صحيفة الدعوى'],
            ['document_type' => 'لائحة', 'description' => 'صحيفة الدعوى الأصلية المقدّمة للمحكمة.'],
        );
        CaseArchiveLocation::firstOrCreate(
            ['case_id' => $case->id, 'file_title' => 'الملف الأصلي'],
            ['archive_room' => 'غرفة الأرشيف 1', 'cabinet' => 'A', 'shelf' => '3', 'file_number' => '20', 'notes' => 'النسخة الورقية الأصلية.'],
        );

        // 8) مهمة معلّقة.
        CaseTask::firstOrCreate(
            ['case_id' => $case->id, 'title' => 'إعداد مذكرة الرد'],
            ['assigned_to' => $employee->id, 'description' => 'إعداد مذكرة الرد على دفوع الخصم قبل الجلسة.', 'priority' => 'high', 'due_date' => now()->addDays(3)->toDateString(), 'status' => 'open'],
        );

        // 9) إنجاز اليوم.
        DailyWorklog::updateOrCreate(
            ['employee_id' => $employee->id, 'work_date' => now()->toDateString()],
            ['done_today' => 'مراجعة ملف القضية والتحضير للجلسة القادمة.', 'plan_tomorrow' => 'إعداد مذكرة الرد وجمع المستندات.'],
        );
    }
}
