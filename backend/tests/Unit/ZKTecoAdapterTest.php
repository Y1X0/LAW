<?php

namespace Tests\Unit;

use Modules\Attendance\Biometric\Adapters\ZKTecoAdapter;
use Modules\Attendance\Models\BiometricDevice;
use PHPUnit\Framework\TestCase;

/**
 * تطبيع (normalize) وتفسير Webhook لمحوّل ZKTeco (Issue #16) — بلا قاعدة بيانات.
 */
class ZKTecoAdapterTest extends TestCase
{
    private function adapter(): ZKTecoAdapter
    {
        // نموذج غير محفوظ يكفي للمحوّل (لا يمسّ قاعدة البيانات).
        return new ZKTecoAdapter(new BiometricDevice(['vendor' => 'zkteco', 'name' => 'جهاز']));
    }

    public function test_normalize_maps_check_in_and_verify_mode(): void
    {
        $punch = $this->adapter()->normalize([
            'pin' => '1001', 'timestamp' => '2026-01-06 08:00:00', 'status' => '0', 'verify' => '1',
        ]);

        $this->assertSame('1001', $punch->biometricUserId);
        $this->assertSame('in', $punch->punchType);
        $this->assertSame('fingerprint', $punch->verifyMode);
        $this->assertSame('2026-01-06 08:00:00', $punch->punchTime->format('Y-m-d H:i:s'));
    }

    public function test_normalize_maps_check_out(): void
    {
        $punch = $this->adapter()->normalize(['user_id' => '1001', 'time' => '2026-01-06 17:00:00', 'status' => '1']);

        $this->assertSame('out', $punch->punchType);
        $this->assertNull($punch->verifyMode);
    }

    public function test_unknown_status_yields_unknown_punch_type(): void
    {
        $punch = $this->adapter()->normalize(['pin' => '1001', 'timestamp' => '2026-01-06 12:00:00', 'status' => '9']);

        $this->assertSame('unknown', $punch->punchType);
    }

    public function test_parse_webhook_handles_multiple_records(): void
    {
        $punches = $this->adapter()->parseWebhook(['records' => [
            ['pin' => '1001', 'timestamp' => '2026-01-06 08:00:00', 'status' => '0'],
            ['pin' => '1002', 'timestamp' => '2026-01-06 08:05:00', 'status' => '0'],
        ]]);

        $this->assertCount(2, $punches);
        $this->assertSame('1002', $punches[1]->biometricUserId);
        $this->assertSame('2026-01-06 08:00:00', $punches[0]->raw['timestamp']);
    }
}
