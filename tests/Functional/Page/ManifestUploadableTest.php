<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Page;

use Silverback\ApiComponentsBundle\Entity\Core\FileInfo;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Doctrine\QueryCountingMiddleware;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Flysystem\CountingFilesystemAdapter;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ManifestTrait;

final class ManifestUploadableTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use ImagePageSiteTrait;
    use ManifestTrait;

    public function test_a_manifest_of_a_page_of_images_lists_every_resource_it_renders(): void
    {
        $client = static::createClient();
        $site = self::createImagePageWithNestedGroups('/gallery', 2, 1);
        [$pageGroup, $firstGroup, $secondGroup] = $site['groups'];
        [$first, $firstNested, $second, $secondNested] = $site['images'];
        [$firstPosition, $firstNestedPosition, $secondPosition, $secondNestedPosition] = $site['positions'];

        $response = self::requestJsonLd($client, 'GET', '/_/resource_manifest//gallery');

        self::assertResponseStatusCodeSame(200);
        $node = static fn (object $resource, array $children = []): array => ['iri' => self::iriOf($resource), 'children' => $children];
        $imageNode = static fn (object $position, object $image, object $group, object $nestedPosition, object $nestedImage): array => $node($position, [
            $node($image, [$node($group, [$node($nestedPosition, [$node($nestedImage)])])]),
        ]);
        self::assertSame([
            $node($site['route'], [
                $node($site['page'], [
                    $node($site['layout']),
                    $node($pageGroup, [
                        $imageNode($firstPosition, $first, $firstGroup, $firstNestedPosition, $firstNested),
                        $imageNode($secondPosition, $second, $secondGroup, $secondNestedPosition, $secondNested),
                    ]),
                ]),
            ]),
        ], $response->toArray()['resource_iris']);
    }

    public function test_building_a_manifest_of_a_page_of_images_makes_no_storage_call(): void
    {
        $client = static::createClient();
        self::createImagePageWithNestedGroups('/gallery');
        CountingFilesystemAdapter::reset();

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//gallery');

        self::assertResponseStatusCodeSame(200);
        self::assertSame([], CountingFilesystemAdapter::calls());
    }

    public function test_building_a_manifest_of_a_page_of_images_queries_no_file_info(): void
    {
        $client = static::createClient();
        self::createImagePageWithNestedGroups('/gallery');
        $table = static::getEntityManager()->getClassMetadata(FileInfo::class)->getTableName();
        QueryCountingMiddleware::reset();

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//gallery');

        self::assertResponseStatusCodeSame(200);
        self::assertSame([], QueryCountingMiddleware::queriesOnTable($table));
    }
}
