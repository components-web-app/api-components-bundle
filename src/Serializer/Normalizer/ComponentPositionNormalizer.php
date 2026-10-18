<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Serializer\Normalizer;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Proxy;
use Silverback\ApiComponentsBundle\DataCollector\CwaCollectorData;
use Silverback\ApiComponentsBundle\DataProvider\PageDataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\EventListener\Api\CacheHeadersEventListener;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Mercure\AnonymousResourceReader;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadataProvider;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PropertyAccess\Exception\NoSuchIndexException;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ComponentPositionNormalizer implements DenormalizerInterface, DenormalizerAwareInterface, NormalizerInterface, NormalizerAwareInterface
{
    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'COMPONENT_POSITION_NORMALIZER_ALREADY_CALLED';

    public function __construct(
        private readonly PageDataProvider $pageDataProvider,
        private readonly RequestStack $requestStack,
        private readonly PublishableStatusChecker $publishableStatusChecker,
        private readonly ManagerRegistry $registry,
        private readonly IriConverterInterface $iriConverter,
        private readonly ResourceMetadataProvider $resourceMetadataProvider,
        private readonly ?CwaCollectorData $collectorData = null,
    ) {
    }

    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        return !isset($context[self::ALREADY_CALLED]) && ComponentPosition::class === $type;
    }

    public function denormalize($data, $type, $format = null, array $context = []): ComponentPosition
    {
        $context[self::ALREADY_CALLED] = true;

        /** @var ComponentPosition $object */
        $object = $this->denormalizer->denormalize($data, $type, $format, $context);

        if (\is_array($data) && \array_key_exists('sortValue', $data)) {
            $object->markSortValueSent();
        }

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

        $staticComponent = $object->component;
        $resourceMetadata = $this->resourceMetadataProvider->findResourceMetadata($object);

        try {
            $object = $this->normalizeForPageData($object, $context);
            if ($object->pageDataProperty) {
                $resourceMetadata->setIsDynamicPosition(true);
                try {
                    $resourceMetadata->setPageDataPath($this->pageDataProvider->getOriginalRequestPath());
                } catch (UnprocessableEntityHttpException) {
                }
            } else {
                $resourceMetadata->setIsDynamicPosition(false);
            }

            $componentHidden = false;
            if ($object->component) {
                $component = $this->getPublishableComponent($object->component);
                if ($component) {
                    $object->component = $component;
                } else {
                    $componentHidden = true;
                }
            }
            if ($staticComponent && $visibleStaticComponent = $this->getPublishableComponent($staticComponent)) {
                $resourceMetadata->setStaticComponent($this->iriConverter->getIriFromResource($visibleStaticComponent));
            }

            $normalized = $this->normalizer->normalize($object, $format, $context);
            if ($componentHidden && \is_array($normalized)) {
                unset($normalized['component']);
            }

            return $normalized;
        } finally {
            $object->component = $staticComponent;
        }
    }

    private function getPublishableComponent(AbstractComponent $component): ?AbstractComponent
    {
        if (!$this->publishableStatusChecker->getAttributeReader()->isConfigured($component)) {
            return $component;
        }

        if ($this->publishableStatusChecker->isGranted($component)) {
            return $this->normalizePublishableComponent($component);
        }

        if ($this->publishableStatusChecker->isActivePublishedAt($component)) {
            return $component;
        }

        $this->recordHiddenPublication($component);

        return null;
    }

    private function recordHiddenPublication(AbstractComponent $component): void
    {
        if ($this->requestStack->getCurrentRequest()?->attributes->getBoolean(AnonymousResourceReader::REQUEST_ATTRIBUTE)) {
            return;
        }

        $publishedAt = $this->publishableStatusChecker->getPublicationDate($component);
        $request = $this->requestStack->getMainRequest();
        if (null === $publishedAt || null === $request) {
            return;
        }

        $recorded = $request->attributes->get(CacheHeadersEventListener::NEXT_PUBLICATION_ATTRIBUTE);
        if (!$recorded instanceof \DateTimeInterface || $publishedAt < $recorded) {
            $request->attributes->set(CacheHeadersEventListener::NEXT_PUBLICATION_ATTRIBUTE, $publishedAt);
        }
    }

    private function normalizePublishableComponent(AbstractComponent $component)
    {
        $configuration = $this->publishableStatusChecker->getAttributeReader()->getConfiguration($type = $component::class);
        $em = $this->registry->getManagerForClass($component::class);
        if (!$em) {
            throw new InvalidArgumentException(\sprintf('Could not find entity manager for class %s', $type));
        }
        /** @var ClassMetadata $classMetadata */
        $classMetadata = $em->getClassMetadata($type);
        $draft = $classMetadata->getFieldValue($component, $configuration->reverseAssociationName);

        return $draft ?? $component;
    }

    private function normalizeForPageData(ComponentPosition $object, array $context): ComponentPosition
    {
        if (!$object->pageDataProperty) {
            return $object;
        }

        $property = $object->pageDataProperty;

        if (isset($context['cwa_current_page_data'])) {
            $pageData = $context['cwa_current_page_data'];
        } elseif ($this->requestStack->getCurrentRequest()) {
            try {
                $pageData = $this->pageDataProvider->getPageData();
            } catch (UnprocessableEntityHttpException $e) {
                $this->collectorData?->recordPageDataResolution($property, null, 'no_path');

                return $object;
            }
            if (!$pageData) {
                $this->collectorData?->recordPageDataResolution($property, null, 'no_pagedata');

                return $object;
            }
        } else {
            $this->collectorData?->recordPageDataResolution($property, null, 'no_request');

            return $object;
        }

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        try {
            $component = $propertyAccessor->getValue($pageData, $property);
        } catch (UnexpectedTypeException|NoSuchIndexException|NoSuchPropertyException $e) {
            $this->collectorData?->recordPageDataResolution($property, null, 'property_missing');

            return $object;
        }

        if (!$component) {
            $this->collectorData?->recordPageDataResolution($property, null, 'no_component');

            return $object;
        }

        if (!$component instanceof AbstractComponent) {
            throw new InvalidArgumentException(\sprintf('The page data property %s is not a component', $property));
        }

        if (
            $this->publishableStatusChecker->getAttributeReader()->isConfigured($component)
            && !$this->publishableStatusChecker->isActivePublishedAt($component)
            && !$this->publishableStatusChecker->isGranted($component)
        ) {
            $this->collectorData?->recordPageDataResolution($property, $component::class, 'not_published');
            $this->recordHiddenPublication($component);

            return $object;
        }

        if ($object->componentGroup && null !== $object->componentGroup->allowedComponents) {
            $resourceClass = $component instanceof Proxy ? get_parent_class($component) : $component::class;
            $iri = $this->iriConverter->getIriFromResource(
                $resourceClass,
                UrlGeneratorInterface::ABS_PATH,
                (new GetCollection())->withClass($resourceClass),
            );
            if (!\in_array($iri, $object->componentGroup->allowedComponents, true)) {
                $this->collectorData?->recordPageDataResolution($property, $component::class, 'not_in_allowed');

                return $object;
            }
        }

        $object->setComponent($component);
        $this->collectorData?->recordPageDataResolution($property, $component::class, null);

        return $object;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [ComponentPosition::class => false];
    }
}
