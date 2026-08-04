<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Models\Invoice;
use Modules\HR\Models\Employee;
use Modules\Legal\Models\Hearing;
use Modules\Legal\Models\LegalCase;
use Modules\Notifications\Models\Notification;
use Modules\Notifications\Services\NotificationService;
use Tests\TestCase;

/**
 * محرّك التذكيرات الزمنية (Phase 8 · PR-4) — الأمر notifications:remind: يولّد تذكيراً
 * عند تحقّق الشرط، لا يكرّره عند إعادة التشغيل، لا يُشعر غير المعنيّ، ولا يوقفه فشل إشعار.
 */
class ReminderCommandTest extends TestCase
{
    use RefreshDatabase;

    /** يبني قضية بمحامٍ مسؤول مرتبط بحساب + جلسة قادمة؛ يعيد [مستخدم المحامي، الجلسة]. */
    private function upcomingHearing(): array
    {
        $lawyerUser = User::factory()->create();
        $lawyer = Employee::factory()->create(['user_id' => $lawyerUser->id]);
        $case = LegalCase::factory()->create(['responsible_lawyer_id' => $lawyer->id]);
        $hearing = Hearing::factory()->create([
            'case_id' => $case->id, 'status' => 'scheduled', 'scheduled_at' => now()->addDay(),
        ]);

        return [$lawyerUser, $hearing];
    }

    private function outstandingInvoice(User $approver, string $dueDate): Invoice
    {
        return Invoice::factory()->create([
            'invoice_no' => 'INV-'.fake()->unique()->numerify('#####'),
            'status' => Invoice::STATUS_SENT,
            'approved_by' => $approver->id,
            'balance' => 500,
            'due_date' => $dueDate,
        ]);
    }

    public function test_upcoming_hearing_notifies_case_lawyer(): void
    {
        [$lawyerUser, $hearing] = $this->upcomingHearing();

        $this->artisan('notifications:remind')->assertExitCode(0);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $lawyerUser->id, 'type' => 'hearing_upcoming',
            'related_type' => 'Hearing', 'related_id' => $hearing->id,
        ]);
    }

    public function test_reminders_are_not_duplicated_on_rerun(): void
    {
        [$lawyerUser, $hearing] = $this->upcomingHearing();

        $this->artisan('notifications:remind')->assertExitCode(0);
        $this->artisan('notifications:remind')->assertExitCode(0);

        $this->assertSame(1, Notification::query()
            ->where('user_id', $lawyerUser->id)->where('type', 'hearing_upcoming')
            ->where('related_id', $hearing->id)->count());
    }

    public function test_uninvolved_user_is_not_notified(): void
    {
        $this->upcomingHearing();
        $outsider = User::factory()->create();

        $this->artisan('notifications:remind')->assertExitCode(0);

        $this->assertSame(0, Notification::query()->where('user_id', $outsider->id)->count());
    }

    public function test_far_future_hearing_is_not_reminded_yet(): void
    {
        $lawyerUser = User::factory()->create();
        $lawyer = Employee::factory()->create(['user_id' => $lawyerUser->id]);
        $case = LegalCase::factory()->create(['responsible_lawyer_id' => $lawyer->id]);
        Hearing::factory()->create(['case_id' => $case->id, 'status' => 'scheduled', 'scheduled_at' => now()->addDays(30)]);

        $this->artisan('notifications:remind')->assertExitCode(0);
        $this->assertSame(0, Notification::query()->where('type', 'hearing_upcoming')->count());
    }

    public function test_invoice_due_soon_notifies_approver(): void
    {
        $approver = User::factory()->create();
        $invoice = $this->outstandingInvoice($approver, now()->addDays(2)->toDateString());

        $this->artisan('notifications:remind')->assertExitCode(0);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $approver->id, 'type' => 'invoice_due_soon',
            'related_type' => 'Invoice', 'related_id' => $invoice->id,
        ]);
    }

    public function test_overdue_invoice_notifies_without_changing_status(): void
    {
        $approver = User::factory()->create();
        $invoice = $this->outstandingInvoice($approver, now()->subDay()->toDateString());

        $this->artisan('notifications:remind')->assertExitCode(0);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $approver->id, 'type' => 'invoice_overdue',
            'related_type' => 'Invoice', 'related_id' => $invoice->id,
        ]);
        // القاعدة #5: لا يغيّر المحرّك حالة الفاتورة المالية.
        $this->assertSame(Invoice::STATUS_SENT, $invoice->fresh()->status);
    }

    public function test_paid_invoice_is_not_reminded(): void
    {
        $approver = User::factory()->create();
        Invoice::factory()->create([
            'invoice_no' => 'INV-PAID', 'status' => Invoice::STATUS_PAID,
            'approved_by' => $approver->id, 'balance' => 0, 'due_date' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('notifications:remind')->assertExitCode(0);
        $this->assertSame(0, Notification::query()->count());
    }

    public function test_emit_failure_does_not_stop_processing(): void
    {
        // خدمة إشعارات معطوبة → الأمر يكمل بلا استثناء (best-effort) ولا صفوف تُنشأ.
        $this->app->bind(NotificationService::class, fn () => new class extends NotificationService
        {
            public function emit(int $userId, string $type, string $title, ?string $body = null, ?string $relatedType = null, ?int $relatedId = null): Notification
            {
                throw new \RuntimeException('boom');
            }
        });

        $this->upcomingHearing();
        $this->artisan('notifications:remind')->assertExitCode(0);
        $this->assertSame(0, Notification::query()->count());
    }
}
