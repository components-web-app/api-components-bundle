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
use Silverback\ApiComponentsBundle\Action\Uploadable\UploadableUploadAction;
use Silverback\ApiComponentsBundle\Annotation\Uploadable;
use Silverback\ApiComponentsBundle\AnnotationReader\UploadableAnnotationReaderInterface;
use Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource\UploadableResourceMetadataFactory;
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
     * @var UploadableAnnotationReaderInterface|MockObject
     */
    private $uploadableHelper;
    /**
     * @var PathSegmentNameGeneratorInterface|MockObject
     */
    private $pathSegmentNameGenerator;

    private UploadableResourceMetadataFactory $factory;

    protected function setUp(): void
    {
        $this->decoratedMock = $this->createMock(ResourceMetadataFactoryInterface::class);
        $this->readerMock = $this->createMock(Reader::class);
        $this->registryMock = $this->createMock(ManagerRegistry::class);
        $this->uploadableHelper = $this->createMock(UploadableAnnotationReaderInterface::class);
        $this->pathSegmentNameGenerator = $this->createMock(PathSegmentNameGeneratorInterface::class);

        $this->factory = new UploadableResourceMetadataFactory($this->decoratedMock, $this->uploadableHelper, $this->pathSegmentNameGenerator);
    }

    public function test_create_returns_original_if_not_uploadable(): void
    {
        $resourceMetadata = new ResourceMetadata();

        $this->decoratedMock
            ->expects($this->once())
            ->method('create')
            ->willReturn($resourceMetadata);

        $this->uploadableHelper
            ->expects($this->once())
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
            ->expects($this->once())
            ->method('create')
            ->willReturn($resourceMetadata);

        $this->uploadableHelper
            ->expects($this->once())
            ->method('isConfigured')
            ->willReturn(true);

        $this->uploadableHelper
            ->expects($this->once())
            ->method('getConfiguredProperties')
            ->willReturn(['resourceDocument']);

        $this->pathSegmentNameGenerator
            ->expects($this->once())
            ->method('getSegmentName')
            ->with('MyName')
            ->willReturn('my_name');

        $output = $this->factory->create(Uploadable::class);

        $this->assertIsArray($collectionOperations = $output->getCollectionOperations());
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
        $this->assertEquals([
            'method' => 'POST',
            'controller' => UploadableUploadAction::class,
            'path' => '/my_name/upload',
            'deserialize' => false,
            'openapi_context' => $commonOpenApiContext,
        ], $collectionOperations['post_upload']);

        $this->assertIsArray($itemOperations = $output->getItemOperations());

        $this->assertEquals([
            'method' => 'PUT',
            'controller' => UploadableUploadAction::class,
            'path' => '/my_name/{id}/upload',
            'deserialize' => false,
            'openapi_context' => $commonOpenApiContext,
        ], $itemOperations['put_upload']);

        $this->assertEquals([
            'method' => 'PATCH',
            'controller' => UploadableUploadAction::class,
            'path' => '/my_name/{id}/upload',
            'deserialize' => false,
            'openapi_context' => $commonOpenApiContext,
        ], $itemOperations['patch_upload']);

        $this->assertEquals([
            'method' => 'GET',
            'controller' => UploadableUploadAction::class,
            'path' => '/my_name/{id}/download/resource_document',
        ], $itemOperations['download_resource_document']);
    }
}
