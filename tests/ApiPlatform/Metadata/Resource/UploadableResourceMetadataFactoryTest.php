<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Tests\ApiPlatform\Metadata\Resource;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use ApiPlatform\Core\Operation\PathSegmentNameGeneratorInterface;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Action\Uploadable\DownloadAction;
use Silverback\ApiComponentsBundle\Action\Uploadable\UploadAction;
use Silverback\ApiComponentsBundle\Annotation\Uploadable;
use Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource\UploadableResourceMetadataCollectionFactory;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReaderInterface;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;

class UploadableResourceMetadataFactoryTest extends TestCase
{
    /**
     * @var ResourceMetadataFactoryInterface|MockObject
     */
    private $decoratedMock;
    /**
     * @var Reader|MockObject
     */
    private $readerMock;
    /**
     * @var ManagerRegistry|MockObject
     */
    private $registryMock;
    /**
     * @var UploadableAttributeReaderInterface|MockObject
     */
    private $uploadableHelper;
    /**
     * @var PathSegmentNameGeneratorInterface|MockObject
     */
    private $pathSegmentNameGenerator;

    private UploadableResourceMetadataCollectionFactory $factory;

    protected function setUp(): void
    {
        $this->decoratedMock = $this->createMock(ResourceMetadataFactoryInterface::class);
        $this->readerMock = $this->createMock(Reader::class);
        $this->registryMock = $this->createMock(ManagerRegistry::class);
        $this->uploadableHelper = $this->createMock(UploadableAttributeReaderInterface::class);
        $this->pathSegmentNameGenerator = $this->createMock(PathSegmentNameGeneratorInterface::class);

        $this->factory = new UploadableResourceMetadataCollectionFactory($this->decoratedMock, $this->uploadableHelper, $this->pathSegmentNameGenerator);
    }

    public function test_create_returns_original_if_not_uploadable(): void
    {
        $resourceMetadata = new ResourceMetadata();

        $this->decoratedMock
            ->expects(self::once())
            ->method('create')
            ->willReturn($resourceMetadata);

        $this->uploadableHelper
            ->expects(self::once())
            ->method('isConfigured')
            ->willReturn(false);

        $output = $this->factory->create(DummyComponent::class);

        $this->assertEquals($resourceMetadata, $output);
        $this->assertNull($output->getCollectionOperations());
        $this->assertNull($output->getItemOperations());
    }

    public function test_create_returns_correct_array_keys(): void
    {
        $resourceMetadata = new ResourceMetadata();
        $resourceMetadata = $resourceMetadata->withShortName('MyName');
        $this->decoratedMock
            ->expects(self::once())
            ->method('create')
            ->willReturn($resourceMetadata);

        $this->uploadableHelper
            ->expects(self::once())
            ->method('isConfigured')
            ->willReturn(true);

        $this->uploadableHelper
            ->expects(self::once())
            ->method('getConfiguredProperties')
            ->willReturn(['resourceDocument' => []]);

        $this->pathSegmentNameGenerator
            ->expects(self::once())
            ->method('getSegmentName')
            ->with('MyName')
            ->willReturn('my_name');

        $output = $this->factory->create(Uploadable::class);

        self::assertIsArray($collectionOperations = $output->getCollectionOperations());
        $commonOpenApiContext = [
            'requestBody' => [
                'content' => [
                    'multipart/form-data' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'resourceDocument' => [
                                    'type' => 'string',
                                    'format' => 'binary',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        self::assertEquals(
            [
                'method' => 'POST',
                'controller' => UploadAction::class,
                'path' => '/my_name/upload',
                'deserialize' => false,
                'openapi_context' => $commonOpenApiContext,
                'stateless' => null,
            ],
            $collectionOperations['post_upload']
        );

        self::assertIsArray($itemOperations = $output->getItemOperations());

        self::assertEquals(
            [
                'method' => 'POST',
                'controller' => UploadAction::class,
                'path' => '/my_name/{id}/upload',
                'deserialize' => false,
                'openapi_context' => $commonOpenApiContext,
                'stateless' => null,
            ],
            $itemOperations['post_upload']
        );

//        self::assertEquals(
//            [
//                'method' => 'PATCH',
//                'controller' => UploadAction::class,
//                'path' => '/my_name/{id}/upload',
//                'deserialize' => false,
//                'openapi_context' => $commonOpenApiContext,
//                'stateless' => null,
//            ],
//            $itemOperations['patch_upload']
//        );

        self::assertEquals(
            [
                'method' => 'GET',
                'controller' => DownloadAction::class,
                'path' => '/my_name/{id}/download/{property}',
                'serialize' => false,
                'stateless' => null,
            ],
            $itemOperations['download']
        );
    }
}
