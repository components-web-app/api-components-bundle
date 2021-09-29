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

use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\AnnotationReader\UploadableAnnotationReader;
use Silverback\ApiComponentsBundle\Factory\Uploadable\MediaObjectFactory;
use Silverback\ApiComponentsBundle\Model\Uploadable\DataUriFile;
use Silverback\ApiComponentsBundle\Model\Uploadable\UploadedDataUriFile;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareDenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Traversable;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class UploadableNormalizer implements CacheableSupportsMethodInterface, ContextAwareDenormalizerInterface, DenormalizerAwareInterface, ContextAwareNormalizerInterface, NormalizerAwareInterface
{
    use ClassMetadataTrait;

    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'UPLOADABLE_NORMALIZER_ALREADY_CALLED';

    private MediaObjectFactory $mediaObjectFactory;
    private UploadableAnnotationReader $annotationReader;
    private PropertyAccessor $propertyAccessor;

    public function __construct(MediaObjectFactory $mediaObjectFactory, UploadableAnnotationReader $annotationReader, ManagerRegistry $registry)
    {
        $this->mediaObjectFactory = $mediaObjectFactory;
        $this->annotationReader = $annotationReader;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
        $this->initRegistry($registry);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        return !isset($context[self::ALREADY_CALLED]) && $this->annotationReader->isConfigured($type);
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $type, $format = null, array $context = [])
    {
        $context[self::ALREADY_CALLED] = true;

        foreach ($data as $fieldName => $value) {
            try {
                $reflectionProperty = new \ReflectionProperty($type, $fieldName);
            } catch (\ReflectionException $exception) {
                // Property does not exist on class: just ignore it.
                continue;
            }

            // Property is not an UploadableField: just ignore it.
            if (!$this->annotationReader->isFieldConfigured($reflectionProperty)) {
                continue;
            }

            // Value is empty: set it to null.
            if (empty($value)) {
                $data[$fieldName] = new File('__DELETE__', false);
                continue;
            }

            try {
                $file = new DataUriFile($value);
                $data[$fieldName] = new UploadedDataUriFile($file, Uuid::uuid4() . '.' . $file->getExtension());
            } catch (FileException $exception) {
                throw new NotNormalizableValueException($exception->getMessage());
            }
        }

        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }

    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        if (!\is_object($data) || $data instanceof Traversable) {
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
            $this->annotationReader->isConfigured($data);
    }

    public function normalize($object, $format = null, array $context = [])
    {
        $context[self::ALREADY_CALLED][] = $this->propertyAccessor->getValue($object, 'id');

        $mediaObjects = $this->mediaObjectFactory->createMediaObjects($object);
        if ($mediaObjects) {
            $mediaObjects = $this->normalizer->normalize(
                $mediaObjects,
                $format,
                [
                    'jsonld_embed_context' => true,
                    'skip_null_values' => $context['skip_null_values'] ?? false,
                ]
            );
            $context[MetadataNormalizer::METADATA_CONTEXT]['media_objects'] = $mediaObjects;
        }

        $fieldConfigurations = $this->annotationReader->getConfiguredProperties($object, true);
        $classMetadata = $this->getClassMetadata($object);
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        foreach ($fieldConfigurations as $fileField => $fieldConfiguration) {
            $propertyAccessor->setValue($object, $fileField, null);
            $classMetadata->setFieldValue($object, $fieldConfiguration->property, null);
        }

        return $this->normalizer->normalize($object, $format, $context);
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }
}
