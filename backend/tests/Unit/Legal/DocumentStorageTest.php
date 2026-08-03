<?php

namespace Tests\Unit\Legal;

use Modules\Legal\Support\DocumentStorage;
use Tests\TestCase;

class DocumentStorageTest extends TestCase
{
    public function test_path_is_scoped_to_case_with_uuid_and_extension(): void
    {
        $path = DocumentStorage::pathFor(42, 'pdf');

        $this->assertMatchesRegularExpression(
            '#^cases/42/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.pdf$#',
            $path,
        );
    }

    public function test_paths_are_unique_per_call(): void
    {
        $this->assertNotSame(DocumentStorage::pathFor(1, 'pdf'), DocumentStorage::pathFor(1, 'pdf'));
    }

    public function test_extension_is_sanitised_and_never_taken_verbatim_from_client(): void
    {
        // محاولة حقن مسار عبر الامتداد — يجب أن يُنظَّف تماماً.
        $path = DocumentStorage::pathFor(7, '../../etc/passwd');

        $this->assertStringStartsWith('cases/7/', $path);
        $this->assertStringNotContainsString('..', $path);
        $this->assertStringNotContainsString('/etc/', $path);
    }

    public function test_missing_extension_yields_pathless_uuid(): void
    {
        $path = DocumentStorage::pathFor(3, null);

        $this->assertMatchesRegularExpression('#^cases/3/[0-9a-f-]{36}$#', $path);
    }
}
