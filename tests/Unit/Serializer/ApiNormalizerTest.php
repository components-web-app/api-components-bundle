<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Serializer;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentBundle\Entity\Content\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Content\Component\Form\Form;
use Silverback\ApiComponentBundle\Entity\Content\Component\Form\FormView;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Form\FormViewFactory;
use Silverback\ApiComponentBundle\Imagine\PathResolver;
use Silverback\ApiComponentBundle\Serializer\ApiNormalizer;
use Silverback\ApiComponentBundle\Serializer\ImageMetadata;
use Silverback\ApiComponentBundle\Tests\TestBundle\Entity\FileComponent;
use Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestType;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\Serializer;

class ApiNormalizerTest extends TestCase
{
    /**
     * @var MockObject|AbstractItemNormalizer
     */
    private $normalizerInterfaceMock;
    /**
     * @var MockObject|CacheManager
     */
    private $cacheManagerMock;
    /**
     * @var MockObject|FormViewFactory
     */
    private $formViewFactoryMock;
    /**
     * @var ApiNormalizer
     */
    private $apiNormalizer;
    /**
     * @var string
     */
    private $filePath;
    /** @var MockObject|PathResolver */
    private $pathResolverMock;
    /** @var MockObject|EntityManagerInterface */
    private $entityManagerMock;
    /** @var MockObject|ContextAwareCollectionDataProviderInterface */
    private $dataProviderMock;
    /** @var string */
    private $publicFilePath;

    public function __construct(?string $name = null, array $data = [], string $dataName = '')
    {
        $this->publicFilePath = 'images/testImage.jpg';
        $this->filePath = realpath(__DIR__ . '/../../app/public/' . $this->publicFilePath);
        parent::__construct($name, $data, $dataName);
    }

    public function setUp()
    {
        $projectRoot = realpath(__DIR__ . '/../../app');

        $this->normalizerInterfaceMock = $this->getMockBuilder(AbstractItemNormalizer::class)->disableOriginalConstructor()->getMock();
        $this->cacheManagerMock = $this->getMockBuilder(CacheManager::class)->disableOriginalConstructor()->getMock();
        $this->formViewFactoryMock = $this->getMockBuilder(FormViewFactory::class)->disableOriginalConstructor()->getMock();

        $this->pathResolverMock = $this->getMockBuilder(PathResolver::class)->setConstructorArgs([
            'roots' => [
                $projectRoot . '/public'
            ]
        ])->getMock();


        $this->entityManagerMock = $this->getMockBuilder(EntityManagerInterface::class)->getMock();
        $this->dataProviderMock = $this->getMockBuilder(ContextAwareCollectionDataProviderInterface::class)->getMock();
        $routerMock = $this->getMockBuilder(RouterInterface::class)->disableOriginalConstructor()->getMock();
        $routerMock->method('generate')->willReturn($this->publicFilePath);

        $iriConverterMock = $this->getMockBuilder(IriConverterInterface::class)->disableOriginalConstructor()->getMock();
        $this->apiNormalizer = new ApiNormalizer(
            $this->normalizerInterfaceMock,
            $this->cacheManagerMock,
            $this->formViewFactoryMock,
            $this->pathResolverMock,
            $this->entityManagerMock,
            $this->dataProviderMock,
            $routerMock,
            $iriConverterMock,
            $projectRoot
        );
    }

    public function test_supports_normalizer(): void
    {
        $args = [[], null];
        $this->normalizerInterfaceMock
            ->expects($this->once())
            ->method('supportsNormalization')
            ->with(...$args)
            ->willReturn(true)
        ;
        $this->assertTrue($this->apiNormalizer->supportsNormalization(...$args));
    }

    public function test_supports_denormalization(): void
    {
        $args = [[], null];
        $this->normalizerInterfaceMock
            ->expects($this->once())
            ->method('supportsDenormalization')
            ->with(...$args)
            ->willReturn(true)
        ;
        $this->assertTrue($this->apiNormalizer->supportsDenormalization(...$args));
    }

    public function test_imagine_supported_file(): void
    {
        $this->assertFalse($this->apiNormalizer->isImagineSupportedFile('not_a_file'));
        $this->assertFalse($this->apiNormalizer->isImagineSupportedFile('dummyfile.txt'));
        $this->assertFalse($this->apiNormalizer->isImagineSupportedFile('images/apiPlatform.svg'));
        $this->assertTrue($this->apiNormalizer->isImagineSupportedFile($this->filePath));
    }

    public function test_set_serializer(): void
    {
        $serializer = new Serializer();
        $this->normalizerInterfaceMock
            ->expects($this->once())
            ->method('setSerializer')
            ->with($serializer)
        ;
        $this->apiNormalizer->setSerializer($serializer);
    }

    public function test_denormalize(): void
    {
        $abstractComponentMock = $this->getMockBuilder(AbstractComponent::class)->getMock();
        $args = [[], $abstractComponentMock, null];
        $this->normalizerInterfaceMock
            ->expects($this->once())
            ->method('denormalize')
            ->with(...array_merge($args, [['allow_extra_attributes' => false]]))
            ->willReturn($abstractComponentMock)
        ;
        $this->apiNormalizer->denormalize(...$args);
    }

    public function test_normalize_file(): void
    {
        $this->pathResolverMock
            ->expects($this->once())
            ->method('resolve')
            ->with($this->filePath)
            ->willReturn($this->publicFilePath)
        ;

        $fileComponent = new FileComponent();
        $fileComponent->setFilePath($this->filePath);

        foreach (FileComponent::getImagineFilters() as $returnKey => $filter) {
            $this->cacheManagerMock
                ->expects($this->once())
                ->method('getBrowserPath')
                ->with($this->publicFilePath, $filter)
                ->willReturn(sprintf('http://website.com/%s', $this->publicFilePath))
            ;
        }

        $this->normalizerInterfaceMock
            ->expects($this->once())
            ->method('normalize')
            ->with($fileComponent)
            ->willReturn([])
        ;

        $data = $this->apiNormalizer->normalize($fileComponent);
//        $expected = new ImageMetadata($this->filePath, $this->publicFilePath);
//        $this->assertEquals($expected, $data['file:image']);
//        foreach (FileComponent::getImagineFilters() as $returnKey => $filter) {
//            $expected = new ImageMetadata($this->filePath, $this->publicFilePath, $filter);
//            $this->assertEquals($expected, $data['file:imagine'][$returnKey]);
//        }
    }

    public function test_normalize_form(): void
    {
        $formEntity = new Form();
        $formEntity->setClassName(TestType::class);

        /** @var MockObject|\Symfony\Component\Form\FormView $formViewMock */
        $formViewMock = $this->getMockBuilder(\Symfony\Component\Form\FormView::class)->getMock();
        $formViewMock
            ->expects($this->once())
            ->method('getIterator')
            ->willReturn(new \ArrayIterator())
        ;
        $formView = new FormView($formViewMock);

        $this->formViewFactoryMock
            ->expects($this->once())
            ->method('create')
            ->with($formEntity)
            ->willReturn($formView)
        ;

        $this->normalizerInterfaceMock
            ->expects($this->once())
            ->method('normalize')
            ->with($formEntity, null, [])
            ->willReturn('normalized_response')
        ;

        $data = $this->apiNormalizer->normalize($formEntity);
        $this->assertEquals('normalized_response', $data);
    }
}
