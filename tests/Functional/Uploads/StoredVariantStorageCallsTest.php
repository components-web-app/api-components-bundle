<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Uploads;

use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyImageComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Flysystem\CountingFilesystemAdapter;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\UploadsTrait;

final class StoredVariantStorageCallsTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use UploadsTrait;

    private string $originalMemoryLimit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalMemoryLimit = (string) \ini_get('memory_limit');
    }

    protected function tearDown(): void
    {
        ini_set('memory_limit', $this->originalMemoryLimit);
        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function memoryLimits(): iterable
    {
        yield 'a memory limit' => ['1G'];
        yield 'an unlimited memory limit' => ['-1'];
    }

    #[DataProvider('memoryLimits')]
    public function test_each_stored_variant_costs_one_storage_check_when_its_resource_is_read(string $memoryLimit): void
    {
        ini_set('memory_limit', $memoryLimit);
        $client = static::createClient();
        $image = self::createUploadable(new DummyImageComponent());
        $iri = self::iriOf($image);
        $client->disableReboot();
        self::requestJsonLd($client, 'GET', $iri);
        self::assertResponseStatusCodeSame(200);
        CountingFilesystemAdapter::reset();

        self::requestJsonLd($client, 'GET', $iri);

        self::assertResponseStatusCodeSame(200);
        $calls = CountingFilesystemAdapter::calls();
        self::assertCount(2, $calls, json_encode($calls, \JSON_THROW_ON_ERROR));
        self::assertSame(['fileExists', 'fileExists'], array_column($calls, 0));
        self::assertCount(2, array_unique(array_column($calls, 1)));
    }
}
