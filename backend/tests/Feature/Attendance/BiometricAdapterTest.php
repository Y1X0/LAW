<?php

namespace Tests\Feature\Attendance;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Attendance\Adapters\BiometricAdapterManager;
use Modules\Attendance\Adapters\ZktecoAdapter;
use Modules\Attendance\Models\BiometricDevice;
use PHPUnit\Framework\TestCase;

class BiometricAdapterTest extends TestCase
{
    public function test_manager_resolves_zkteco_and_rejects_unknown(): void
    {
        $manager = new BiometricAdapterManager;

        $this->assertInstanceOf(ZktecoAdapter::class, $manager->driver('zkteco'));
        $this->assertTrue($manager->supports('zkteco'));
        $this->assertFalse($manager->supports('nokia'));

        $this->expectException(InvalidArgumentException::class);
        $manager->driver('nokia');
    }

    public function test_zkteco_normalizes_punch_status_to_type(): void
    {
        $adapter = new ZktecoAdapter;

        $in = $adapter->normalize(['pin' => 'U9', 'time' => '2026-02-01 08:00:00', 'status' => 0, 'verify' => 'face']);
        $this->assertSame('U9', $in['biometric_user_id']);
        $this->assertSame('check_in', $in['punch_type']);
        $this->assertSame('face', $in['verify_mode']);
        $this->assertInstanceOf(Carbon::class, $in['punch_time']);

        $out = $adapter->normalize(['pin' => 'U9', 'time' => '2026-02-01 17:00:00', 'status' => 1]);
        $this->assertSame('check_out', $out['punch_type']);

        $unknown = $adapter->normalize(['pin' => 'U9', 'time' => '2026-02-01 12:00:00', 'status' => 9]);
        $this->assertSame('unknown', $unknown['punch_type']);
    }

    public function test_zkteco_connect_reflects_device_reachability(): void
    {
        $adapter = new ZktecoAdapter;

        $reachable = new BiometricDevice(['is_active' => true, 'ip_address' => '10.0.0.5']);
        $this->assertTrue($adapter->connect($reachable));

        $noIp = new BiometricDevice(['is_active' => true]);
        $this->assertFalse($adapter->connect($noIp));

        // enrollUser جزء من الواجهة؛ النقل الفعلي يُوصَّل لكل بيئة (الأساس false).
        $this->assertFalse($adapter->enrollUser($reachable, ['biometric_user_id' => 'U1']));
    }

    public function test_zkteco_parses_json_and_attlog_payloads(): void
    {
        $adapter = new ZktecoAdapter;

        $json = $adapter->parseWebhook(['records' => [['pin' => 'A', 'time' => 't', 'status' => 0]]]);
        $this->assertCount(1, $json);
        $this->assertSame('A', $json[0]['pin']);

        $attlog = $adapter->parseWebhook(['attlog' => "B\t2026-02-01 08:00:00\t0\t1"]);
        $this->assertCount(1, $attlog);
        $this->assertSame('B', $attlog[0]['pin']);
        $this->assertSame('2026-02-01 08:00:00', $attlog[0]['time']);
        $this->assertSame('0', (string) $attlog[0]['status']);

        $this->assertSame([], $adapter->parseWebhook(['unexpected' => true]));
    }
}
