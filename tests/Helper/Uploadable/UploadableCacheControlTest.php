<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\Uploadable;

use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Annotation\UploadableField;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemFactory;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableCacheControl;
use Symfony\Component\DependencyInjection\ServiceLocator;

require_once __DIR__ . '/../../Flysystem/cloud_adapter_stubs.php';

class UploadableCacheControlTest extends TestCase
{
    private const string ORIGINAL = 'public, max-age=31536000, immutable';
    private const string IMAGINE = 'public, max-age=2592000';

    public function test_a_field_with_no_override_uses_the_bundle_values(): void
    {
        $field = new UploadableField(adapter: 'gcs');
        $cacheControl = $this->cacheControl();

        self::assertSame(self::ORIGINAL, $cacheControl->forOriginal($field));
        self::assertSame(self::IMAGINE, $cacheControl->forImagine($field));
    }

    public function test_a_field_override_replaces_the_bundle_value_for_the_original_and_its_variants_separately(): void
    {
        $cacheControl = $this->cacheControl();

        $originalOnly = new UploadableField(adapter: 'gcs', cacheControl: 'private, max-age=60');
        self::assertSame('private, max-age=60', $cacheControl->forOriginal($originalOnly));
        self::assertSame(self::IMAGINE, $cacheControl->forImagine($originalOnly));

        $imagineOnly = new UploadableField(adapter: 'gcs', imagineCacheControl: 'public, max-age=600');
        self::assertSame(self::ORIGINAL, $cacheControl->forOriginal($imagineOnly));
        self::assertSame('public, max-age=600', $cacheControl->forImagine($imagineOnly));
    }

    public function test_a_field_set_to_false_sets_nothing_when_the_bundle_value_is_set(): void
    {
        $field = new UploadableField(adapter: 'gcs', cacheControl: false, imagineCacheControl: false);
        $cacheControl = $this->cacheControl();

        self::assertNull($cacheControl->forOriginal($field));
        self::assertNull($cacheControl->forImagine($field));
    }

    public function test_a_field_override_applies_when_the_bundle_sets_nothing(): void
    {
        $cacheControl = $this->cacheControl(null, null);

        self::assertNull($cacheControl->forOriginal(new UploadableField(adapter: 'gcs')));
        self::assertNull($cacheControl->forImagine(new UploadableField(adapter: 'gcs')));
        self::assertSame('no-cache', $cacheControl->forOriginal(new UploadableField(adapter: 'gcs', cacheControl: 'no-cache')));
        self::assertSame('no-cache', $cacheControl->forImagine(new UploadableField(adapter: 'gcs', imagineCacheControl: 'no-cache')));
    }

    public function test_a_variant_of_a_source_registered_for_a_field_uses_that_fields_imagine_value(): void
    {
        $cacheControl = $this->cacheControl();
        $cacheControl->registerVariantSource('photo-1a2b3c4d.png', new UploadableField(adapter: 'gcs', imagineCacheControl: 'public, max-age=600'));
        $cacheControl->registerVariantSource('logo-1a2b3c4d.png', new UploadableField(adapter: 'gcs', imagineCacheControl: false));

        self::assertSame('public, max-age=600', $cacheControl->forVariantOf('photo-1a2b3c4d.png'));
        self::assertNull($cacheControl->forVariantOf('logo-1a2b3c4d.png'));
        self::assertSame(self::IMAGINE, $cacheControl->forVariantOf('other-1a2b3c4d.png'));
    }

    public function test_reset_forgets_the_registered_variant_sources(): void
    {
        $cacheControl = $this->cacheControl();
        $cacheControl->registerVariantSource('photo-1a2b3c4d.png', new UploadableField(adapter: 'gcs', imagineCacheControl: false));

        $cacheControl->reset();

        self::assertSame(self::IMAGINE, $cacheControl->forVariantOf('photo-1a2b3c4d.png'));
    }

    public function test_the_write_config_takes_the_form_of_the_adapter_behind_the_filesystem(): void
    {
        $factory = new FilesystemFactory(new ServiceLocator([
            'gcs' => static fn () => (new \ReflectionClass(GoogleCloudStorageAdapter::class))->newInstanceWithoutConstructor(),
            's3' => static fn () => (new \ReflectionClass(AwsS3V3Adapter::class))->newInstanceWithoutConstructor(),
        ]));
        $cacheControl = new UploadableCacheControl($factory, self::ORIGINAL, self::IMAGINE);

        self::assertSame(
            ['metadata' => ['contentType' => 'image/png', 'cacheControl' => 'no-cache']],
            $cacheControl->writeConfig($factory->create('gcs'), ['metadata' => ['contentType' => 'image/png']], 'no-cache'),
        );
        self::assertSame(
            ['mimetype' => 'image/png', 'CacheControl' => 'no-cache'],
            $cacheControl->writeConfig($factory->create('s3'), ['mimetype' => 'image/png'], 'no-cache'),
        );
        self::assertSame(
            ['mimetype' => 'image/png'],
            $cacheControl->writeConfig(new Filesystem((new \ReflectionClass(GoogleCloudStorageAdapter::class))->newInstanceWithoutConstructor()), ['mimetype' => 'image/png'], 'no-cache'),
        );
    }

    private function cacheControl(?string $original = self::ORIGINAL, ?string $imagine = self::IMAGINE): UploadableCacheControl
    {
        return new UploadableCacheControl(new FilesystemFactory(new ServiceLocator([])), $original, $imagine);
    }
}
