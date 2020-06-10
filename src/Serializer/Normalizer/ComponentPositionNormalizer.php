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

use Silverback\ApiComponentsBundle\DataProvider\PageDataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Exception\UnexpectedValueException;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentPositionSortValueHelper;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareDenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * When creating a new component position the sort value should be set if not already explicitly set in the request.
 *
 * @author Daniel West <daniel@silverback.is>
 */
class ComponentPositionNormalizer implements CacheableSupportsMethodInterface, ContextAwareDenormalizerInterface, DenormalizerAwareInterface, ContextAwareNormalizerInterface, NormalizerAwareInterface
{
    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'COMPONENT_POSITION_NORMALIZER_ALREADY_CALLED';

    private PageDataProvider $pageDataProvider;
    private ComponentPositionSortValueHelper $componentPositionSortValueHelper;
    private RequestStack $requestStack;

    public function __construct(PageDataProvider $pageDataProvider, ComponentPositionSortValueHelper $componentPositionSortValueHelper, RequestStack $requestStack)
    {
        $this->pageDataProvider = $pageDataProvider;
        $this->componentPositionSortValueHelper = $componentPositionSortValueHelper;
        $this->requestStack = $requestStack;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }

    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        return !isset($context[self::ALREADY_CALLED]) && ComponentPosition::class === $type;
    }

    public function denormalize($data, $type, $format = null, array $context = [])
    {
        $context[self::ALREADY_CALLED] = true;

        /** @var ComponentPosition $object */
        $object = $this->denormalizer->denormalize($data, $type, $format, $context);
        $this->componentPositionSortValueHelper->calculateSortValue($object);

        return $object;
    }

    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        return $data instanceof ComponentPosition && $data->pageDataProperty && !isset($context[self::ALREADY_CALLED]) && (bool) $this->requestStack->getCurrentRequest();
    }

    /**
     * @param ComponentPosition $object
     * @param mixed|null        $format
     */
    public function normalize($object, $format = null, array $context = [])
    {
        $context[self::ALREADY_CALLED] = true;
        $pageData = $this->pageDataProvider->getPageData();
        if (!$pageData) {
            if ($object->component) {
                return $this->normalizer->normalize($object, $format, $context);
            }
            throw new UnexpectedValueException('Could not find page data for this route.');
        }

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $component = $propertyAccessor->getValue($pageData, $object->pageDataProperty);
        if (!$component) {
            throw new UnexpectedValueException(sprintf('Page data does not contain a value at %s', $object->pageDataProperty));
        }

        if (!$component instanceof AbstractComponent) {
            throw new InvalidArgumentException(sprintf('The page data property %s is not a component', $object->pageDataProperty));
        }

        $object->setComponent($component);

        return $this->normalizer->normalize($object, $format, $context);
    }
}
