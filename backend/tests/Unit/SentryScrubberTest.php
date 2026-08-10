<?php

namespace Tests\Unit;

use App\Support\SentryScrubber;
use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\UserDataBag;

/**
 * تنقية أحداث Sentry: لا بيانات موكّلين/PII تُغادر الخادم إلى المراقبة.
 */
class SentryScrubberTest extends TestCase
{
    public function test_strips_request_body_query_and_cookies(): void
    {
        $event = Event::createEvent();
        $event->setRequest([
            'url' => 'https://api/cases',
            'method' => 'POST',
            'data' => ['national_id' => '1234567890', 'title' => 'قضية موكّل'],
            'query_string' => 'search=سرّي',
            'cookies' => ['session' => 'abc'],
            'headers' => ['Authorization' => 'Bearer secret', 'Accept' => 'application/json'],
        ]);

        $out = SentryScrubber::scrub($event);
        $request = $out->getRequest();

        $this->assertArrayNotHasKey('data', $request);
        $this->assertArrayNotHasKey('query_string', $request);
        $this->assertArrayNotHasKey('cookies', $request);
        // يبقى المسار/الطريقة للتشخيص.
        $this->assertSame('https://api/cases', $request['url']);
        $this->assertSame('POST', $request['method']);
        // ترويسة المصادقة تُحجب، والعاديّة تبقى.
        $this->assertSame('[redacted]', $request['headers']['Authorization']);
        $this->assertSame('application/json', $request['headers']['Accept']);
    }

    public function test_detaches_user_identity(): void
    {
        $event = Event::createEvent();
        $event->setUser(UserDataBag::createFromArray(['id' => 7, 'email' => 'owner@justice.law', 'ip_address' => '1.2.3.4']));

        $out = SentryScrubber::scrub($event);

        $this->assertNull($out->getUser());
    }

    public function test_redacts_sensitive_keys_in_extra(): void
    {
        $event = Event::createEvent();
        $event->setExtra([
            'password' => 'Secret@123',
            'nested' => ['salary' => 9000, 'note' => 'ok'],
            'safe' => 'value',
        ]);

        $out = SentryScrubber::scrub($event);
        $extra = $out->getExtra();

        $this->assertSame('[redacted]', $extra['password']);
        $this->assertSame('[redacted]', $extra['nested']['salary']);
        $this->assertSame('ok', $extra['nested']['note']);
        $this->assertSame('value', $extra['safe']);
    }
}
