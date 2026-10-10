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

use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReaderInterface;
use Silverback\ApiComponentsBundle\Factory\Uploadable\MediaObjectFactory;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Silverback\ApiComponentsBundle\Model\Uploadable\DataUriFile;
use Silverback\ApiComponentsBundle\Model\Uploadable\UploadedDataUriFile;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadataProvider;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class UploadableNormalizer implements DenormalizerInterface, DenormalizerAwareInterface, NormalizerInterface, NormalizerAwareInterface
{
    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'UPLOADABLE_NORMALIZER_ALREADY_CALLED';

    private PropertyAccessor $propertyAccessor;

    public function __construct(
        private MediaObjectFactory $mediaObjectFactory,
        private UploadableAttributeReaderInterface $annotationReader,
        private UploadableFileManager $uploadableFileManager,
        private ResourceMetadataProvider $resourceMetadataProvider,
    ) {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        return !isset($context[self::ALREADY_CALLED]) && $this->annotationReader->isConfigured($type);
    }

    /**
     * @param list<string> $deletedProperties storage properties the payload explicitly cleared
     */
    private function findConfiguredFields(iterable $data, string $type, array &$deletedProperties): iterable
    {
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

            // Value is empty: set it to null. Might be blank string
            if (empty($value)) {
                $fieldConfig = $this->annotationReader->getPropertyConfiguration($reflectionProperty);
                $deletedProperties[] = $fieldConfig->property;
                $data[$fieldName] = null;
                continue;
            }

            try {
                $file = new DataUriFile($value);
                $data[$fieldName] = new UploadedDataUriFile($file, Uuid::uuid4() . '.' . $file->getExtension());
            } catch (FileException $exception) {
                throw new NotNormalizableValueException($exception->getMessage());
            }
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $type, $format = null, array $context = []): mixed
    {
        $context[self::ALREADY_CALLED] = true;

        $deletedProperties = [];
        if (is_iterable($data)) {
            $data = $this->findConfiguredFields($data, $type, $deletedProperties);
        }

        $object = $this->denormalizer->denormalize($data, $type, $format, $context);

        if (\is_object($object)) {
            foreach ($deletedProperties as $property) {
                $this->uploadableFileManager->addDeletedField($object, $property);
            }
        }

        return $object;
    }

    public function supportsNormalization($data, $format = null, array $context = []): bool
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

        return !\in_array($id, $context[self::ALREADY_CALLED], true)
            && $this->annotationReader->isConfigured($data);
    }

    public function normalize($object, $format = null, array $context = []): float|array|\ArrayObject|bool|int|string|null
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

            $resourceMetadata = $this->resourceMetadataProvider->findResourceMetadata($object);
            $resourceMetadata->setMediaObjects($mediaObjects);
        }

        $normalized = $this->normalizer->normalize($object, $format, $context);
        if (!\is_array($normalized) && !$normalized instanceof \ArrayObject) {
            return $normalized;
        }

        foreach ($this->annotationReader->getConfiguredProperties($object, true) as $fileField => $fieldConfiguration) {
            unset($normalized[$fileField], $normalized[$fieldConfiguration->property]);
        }

        return $normalized;
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['object' => false];
    }
}
