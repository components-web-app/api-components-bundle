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

use ApiPlatform\Core\Api\IriConverterInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class PageDataNormalizer implements ContextAwareNormalizerInterface, CacheableSupportsMethodInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'PAGE_DATA_NORMALIZER_ALREADY_CALLED';

    private ManagerRegistry $registry;
    private IriConverterInterface $iriConverter;
    private PropertyAccessor $propertyAccessor;

    public function __construct(
        ManagerRegistry $registry,
        IriConverterInterface $iriConverter
    ) {
        $this->registry = $registry;
        $this->iriConverter = $iriConverter;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    public function normalize($object, $format = null, array $context = [])
    {
        $context[self::ALREADY_CALLED][] = $this->propertyAccessor->getValue($object, 'id');
        $context[MetadataNormalizer::METADATA_CONTEXT]['page_data_props'] = $this->getPageDataProps($object);

        return $this->normalizer->normalize($object, $format, $context);
    }

    private function getPageDataProps(AbstractPageData $data): array
    {
        $abstractRefl = new \ReflectionClass(AbstractPageData::class);
        $reflProps = $abstractRefl->getProperties();
        $abstractProps = array_map(static function (\ReflectionProperty $prop) {
            return $prop->name;
        }, $reflProps);

        $resourceClass = \get_class($data);
        $manager = $this->registry->getManagerForClass($resourceClass);
        if (!$manager) {
            return [];
        }
        $classMetadata = $manager->getClassMetadata($resourceClass);
        $assocFields = array_filter($classMetadata->getAssociationNames(), static function ($name) use ($abstractProps) {
            return !\in_array($name, $abstractProps, true);
        });
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $props = [];
        foreach ($assocFields as $assocField) {
            $assocData = $propertyAccessor->getValue($data, $assocField);
            if (!$assocData) {
                $resourceClass = $classMetadata->getAssociationTargetClass($assocField);
                $props[$assocField] = $this->iriConverter->getIriFromResourceClass($resourceClass);
            }
        }

        return $props;
    }

    public function supportsNormalization($data, $format = null, $context = []): bool
    {
        if (!\is_object($data) || $data instanceof \Traversable) {
            return false;
        }
        if (!isset($context[self::ALREADY_CALLED])) {
            $context[self::ALREADY_CALLED] = [];
        }
        try {
            $id = $this->propertyAccessor->getValue($data, 'id');
        } catch (NoSuchPropertyException $e) {
            return false;
        }

        return !\in_array($id, $context[self::ALREADY_CALLED], true) &&
            is_a($data, AbstractPageData::class);
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }
}
