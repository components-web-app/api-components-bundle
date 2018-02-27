<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Serializer;

use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Component\FileInterface;
use Silverback\ApiComponentBundle\Entity\Component\Form\Form;
use Silverback\ApiComponentBundle\Entity\Component\Form\FormView;
use Silverback\ApiComponentBundle\Factory\Entity\Component\Form\FormViewFactory;
use Silverback\ApiComponentBundle\Serializer\ApiNormalizer;
use Silverback\ApiComponentBundle\Tests\TestBundle\Entity\FileComponent;
use Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestType;
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

    public function setUp()
    {
        $this->normalizerInterfaceMock = $this->getMockBuilder(AbstractItemNormalizer::class)->disableOriginalConstructor()->getMock();
        $this->cacheManagerMock = $this->getMockBuilder(CacheManager::class)->disableOriginalConstructor()->getMock();
        $this->formViewFactoryMock = $this->getMockBuilder(FormViewFactory::class)->disableOriginalConstructor()->getMock();
        $this->apiNormalizer = new ApiNormalizer(
            $this->normalizerInterfaceMock,
            __DIR__ . '/../../app/',
            $this->cacheManagerMock,
            $this->formViewFactoryMock
        );
    }

    public function test_supports_normalizer()
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

    public function test_supports_denormalization()
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

    public function test_imagine_supported_file()
    {
        $this->assertFalse($this->apiNormalizer->isImagineSupportedFile('not_a_file'));
        $this->assertFalse($this->apiNormalizer->isImagineSupportedFile('dummyfile.txt'));
        $this->assertFalse($this->apiNormalizer->isImagineSupportedFile('images/apiPlatform.svg'));
        $this->assertTrue($this->apiNormalizer->isImagineSupportedFile('images/testImage.jpg'));
    }

    public function test_set_serializer()
    {
        $serializer = new Serializer();
        $this->normalizerInterfaceMock
            ->expects($this->once())
            ->method('setSerializer')
            ->with($serializer)
        ;
        $this->apiNormalizer->setSerializer($serializer);
    }

    public function test_denormalize()
    {
        $abstractComponentParentMock = $this->getMockBuilder(AbstractComponent::class)->getMock();
        $abstractComponentMock = $this->getMockBuilder(AbstractComponent::class)->getMock();
        $abstractComponentMock
            ->expects($this->once())
            ->method('getParent')
            ->willReturn($abstractComponentParentMock)
        ;
        $abstractComponentMock
            ->expects($this->once())
            ->method('addToParentComponent')
            ->with($abstractComponentParentMock)
        ;

        $args = [[], $abstractComponentMock, null];
        $this->normalizerInterfaceMock
            ->expects($this->once())
            ->method('denormalize')
            ->with(...$args)
            ->willReturn($abstractComponentMock)
        ;
        $this->apiNormalizer->denormalize(...array_merge($args, [['allow_extra_attributes' => false]]));
    }

    public function test_normalize_file()
    {
        $filePath = 'images/testImage.jpg';
        $fileComponent = new FileComponent();
        $fileComponent->setFilePath($filePath);

        foreach (FileComponent::getImagineFilters() as $returnKey => $filter) {
            $this->cacheManagerMock
                ->expects($this->once())
                ->method('getBrowserPath')
                ->with($filePath, $filter)
                ->willReturn(sprintf('http://website.com/%s/%s', $filter, $filePath))
            ;
        }

        $this->normalizerInterfaceMock
            ->expects($this->once())
            ->method('normalize')
            ->with($fileComponent)
            ->willReturn([])
        ;

        $data = $this->apiNormalizer->normalize($fileComponent);
        $this->assertEquals(100, $data['width']);
        $this->assertEquals(100, $data['height']);
        foreach (FileComponent::getImagineFilters() as $returnKey => $filter) {
            $this->assertEquals(sprintf('/%s/%s', $filter, $filePath), $data[$returnKey]);
        }
    }

    public function test_normalize_form()
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

        $data = $this->apiNormalizer->normalize($formEntity);
        $this->assertEquals($formView, $data['form']);
    }
}
