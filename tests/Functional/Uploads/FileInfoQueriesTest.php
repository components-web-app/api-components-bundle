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

use Silverback\ApiComponentsBundle\Entity\Core\FileInfo;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Doctrine\QueryCountingMiddleware;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyImageComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\UploadsTrait;

final class FileInfoQueriesTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use UploadsTrait;

    public function test_reading_an_image_whose_file_info_is_stored_queries_it_once_for_the_original_and_every_variant(): void
    {
        $client = static::createClient();
        $image = self::createUploadable(new DummyImageComponent());
        $iri = self::iriOf($image);
        $table = static::getEntityManager()->getClassMetadata(FileInfo::class)->getTableName();
        $client->disableReboot();
        self::requestJsonLd($client, 'GET', $iri);
        self::assertResponseStatusCodeSame(200);
        self::assertSame(3, self::countFileInfoRows() + self::countFileInfoRows('thumbnail') + self::countFileInfoRows('square_thumbnail'));
        QueryCountingMiddleware::reset();

        $response = self::requestJsonLd($client, 'GET', $iri);

        self::assertResponseStatusCodeSame(200);
        self::assertCount(1, QueryCountingMiddleware::queriesOnTable($table), implode("\n", QueryCountingMiddleware::queriesOnTable($table)));
        $mediaObjects = $response->toArray()['_metadata']['mediaObjects']['file'];
        self::assertSame([null, 'thumbnail', 'square_thumbnail'], array_map(static fn (array $mediaObject): ?string => $mediaObject['imagineFilter'] ?? null, $mediaObjects));
        foreach ($mediaObjects as $mediaObject) {
            self::assertSame('image/png', $mediaObject['mimeType']);
            self::assertGreaterThan(0, $mediaObject['width']);
            self::assertGreaterThan(0, $mediaObject['height']);
            self::assertGreaterThan(0, $mediaObject['fileSize']);
        }
    }
}
