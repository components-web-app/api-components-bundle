<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\OpenApi;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Exception\ResourceClassNotFoundException;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Info;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Paths;
use ApiPlatform\OpenApi\OpenApi;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\OpenApi\OpenApiFactory;

class OpenApiFactoryTest extends TestCase
{
    public function test_get_extended_version_appends_parenthesized_bundle_version(): void
    {
        $extended = OpenApiFactory::getExtendedVersion('3.1.0');

        $this->assertStringStartsWith('3.1.0 (', $extended);
        $this->assertStringEndsWith(')', $extended);
    }

    public function test_get_extended_version_preserves_original_version(): void
    {
        $extended = OpenApiFactory::getExtendedVersion('2.0');

        $this->assertStringStartsWith('2.0', $extended);
    }

    public function test_a_resource_class_that_is_not_a_resource_is_skipped_while_the_others_are_removed(): void
    {
        $paths = new Paths();
        $paths->addPath('/component/dummies', new PathItem(get: new Operation(tags: ['DummyComponent'])));
        $paths->addPath('/layouts', new PathItem(get: new Operation(tags: ['Layout'])));

        $decorated = $this->createStub(OpenApiFactoryInterface::class);
        $decorated->method('__invoke')->willReturn(new OpenApi(new Info('API', '1.0'), [], $paths));

        $metadata = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $metadata->method('create')->willReturnCallback(static function (string $resourceClass): ResourceMetadataCollection {
            if (AbstractComponent::class !== $resourceClass) {
                throw new ResourceClassNotFoundException($resourceClass);
            }

            return new ResourceMetadataCollection($resourceClass, [new ApiResource(shortName: 'DummyComponent')]);
        });

        $openApi = (new OpenApiFactory($decorated, $metadata))();

        self::assertNull($openApi->getPaths()->getPath('/component/dummies')->getGet());
        self::assertNotNull($openApi->getPaths()->getPath('/layouts')->getGet());
    }
}
