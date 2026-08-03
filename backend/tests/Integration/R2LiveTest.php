<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * تحقّق حيّ من التكامل مع Cloudflare R2 (Phase 5 — Operational Validation).
 *
 * لا يُستخدم `Storage::fake` — يتصل بـ R2 الحقيقي عبر أسرار البيئة. يُشغَّل فقط من
 * سير عمل مخصّص (r2-integration.yml)؛ ليس ضمن مجموعتَي الاختبار Unit/Feature، فلا
 * يعمل في CI الرئيسي ولا يتطلّب أسراراً هناك. عند غياب الأسرار يُتخطّى برسالة واضحة.
 *
 * يغطّي الجولة الكاملة: رفع → تأكيد → تنزيل → مطابقة SHA-256 → حذف → تأكيد الحذف.
 */
class R2LiveTest extends TestCase
{
    private const REQUIRED_ENV = ['R2_ACCESS_KEY_ID', 'R2_SECRET_ACCESS_KEY', 'R2_BUCKET', 'R2_ENDPOINT'];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::REQUIRED_ENV as $key) {
            if (blank(env($key))) {
                $this->markTestSkipped("R2 غير مُهيّأ ({$key} مفقود) — يُشغَّل هذا الاختبار فقط مع أسرار R2.");
            }
        }
    }

    public function test_full_r2_round_trip_upload_download_delete(): void
    {
        $disk = Storage::disk('r2');
        $path = 'ci-r2-validation/'.Str::uuid().'.bin';
        $original = random_bytes(256 * 1024); // 256KB محتوى عشوائي
        $expectedSha = hash('sha256', $original);

        try {
            // 1) رفع + 2) تأكيد النجاح.
            $this->assertTrue($disk->put($path, $original), 'فشل رفع الملف إلى R2.');
            $this->assertTrue($disk->exists($path), 'الملف غير موجود على R2 بعد الرفع.');
            $this->assertSame(strlen($original), $disk->size($path), 'حجم الملف على R2 لا يطابق الأصل.');

            // 3) تنزيل + 4) مطابقة SHA-256.
            $downloaded = $disk->get($path);
            $this->assertNotNull($downloaded, 'تعذّر تنزيل الملف من R2.');
            $this->assertSame(
                $expectedSha,
                hash('sha256', $downloaded),
                'SHA-256 للملف المنزَّل لا يطابق الأصل — تلف في جولة R2.',
            );
        } finally {
            // 5) حذف — يُنفَّذ حتى عند فشل ما سبق كي لا نترك ملفاً على الدلو.
            $disk->delete($path);
        }

        // 6) تأكيد الحذف.
        $this->assertFalse($disk->exists($path), 'الملف ما زال موجوداً على R2 بعد الحذف.');
    }
}
