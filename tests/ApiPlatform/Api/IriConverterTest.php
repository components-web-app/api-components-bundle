<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\ApiPlatform\Api;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\ApiPlatform\Api\IriConverter;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Route;

class IriConverterTest extends TestCase
{
    public function test_a_resource_other_than_a_route_keeps_the_decorated_iri(): void
    {
        $converter = new IriConverter($this->decorated('/_/layouts/abc'), $this->createStub(ResourceMetadataCollectionFactoryInterface::class));

        self::assertSame('/_/layouts/abc', $converter->getIriFromResource(new Layout()));
    }

    public function test_a_route_without_a_path_keeps_the_decorated_iri(): void
    {
        $converter = new IriConverter($this->decorated('/_/routes/abc'), $this->createStub(ResourceMetadataCollectionFactoryInterface::class));

        self::assertSame('/_/routes/abc', $converter->getIriFromResource(new Route()));
    }

    public function test_a_saved_route_has_its_id_replaced_by_its_path(): void
    {
        $id = Uuid::uuid4();
        $route = (new Route())->setPath('/about-us');
        (new \ReflectionProperty(Route::class, 'id'))->setValue($route, $id);
        $converter = new IriConverter($this->decorated('/_/routes/' . $id->toString()), $this->createStub(ResourceMetadataCollectionFactoryInterface::class));

        self::assertSame('/_/routes//about-us', $converter->getIriFromResource($route));
    }

    public function test_an_unsaved_route_has_its_last_segment_replaced_by_its_path(): void
    {
        $route = (new Route())->setPath('/about-us');
        $converter = new IriConverter($this->decorated('/_/routes/placeholder'), $this->createStub(ResourceMetadataCollectionFactoryInterface::class));

        self::assertSame('/_/routes//about-us', $converter->getIriFromResource($route));
    }

    public function test_the_me_operation_asks_for_the_iri_of_the_users_item_operation(): void
    {
        $itemOperation = new Get(name: 'user_get', class: Layout::class);
        $metadataFactory = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $metadataFactory->method('create')->willReturn(new ResourceMetadataCollection(Layout::class, [
            (new ApiResource())->withOperations(new Operations([
                'user_collection' => new GetCollection(name: 'user_collection', class: Layout::class),
                'user_get' => $itemOperation,
            ])),
        ]));

        $decorated = $this->createMock(IriConverterInterface::class);
        $decorated->expects(self::once())
            ->method('getIriFromResource')
            ->with(self::anything(), UrlGeneratorInterface::ABS_PATH, $itemOperation, ['operation' => $itemOperation])
            ->willReturn('/users/abc');

        $converter = new IriConverter($decorated, $metadataFactory);

        self::assertSame('/users/abc', $converter->getIriFromResource(new Layout(), UrlGeneratorInterface::ABS_PATH, new Get(name: '_api_me', class: Layout::class)));
    }

    public function test_the_me_operation_is_kept_when_the_resource_has_no_item_operation(): void
    {
        $meOperation = new Get(name: '_api_me', class: Layout::class);
        $metadataFactory = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $metadataFactory->method('create')->willReturn(new ResourceMetadataCollection(Layout::class, [
            (new ApiResource())->withOperations(new Operations([
                'user_collection' => new GetCollection(name: 'user_collection', class: Layout::class),
            ])),
        ]));

        $decorated = $this->createMock(IriConverterInterface::class);
        $decorated->expects(self::once())
            ->method('getIriFromResource')
            ->with(self::anything(), UrlGeneratorInterface::ABS_PATH, $meOperation, ['operation' => $meOperation])
            ->willReturn('/me');

        $converter = new IriConverter($decorated, $metadataFactory);

        self::assertSame('/me', $converter->getIriFromResource(new Layout(), UrlGeneratorInterface::ABS_PATH, $meOperation));
    }

    private function decorated(string $iri): IriConverterInterface
    {
        $decorated = $this->createStub(IriConverterInterface::class);
        $decorated->method('getIriFromResource')->willReturn($iri);

        return $decorated;
    }
}
