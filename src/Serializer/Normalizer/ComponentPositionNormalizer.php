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

namespace Silverback\ApiComponentsBundle\Serializer\Normalizer;

use ApiPlatform\Api\IriConverterInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\DataProvider\PageDataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentPositionSortValueHelper;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadataProvider;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PropertyAccess\Exception\NoSuchIndexException;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * When creating a new component position the sort value should be set if not already explicitly set in the request.
 *
 * @author Daniel West <daniel@silverback.is>
 */
class ComponentPositionNormalizer implements CacheableSupportsMethodInterface, DenormalizerInterface, DenormalizerAwareInterface, NormalizerInterface, NormalizerAwareInterface
{
    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'COMPONENT_POSITION_NORMALIZER_ALREADY_CALLED';

    public function __construct(
        private readonly PageDataProvider $pageDataProvider,
        private readonly ComponentPositionSortValueHelper $componentPositionSortValueHelper,
        private readonly RequestStack $requestStack,
        private readonly PublishableStatusChecker $publishableStatusChecker,
        private readonly ManagerRegistry $registry,
        private readonly IriConverterInterface $iriConverter,
        private readonly ResourceMetadataProvider $resourceMetadataProvider
    ) {
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }

    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        return !isset($context[self::ALREADY_CALLED]) && ComponentPosition::class === $type;
    }

    public function denormalize($data, $type, $format = null, array $context = []): ComponentPosition
    {
        $context[self::ALREADY_CALLED] = true;

        $originalObject = $context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? null;
        $originalSortValue = $originalObject ? $originalObject->sortValue : null;

        /** @var ComponentPosition $object */
        $object = $this->denormalizer->denormalize($data, $type, $format, $context);

        $this->componentPositionSortValueHelper->calculateSortValue($object, $originalSortValue);

        return $object;
    }

    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        return $data instanceof ComponentPosition && !isset($context[self::ALREADY_CALLED]);
    }

    public function normalize($object, $format = null, array $context = []): float|array|\ArrayObject|bool|int|string|null
    {
        /* @var ComponentPosition $object */
        /* @var mixed|null        $format */

        $context[self::ALREADY_CALLED] = true;

        $staticComponent = $object->component ? $this->getPublishableComponent($object->component) : null;
        $resourceMetadata = $this->resourceMetadataProvider->findResourceMetadata($object);

        if ($staticComponent) {
            $resourceMetadata->setStaticComponent($this->iriConverter->getIriFromResource($staticComponent));
        }

        $object = $this->normalizeForPageData($object);
        if ($object->component !== $staticComponent) {
            $component = $object->component;
            $object->setComponent($this->getPublishableComponent($component));
            $resourceMetadata->setPageDataPath($this->pageDataProvider->getOriginalRequestPath());
        }

        return $this->normalizer->normalize($object, $format, $context);
    }

    private function getPublishableComponent($component)
    {
        if (
            $component &&
            $this->publishableStatusChecker->getAnnotationReader()->isConfigured($component) &&
            $this->publishableStatusChecker->isGranted($component)
        ) {
            return $this->normalizePublishableComponent($component);
        }

        return $component;
    }

    private function normalizePublishableComponent(AbstractComponent $component)
    {
        $configuration = $this->publishableStatusChecker->getAnnotationReader()->getConfiguration($type = \get_class($component));
        $em = $this->registry->getManagerForClass(\get_class($component));
        if (!$em) {
            throw new InvalidArgumentException(sprintf('Could not find entity manager for class %s', $type));
        }
        /** @var ClassMetadataInfo $classMetadata */
        $classMetadata = $em->getClassMetadata($type);
        $draft = $classMetadata->getFieldValue($component, $configuration->reverseAssociationName);

        return $draft ?? $component;
    }

    private function normalizeForPageData(ComponentPosition $object): ComponentPosition
    {
        if (!$object->pageDataProperty || !$this->requestStack->getCurrentRequest()) {
            return $object;
        }
        try {
            $pageData = $this->pageDataProvider->getPageData();
        } catch (UnprocessableEntityHttpException $e) {
            // when serializing for mercure, we do not need the path header
            return $object;
        }

        if (!$pageData) {
            return $object;
        }

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        try {
            $component = $propertyAccessor->getValue($pageData, $object->pageDataProperty);
        } catch (UnexpectedTypeException|NoSuchIndexException|NoSuchPropertyException $e) {
            return $object;
        }

        // optional to have the page data component found
        if (!$component) {
            return $object;
        }

        // it must be a component if it is found though
        if (!$component instanceof AbstractComponent) {
            throw new InvalidArgumentException(sprintf('The page data property %s is not a component', $object->pageDataProperty));
        }

        // populate the position
        $object->setComponent($component);

        return $object;
    }
}
