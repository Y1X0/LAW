<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Department;
use Modules\Core\Seeders\RbacSeeder;
use Modules\Finance\Models\ExpenseCategory;
use Modules\Finance\Models\FinancialAccount;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Seeders\ChartOfAccountsSeeder;
use Modules\Finance\Seeders\FinanceReferenceSeeder;
use Modules\Finance\Services\ExpenseService;
use Modules\Finance\Services\InvoiceService;
use Modules\Finance\Services\PaymentService;
use Modules\HR\Models\Employee;
use Modules\Leave\Models\LeaveBalance;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveType;
use Modules\Legal\Models\CaseAssignment;
use Modules\Legal\Models\CaseDocument;
use Modules\Legal\Models\CaseTask;
use Modules\Legal\Models\Client;
use Modules\Legal\Models\DailyWorklog;
use Modules\Legal\Models\Hearing;
use Modules\Legal\Models\LegalCase;
use Modules\Notifications\Models\Notification;

/**
 * بذرة عرض غنيّة — «مكتب العدالة للمحاماة» (Demo/Training فقط، ليست بيانات إنتاج).
 *
 * مستقلّة تماماً: لا يستدعيها DatabaseSeeder، ولا تعدّل أي منطق/هجرة/بذرة قائمة. تُشغَّل يدويّاً:
 *   php artisan db:seed --class="Database\Seeders\JusticeDemoSeeder"
 *
 * السلامة: **محجوبة على الإنتاج** افتراضياً (تتطلّب JUSTICE_DEMO_FORCE=1 لتجاوز الحارس) —
 * فخطأ بشري لا يلوّث قاعدة عميل حقيقيّة. **Idempotent**: كل كيان بمفتاح طبيعي (firstOrCreate/
 * updateOrCreate)، والمالية بحارس وجود — فتشغيلها مراراً آمن بلا تكرار أو فساد.
 *
 * المالية عبر **نفس خدمات النظام** (InvoiceService/PaymentService/ExpenseService) فيبقى دفتر
 * الأستاذ متزناً والأرصدة صحيحة (لا إدراج مباشر على القيود). التدقيق يُولَّد تلقائياً عبرها.
 */
class JusticeDemoSeeder extends Seeder
{
    private const PASSWORD = 'Justice@2026';

    public function run(): void
    {
        if (app()->environment('production') && ! env('JUSTICE_DEMO_FORCE')) {
            $this->command?->error('JusticeDemoSeeder محجوب على الإنتاج. للتجاوز عمداً: JUSTICE_DEMO_FORCE=1');

            return;
        }

        // مرجعيّات مطلوبة (idempotent وآمنة للإنتاج): الأدوار + دليل الحسابات + الصندوق/التصنيفات.
        $this->call(RbacSeeder::class);
        $this->call(ChartOfAccountsSeeder::class);
        $this->call(FinanceReferenceSeeder::class);

        [$branch, $depts] = $this->org();
        $people = $this->staff($branch, $depts);
        $clients = $this->clients();
        $cases = $this->cases($clients, $people['lawyers']);
        $this->worklogs($people['lawyers']);
        $this->finance($clients, $cases, $people['owner']);
        $this->notifications($people);
        $this->attendanceAndLeave($people['lawyers']);

        $this->command?->info('تم تجهيز بيانات عرض «مكتب العدالة للمحاماة». كلمة المرور للجميع: '.self::PASSWORD);
    }

    /** الفرع الرئيسي + الأقسام (يعيد استخدام HQ إن وُجد). */
    private function org(): array
    {
        $branch = Branch::firstOrCreate(['code' => 'HQ'], ['name' => 'مكتب العدالة للمحاماة', 'is_active' => true]);
        $depts = [];
        foreach (['التقاضي', 'الاستشارات', 'المالية', 'الموارد البشرية', 'الإدارة'] as $name) {
            $depts[$name] = Department::firstOrCreate(['branch_id' => $branch->id, 'name' => $name], ['is_active' => true]);
        }

        return [$branch, $depts];
    }

    /** يُنشئ مستخدماً بدور (updateOrCreate على البريد). */
    private function user(string $name, string $email, array $roles): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make(self::PASSWORD), 'status' => 'active', 'email_verified_at' => now()],
        );
        foreach ($roles as $role) {
            if (! $user->hasRole($role)) {
                $user->assignRole($role);
            }
        }

        return $user;
    }

    /** يُنشئ موظّفاً مربوطاً بمستخدم (firstOrCreate على employee_no). */
    private function employee(Branch $b, Department $d, string $no, string $name, string $nid, string $title, User $user): Employee
    {
        return Employee::firstOrCreate(
            ['employee_no' => $no],
            Employee::factory()->make([
                'branch_id' => $b->id, 'department_id' => $d->id, 'employee_no' => $no,
                'national_id' => $nid, 'full_name_ar' => $name, 'job_title' => $title,
                'status' => 'active', 'user_id' => $user->id, 'hire_date' => now()->subYears(2)->toDateString(),
            ])->getAttributes(),
        );
    }

    /** الفريق: مالك + HR + 5 محامين + محاسب + سكرتير + 3 موظّفين (≈12). */
    private function staff(Branch $branch, array $depts): array
    {
        $owner = $this->user('عُمر العدالة (المالك)', 'owner@justice.law', ['admin']);

        $hrUser = $this->user('منى الموارد', 'hr@justice.law', ['hr', 'employee']);
        $this->employee($branch, $depts['الموارد البشرية'], 'JUS-1001', 'منى الموارد', '9990001', 'مدير الموارد البشرية', $hrUser);

        $lawyerNames = ['أحمد الشريف', 'ليلى القاضي', 'خالد المنصور', 'رنا الحداد', 'سامي العلي'];
        $lawyers = [];
        foreach ($lawyerNames as $i => $name) {
            $u = $this->user($name, 'lawyer'.($i + 1).'@justice.law', ['lawyer', 'employee']);
            $lawyers[] = $this->employee($branch, $depts['التقاضي'], 'JUS-20'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT), $name, '99110'.$i, 'محامٍ', $u);
        }

        $acctUser = $this->user('سعاد المالية', 'accountant@justice.law', ['accountant', 'employee']);
        $this->employee($branch, $depts['المالية'], 'JUS-3001', 'سعاد المالية', '9990003', 'محاسب', $acctUser);

        $secUser = $this->user('هدى الاستقبال', 'secretary@justice.law', ['secretary', 'employee']);
        $this->employee($branch, $depts['الإدارة'], 'JUS-4001', 'هدى الاستقبال', '9990004', 'سكرتير', $secUser);

        foreach (['طارق الإداري', 'نور المساعدة', 'وليد الأرشيف'] as $j => $name) {
            $u = $this->user($name, 'staff'.($j + 1).'@justice.law', ['employee']);
            $this->employee($branch, $depts['الإدارة'], 'JUS-50'.str_pad((string) ($j + 1), 2, '0', STR_PAD_LEFT), $name, '99120'.$j, 'موظّف إداري', $u);
        }

        return ['owner' => $owner, 'lawyers' => $lawyers, 'hr' => $hrUser, 'accountant' => $acctUser];
    }

    /** 7 عملاء (4 شركات + 3 أفراد). */
    private function clients(): array
    {
        $companies = ['شركة المستقبل التجارية', 'مجموعة البنيان العقاريّة', 'مصنع الرواد للصناعات', 'شركة الأفق للنقل'];
        $individuals = ['فهد الغامدي', 'عائشة النعيمي', 'يوسف بن سالم'];
        $out = [];
        foreach ($companies as $i => $n) {
            $out[] = Client::firstOrCreate(['name' => $n], ['type' => 'company', 'status' => 'active', 'national_id' => '30012345'.$i, 'phone' => '079000000'.$i]);
        }
        foreach ($individuals as $i => $n) {
            $out[] = Client::firstOrCreate(['name' => $n], ['type' => 'individual', 'status' => 'active', 'national_id' => '99887766'.$i, 'phone' => '078000000'.$i]);
        }

        return $out;
    }

    /** 20 قضية بتوزيع واقعي (تجاري6/عمالي5/مدني4/تنفيذ3/أسري2) وحالات مختلفة + إسناد/جلسات/مهام/مستندات. */
    private function cases(array $clients, array $lawyers): array
    {
        $dist = array_merge(
            array_fill(0, 6, 'تجاري'), array_fill(0, 5, 'عمالي'),
            array_fill(0, 4, 'مدني'), array_fill(0, 3, 'تنفيذ'), array_fill(0, 2, 'أسري'),
        );

        // حالات القضايا: النظام يدعم open/pending/closed؛ نعبّر عن «نشطة/بانتظار جلسة» بـ open + تقدّم/جلسة.
        $cases = [];
        foreach ($dist as $i => $type) {
            $n = $i + 1;
            $status = match (true) {
                $i % 7 === 6 => 'closed',   // مغلقة
                $i % 7 === 5 => 'pending',  // قيد المراجعة
                default => 'open',          // جديدة/نشطة/بانتظار جلسة
            };
            $lawyer = $lawyers[$i % count($lawyers)];
            $client = $clients[$i % count($clients)];

            $case = LegalCase::firstOrCreate(
                ['internal_number' => 'JUS-C-2026-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT)],
                [
                    'title' => $type.' — '.$client->name.' (قضية '.$n.')',
                    'client_id' => $client->id,
                    'case_type' => $type,
                    'status' => $status,
                    'progress' => $status === 'closed' ? 100 : ($status === 'pending' ? 15 : (($i % 3 + 1) * 25)),
                    'responsible_lawyer_id' => $lawyer->id,
                    'court_name' => 'محكمة '.($type === 'تجاري' ? 'البداية التجاريّة' : 'الصلح'),
                    'value' => ($i + 1) * 5000,
                    'opened_date' => now()->subDays(120 - $i * 5)->toDateString(),
                ],
            );

            CaseAssignment::firstOrCreate(['case_id' => $case->id, 'employee_id' => $lawyer->id], ['role' => 'lead']);

            // جلسة: قادمة للقضايا النشطة (بانتظار جلسة)، وسابقة للمغلقة.
            if ($status === 'open' && $i % 2 === 0) {
                Hearing::firstOrCreate(['case_id' => $case->id, 'type' => 'مرافعة'],
                    ['scheduled_at' => now()->addDays(($i % 10) + 2), 'status' => 'scheduled', 'location' => 'القاعة '.($i % 4 + 1)]);
            } elseif ($status === 'closed') {
                Hearing::firstOrCreate(['case_id' => $case->id, 'type' => 'نطق بالحكم'],
                    ['scheduled_at' => now()->subDays(15), 'status' => 'held', 'outcome' => 'صدر الحكم']);
            }

            // مهمة على القضية للمحامي المسؤول.
            CaseTask::firstOrCreate(['case_id' => $case->id, 'title' => 'إعداد مذكرة القضية '.$n],
                ['assigned_to' => $lawyer->id, 'priority' => ['low', 'normal', 'high'][$i % 3], 'status' => $status === 'closed' ? 'done' : 'open']);

            // مستند وصفي (بلا رفع فعلي).
            CaseDocument::firstOrCreate(['case_id' => $case->id, 'title' => 'عقد/مستند القضية '.$n],
                ['document_type' => 'مستند', 'description' => 'نسخة عرض — بيانات وصفية فقط', 'original_name' => 'doc-'.$n.'.pdf', 'mime_type' => 'application/pdf']);

            $cases[] = $case;
        }

        return $cases;
    }

    /** Worklogs يوميّة للمحامين عبر تواريخ مختلفة. */
    private function worklogs(array $lawyers): void
    {
        foreach ($lawyers as $li => $lawyer) {
            for ($d = 1; $d <= 3; $d++) {
                DailyWorklog::updateOrCreate(
                    ['employee_id' => $lawyer->id, 'work_date' => now()->subDays($d)->toDateString()],
                    ['done_today' => 'مراجعة قضايا ومتابعة جلسات (اليوم '.$d.')', 'plan_tomorrow' => 'إعداد مذكّرات وحضور جلسة'],
                );
            }
        }
    }

    /** مالية واقعيّة عبر الخدمات (متزنة بدفتر الأستاذ) — بحارس وجود لمنع التكرار. */
    private function finance(array $clients, array $cases, User $owner): void
    {
        $firstClient = $clients[0];
        if (Invoice::where('client_id', $firstClient->id)->exists()) {
            return; // سبق تجهيز المالية.
        }

        $request = Request::create('/', 'POST');
        $request->setUserResolver(fn () => $owner);

        $invoiceService = app(InvoiceService::class);
        $paymentService = app(PaymentService::class);
        $expenseService = app(ExpenseService::class);
        $cashAccount = FinancialAccount::where('type', 'cash')->first() ?? FinancialAccount::first();
        $category = ExpenseCategory::first();

        // 6 فواتير: 3 مدفوعة كاملاً، 1 مدفوعة جزئياً، 1 معتمَدة غير مدفوعة، 1 مسوّدة.
        foreach (range(0, 5) as $i) {
            $client = $clients[$i % count($clients)];
            $case = $cases[$i] ?? null;
            $invoice = $invoiceService->create([
                'client_id' => $client->id,
                'case_id' => $case?->id,
                'issue_date' => now()->subDays(20 - $i)->toDateString(),
                'due_date' => now()->addDays($i * 3 + 2)->toDateString(),
                'items' => [[
                    'description' => 'أتعاب محاماة ومتابعة قضائيّة',
                    'quantity' => 1, 'unit_price' => ($i + 2) * 500, 'tax_rate' => 16,
                ]],
            ], $request);

            if ($i === 5) {
                continue; // تبقى مسوّدة.
            }

            $invoice = $invoiceService->approve($invoice, $request);

            if ($i < 3) { // مدفوعة كاملاً
                $paymentService->record($invoice, ['amount' => (float) $invoice->total, 'method' => 'bank_transfer', 'account_id' => $cashAccount->id, 'payment_date' => now()->toDateString()], 'JUS-PAY-'.$invoice->id, $request);
            } elseif ($i === 3) { // مدفوعة جزئياً
                $paymentService->record($invoice, ['amount' => round((float) $invoice->total / 2, 2), 'method' => 'cash', 'account_id' => $cashAccount->id, 'payment_date' => now()->toDateString()], 'JUS-PAY-'.$invoice->id, $request);
            }
            // i === 4 تبقى معتمَدة غير مدفوعة.
        }

        // 3 مصروفات تشغيليّة.
        if ($category && $cashAccount) {
            foreach (['إيجار المكتب', 'رسوم قضائيّة', 'قرطاسيّة ولوازم'] as $k => $label) {
                $expenseService->record([
                    'category_id' => $category->id, 'amount' => ($k + 1) * 300, 'method' => 'cash',
                    'account_id' => $cashAccount->id, 'beneficiary' => $label, 'expense_date' => now()->subDays($k * 3)->toDateString(),
                    'description' => $label,
                ], $request);
            }
        }
    }

    /** إشعارات داخل النظام (مباشرة — بلا بريد). */
    private function notifications(array $people): void
    {
        $rows = [
            [$people['accountant']->id, 'invoice_due_soon', 'فاتورة تقترب من الاستحقاق'],
            [$people['owner']->id, 'generic', 'مرحباً بك في نظام مكتب العدالة'],
            [$people['hr']->id, 'generic', 'تذكير: مراجعة طلبات الإجازة'],
        ];
        foreach ($rows as [$uid, $type, $title]) {
            Notification::firstOrCreate(['user_id' => $uid, 'type' => $type, 'title' => $title], ['body' => 'إشعار عرض تجريبي.']);
        }
    }

    /** حضور وإجازات خفيفة لإحياء لوحة HR. */
    private function attendanceAndLeave(array $lawyers): void
    {
        $sample = array_slice($lawyers, 0, 4);
        foreach ($sample as $idx => $emp) {
            for ($d = 1; $d <= 15; $d++) {
                $date = now()->subDays($d);
                if ($date->isFriday() || $date->isSaturday()) {
                    continue; // عطلة نهاية الأسبوع.
                }
                $exists = AttendanceRecord::where('employee_id', $emp->id)
                    ->whereDate('work_date', $date->toDateString())->exists();
                if ($exists) {
                    continue;
                }
                $late = ($d + $idx) % 5 === 0;
                AttendanceRecord::create([
                    'employee_id' => $emp->id, 'work_date' => $date->toDateString(),
                    'status' => $late ? 'late' : 'present', 'source' => 'manual',
                    'late_minutes' => $late ? 20 : 0,
                ]);
            }
        }

        // إجازتان-ثلاث (نوع + رصيد + طلب) — مثل نمط DemoSeeder.
        $annual = LeaveType::firstOrCreate(['code' => 'annual'], ['name' => 'سنويّة', 'is_paid' => true, 'consumes_balance' => true, 'default_annual_days' => 21]);
        foreach (array_slice($lawyers, 0, 3) as $k => $emp) {
            LeaveBalance::firstOrCreate(
                ['employee_id' => $emp->id, 'leave_type_id' => $annual->id, 'year' => (int) now()->year],
                ['entitled_days' => 21, 'consumed_days' => $k],
            );
            $start = now()->addDays(10 + $k * 3);
            $exists = LeaveRequest::where('employee_id', $emp->id)
                ->whereDate('start_date', $start->toDateString())->exists();
            if (! $exists) {
                LeaveRequest::create([
                    'employee_id' => $emp->id, 'leave_type_id' => $annual->id,
                    'start_date' => $start->toDateString(), 'end_date' => $start->copy()->addDays(2)->toDateString(),
                    'days' => 3, 'reason' => 'إجازة قصيرة', 'status' => 'pending',
                ]);
            }
        }
    }
}
