<?php

namespace Tests\Feature\Legal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use Modules\HR\Models\Employee;
use Modules\Legal\Models\LegalCase;
use Modules\Legal\Services\CaseService;
use Modules\Legal\Services\TimelineService;
use Tests\TestCase;

/**
 * CASE-1: إنشاء/إسناد القضية ذرّي — فشل جزئي (الخط الزمني) لا يترك قضية بلا إسناد
 * lead (أساس رؤية view_own) ولا صفوفاً معلّقة.
 */
class CaseServiceAtomicityTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_rolls_back_when_timeline_fails(): void
    {
        $lawyer = Employee::factory()->create();
        $data = LegalCase::factory()->make(['responsible_lawyer_id' => $lawyer->id])->toArray();

        // الخط الزمني يفشل بعد إنشاء القضية والإسناد داخل المعاملة.
        $timeline = Mockery::mock(TimelineService::class);
        $timeline->shouldReceive('recordAuto')->andThrow(new \RuntimeException('فشل محاكى للخط الزمني'));

        $service = new CaseService($timeline);

        try {
            $service->create($data, Request::create('/'));
            $this->fail('كان يجب أن يُرمى الاستثناء من داخل المعاملة.');
        } catch (\RuntimeException) {
            // متوقّع
        }

        // القضية والإسناد يجب أن يكونا قد تراجعا بالكامل — لا حالة جزئية.
        $this->assertDatabaseCount('cases', 0);
        $this->assertDatabaseCount('case_assignments', 0);
    }

    public function test_assign_rolls_back_when_timeline_fails(): void
    {
        $case = LegalCase::factory()->create(['responsible_lawyer_id' => null]);
        $lawyer = Employee::factory()->create();

        $timeline = Mockery::mock(TimelineService::class);
        $timeline->shouldReceive('recordAuto')->andThrow(new \RuntimeException('فشل محاكى للخط الزمني'));

        $service = new CaseService($timeline);

        try {
            $service->assign($case, $lawyer->id, 'lead', Request::create('/'));
            $this->fail('كان يجب أن يُرمى الاستثناء من داخل المعاملة.');
        } catch (\RuntimeException) {
            // متوقّع
        }

        // لا إسناد مُودَع، والقضية لم تُحدَّث بـ responsible_lawyer_id.
        $this->assertDatabaseCount('case_assignments', 0);
        $this->assertNull($case->fresh()->responsible_lawyer_id);
    }
}
