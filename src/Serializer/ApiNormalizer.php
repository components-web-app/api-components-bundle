<?php

namespace Silverback\ApiComponentBundle\Serializer;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use function file_exists;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Silverback\ApiComponentBundle\Entity\Content\Component\Collection\Collection;
use Silverback\ApiComponentBundle\Entity\Content\Component\ComponentLocation;
use Silverback\ApiComponentBundle\Entity\Content\Component\Form\Form;
use Silverback\ApiComponentBundle\Entity\Content\Dynamic\AbstractDynamicPage;
use Silverback\ApiComponentBundle\Entity\Content\FileInterface;
use Silverback\ApiComponentBundle\Entity\Content\Page;
use Silverback\ApiComponentBundle\Entity\Layout\Layout;
use Silverback\ApiComponentBundle\Factory\Entity\Content\Component\Form\FormViewFactory;
use Silverback\ApiComponentBundle\Imagine\PathResolver;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class ApiNormalizer implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface
{
    private $decorated;
    private $imagineCacheManager;
    private $formViewFactory;
    private $pathResolver;
    private $em;
    private $projectDir;
    private $collectionDataProvider;
    private $serializer;
    private $router;
    private $iriConverter;
    private $fileNormalizer;

    /**
     * FileNormalizer constructor.
     * @param NormalizerInterface $decorated
     * @param CacheManager $imagineCacheManager
     * @param FormViewFactory $formViewFactory
     * @param PathResolver $pathResolver
     * @param EntityManagerInterface $entityManager
     * @param ContextAwareCollectionDataProviderInterface $collectionDataProvider
     * @param RouterInterface $router
     * @param IriConverterInterface $iriConverter
     * @param string $projectDir
     * @param FileNormalizer $fileNormalizer
     */
    public function __construct(
        NormalizerInterface $decorated,
        CacheManager $imagineCacheManager,
        FormViewFactory $formViewFactory,
        PathResolver $pathResolver,
        EntityManagerInterface $entityManager,
        ContextAwareCollectionDataProviderInterface $collectionDataProvider,
        RouterInterface $router,
        IriConverterInterface $iriConverter,
        string $projectDir,
        FileNormalizer $fileNormalizer
    ) {
        if (!$decorated instanceof DenormalizerInterface) {
            throw new \InvalidArgumentException(sprintf('The decorated normalizer must implement the %s.', DenormalizerInterface::class));
        }
        if (!$decorated instanceof AbstractNormalizer) {
            throw new \InvalidArgumentException(sprintf('The decorated normalizer must implement the %s.', AbstractNormalizer::class));
        }
        // If a page will list itself again as a component then we should re-serialize it as it'll have different context/groups applied
        $decorated->setCircularReferenceLimit(2);
        $this->decorated = $decorated;
        $this->imagineCacheManager = $imagineCacheManager;
        $this->formViewFactory = $formViewFactory;
        $this->pathResolver = $pathResolver;
        $this->em = $entityManager;
        $this->collectionDataProvider = $collectionDataProvider;
        $this->router = $router;
        $this->iriConverter = $iriConverter;
        $this->projectDir = $projectDir;
        $this->fileNormalizer = $fileNormalizer;

        $normalizers = array(new ObjectNormalizer());
        $this->serializer = new Serializer($normalizers);
    }

    /**
     * @param mixed $data
     * @param string|null $format
     * @return bool
     */
    public function supportsNormalization($data, $format = null): bool
    {
        return $this->decorated->supportsNormalization($data, $format);
    }

    /**
     * @param $object
     * @param string|null $format
     * @param array $context
     * @return array|bool|float|int|string
     * @throws \Symfony\Component\Serializer\Exception\LogicException
     * @throws \Symfony\Component\Serializer\Exception\InvalidArgumentException
     * @throws \Symfony\Component\Serializer\Exception\CircularReferenceException
     */
    public function normalize($object, $format = null, array $context = [])
    {
        if (($object instanceof Page || $object instanceof AbstractDynamicPage) && !$object->getLayout()) {
            // Should we be using the ItemDataProvider (or detect data provider and use that, we already use a custom data provider for layouts)
            $object->setLayout($this->em->getRepository(Layout::class)->findOneBy(['default' => true]));
        }
        if ($object instanceof AbstractDynamicPage) {
            $object = $this->populateDynamicComponents($object);
        }
        if ($object instanceof Collection) {
            // We should really find whatever the data provider is currently for the resource instead of just using the default
            $object->setCollection($this->collectionDataProvider->getCollection($object->getResource(), 'GET', $context));
        }
        if ($object instanceof Form && !$object->getForm()) {
            $object->setForm($this->formViewFactory->create($object));
        }

        $data = $this->decorated->normalize($object, $format, $context);
        // data may be a string if circular reference
        if (\is_array($data) && $object instanceof FileInterface) {
            $data = array_merge($data, $this->getFileData($object, $format, $context));
        }
        return $data;
    }

    /**
     * @param \Silverback\ApiComponentBundle\Entity\Content\FileInterface $object
     * @param null|string $format
     * @param array $context
     * @return array
     */
    private function getFileData(FileInterface $object, $format = null, array $context = []): array
    {
        $data = [];
        $filePath = $object->getFilePath();
        if ($filePath && file_exists($filePath)) {
            $objectId = $this->iriConverter->getIriFromItem($object);
            $data['file:publicPath'] = $this->router->generate(
                'files_upload',
                [ 'field' => 'filePath', 'id' => $objectId ]
            );
            // $this->getPublicPath($filePath);
            if (\exif_imagetype($filePath)) {
                $data['file:image'] = $this->serializer->normalize(
                    new ImageMetadata($filePath, $data['file:publicPath']),
                    $format,
                    $context
                );
                $supported = $this->fileNormalizer->isImagineSupportedFile($filePath);
                if ($supported) {
                    $imagineData = [];
                    foreach ($object::getImagineFilters() as $returnKey => $filter) {
                        // Strip path root from beginning of string.
                        // Whatever image roots are set in imagine will be looped and removed from the start of the string
                        $resolvedPath = $this->pathResolver->resolve($filePath);
                        $imagineBrowserPath = $this->imagineCacheManager->getBrowserPath($resolvedPath, $filter);
                        $imagineFilePath = ltrim(parse_url(
                            $imagineBrowserPath,
                            PHP_URL_PATH
                        ), '/');
                        $realPath = sprintf('%s/public/%s', $this->projectDir, $imagineFilePath);
                        $imagineData[$returnKey] = $this->serializer->normalize(
                            new ImageMetadata($realPath, $imagineFilePath, $filter),
                            $format,
                            $context
                        );
                    }
                    $data['file:imagine'] = $imagineData;
                }
            }
        }
        return $data;
    }

    /**
     * @param mixed $data
     * @param string $type
     * @param string|null $format
     * @return bool
     */
    public function supportsDenormalization($data, $type, $format = null): bool
    {
        return $this->decorated->supportsDenormalization($data, $type, $format);
    }

    /**
     * @param mixed $data
     * @param string $class
     * @param string|null $format
     * @param array $context
     * @return object
     * @throws \Symfony\Component\Serializer\Exception\UnexpectedValueException
     * @throws \Symfony\Component\Serializer\Exception\RuntimeException
     * @throws \Symfony\Component\Serializer\Exception\LogicException
     * @throws \Symfony\Component\Serializer\Exception\InvalidArgumentException
     * @throws \Symfony\Component\Serializer\Exception\ExtraAttributesException
     * @throws \Symfony\Component\Serializer\Exception\BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function denormalize($data, $class, $format = null, array $context = [])
    {
        $context['allow_extra_attributes'] = $class === Form::class;
        return $this->decorated->denormalize($data, $class, $format, $context);
    }

    /**
     * @param SerializerInterface $serializer
     */
    public function setSerializer(SerializerInterface $serializer)
    {
        if ($this->decorated instanceof SerializerAwareInterface) {
            $this->decorated->setSerializer($serializer);
        }
    }

    /**
     * @param AbstractDynamicPage $page
     * @return AbstractDynamicPage
     */
    public function populateDynamicComponents(AbstractDynamicPage $page): AbstractDynamicPage
    {
        $locations = $this->em->getRepository(ComponentLocation::class)->findByDynamicPage($page);
        if ($locations) {
            $page->setComponentLocations($locations);
        }
        return $page;
    }
}
