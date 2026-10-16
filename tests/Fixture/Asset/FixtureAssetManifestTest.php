<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Fixture\Asset;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Fixture\Asset\FixtureAssetManifest;

class FixtureAssetManifestTest extends TestCase
{
    private const string A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string B = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function test_the_manifest_sits_beside_the_scaffold_class_and_is_named_after_it(): void
    {
        self::assertSame('/app/src/DataFixtures/SiteScaffold.assets.json', FixtureAssetManifest::pathFor('/app/src/DataFixtures', 'SiteScaffold'));
    }

    public function test_an_entry_records_the_name_type_size_and_every_field_it_came_from(): void
    {
        $manifest = new FixtureAssetManifest();

        self::assertTrue($manifest->add(self::A, 'photo.png', 'image/png', 12, 'App\\Entity\\Hero::file'));
        self::assertTrue($manifest->add(self::A, 'photo.png', 'image/png', 12, 'App\\Entity\\Card::image'));
        self::assertTrue($manifest->add(self::A, 'photo.png', 'image/png', 12, 'App\\Entity\\Hero::file'));

        self::assertSame(['name' => 'photo.png', 'mimeType' => 'image/png', 'size' => 12, 'fields' => ['App\\Entity\\Card::image', 'App\\Entity\\Hero::file']], $manifest->get(self::A));
        self::assertSame(1, $manifest->count());
    }

    public function test_the_same_content_under_another_name_keeps_the_first_name_and_says_so(): void
    {
        $manifest = new FixtureAssetManifest();
        $manifest->add(self::A, 'photo.png', 'image/png', 12, 'App\\Entity\\Hero::file');

        self::assertFalse($manifest->add(self::A, 'copy.png', 'image/png', 12, 'App\\Entity\\Card::image'));
        self::assertSame(['name' => 'photo.png', 'mimeType' => 'image/png', 'size' => 12, 'fields' => ['App\\Entity\\Card::image', 'App\\Entity\\Hero::file']], $manifest->get(self::A));
    }

    public function test_an_unknown_hash_has_no_entry(): void
    {
        self::assertNull((new FixtureAssetManifest())->get(self::A));
        self::assertSame(0, (new FixtureAssetManifest())->count());
    }

    public function test_the_json_is_ordered_by_hash_so_the_same_site_writes_the_same_manifest(): void
    {
        $manifest = new FixtureAssetManifest();
        $manifest->add(self::B, 'b.txt', 'text/plain', 1, 'App\\Entity\\Doc::file');
        $manifest->add(self::A, 'a/b.png', 'image/png', 2, 'App\\Entity\\Hero::file');

        self::assertSame(<<<'JSON'
            {
                "assets": {
                    "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa": {
                        "name": "a/b.png",
                        "mimeType": "image/png",
                        "size": 2,
                        "fields": [
                            "App\\Entity\\Hero::file"
                        ]
                    },
                    "bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb": {
                        "name": "b.txt",
                        "mimeType": "text/plain",
                        "size": 1,
                        "fields": [
                            "App\\Entity\\Doc::file"
                        ]
                    }
                }
            }

            JSON, $manifest->toJson());
        self::assertSame([self::A, self::B], $manifest->hashes());
    }

    public function test_an_empty_manifest_is_still_written(): void
    {
        self::assertSame("{\n    \"assets\": {}\n}\n", (new FixtureAssetManifest())->toJson());
        self::assertSame([], (new FixtureAssetManifest())->hashes());
    }

    public function test_a_manifest_reads_back_what_it_wrote(): void
    {
        $manifest = new FixtureAssetManifest();
        $manifest->add(self::B, 'b.txt', 'text/plain', 1, 'App\\Entity\\Doc::file');
        $manifest->add(self::A, 'photo.png', 'image/png', 12, 'App\\Entity\\Hero::file');

        $read = FixtureAssetManifest::fromJson($manifest->toJson(), 'GeneratedScaffold.assets.json');

        self::assertSame($manifest->toJson(), $read->toJson());
        self::assertSame(['name' => 'photo.png', 'mimeType' => 'image/png', 'size' => 12, 'fields' => ['App\\Entity\\Hero::file']], $read->get(self::A));
        self::assertSame("{\n    \"assets\": {}\n}\n", FixtureAssetManifest::fromJson("{\n    \"assets\": {}\n}\n", 'Empty.assets.json')->toJson());
    }

    public function test_a_manifest_file_is_read_from_disk(): void
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'manifest');
        $manifest = new FixtureAssetManifest();
        $manifest->add(self::A, 'photo.png', 'image/png', 12, 'App\\Entity\\Hero::file');
        file_put_contents($file, $manifest->toJson());
        try {
            self::assertSame($manifest->toJson(), FixtureAssetManifest::fromFile($file)->toJson());
        } finally {
            unlink($file);
        }
    }

    public function test_a_missing_manifest_file_names_itself(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The fixture asset manifest /nowhere/Site.assets.json does not exist.');

        FixtureAssetManifest::fromFile('/nowhere/Site.assets.json');
    }

    public static function invalidManifests(): iterable
    {
        yield 'not json' => ['{'];
        yield 'not an object' => ['[]'];
        yield 'no assets' => ['{}'];
        yield 'assets not an object' => ['{"assets": "none"}'];
        yield 'a key that is not a hash' => ['{"assets": {"photo.png": {"name": "photo.png", "mimeType": "image/png", "size": 1, "fields": []}}}'];
        yield 'an upper case hash' => ['{"assets": {"' . strtoupper(self::A) . '": {"name": "a.png", "mimeType": "image/png", "size": 1, "fields": []}}}'];
        yield 'an entry that is not an object' => ['{"assets": {"' . self::A . '": "a.png"}}'];
        yield 'no name' => ['{"assets": {"' . self::A . '": {"mimeType": "image/png", "size": 1, "fields": []}}}'];
        yield 'no mime type' => ['{"assets": {"' . self::A . '": {"name": "a.png", "size": 1, "fields": []}}}'];
        yield 'a size that is not an integer' => ['{"assets": {"' . self::A . '": {"name": "a.png", "mimeType": "image/png", "size": "1", "fields": []}}}'];
        yield 'no fields' => ['{"assets": {"' . self::A . '": {"name": "a.png", "mimeType": "image/png", "size": 1}}}'];
        yield 'fields that are not strings' => ['{"assets": {"' . self::A . '": {"name": "a.png", "mimeType": "image/png", "size": 1, "fields": [1]}}}'];
    }

    #[DataProvider('invalidManifests')]
    public function test_an_invalid_manifest_is_rejected_by_name(string $json): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The fixture asset manifest Site.assets.json is not valid.');

        FixtureAssetManifest::fromJson($json, 'Site.assets.json');
    }
}
