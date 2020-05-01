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

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\AnnotationReader\UploadableAnnotationReader;
use Silverback\ApiComponentsBundle\Model\Uploadable\Base64EncodedFile;
use Silverback\ApiComponentsBundle\Model\Uploadable\ImageDimensions;
use Silverback\ApiComponentsBundle\Model\Uploadable\MediaObject;
use Silverback\ApiComponentsBundle\Model\Uploadable\UploadedBase64EncodedFile;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareDenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class UploadableNormalizer implements CacheableSupportsMethodInterface, ContextAwareDenormalizerInterface, DenormalizerAwareInterface, ContextAwareNormalizerInterface, NormalizerAwareInterface
{
    use ClassMetadataTrait;

    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'UPLOADABLE_NORMALIZER_ALREADY_CALLED';

    private UploadableAnnotationReader $annotationReader;

    public function __construct(UploadableAnnotationReader $annotationReader, ManagerRegistry $registry)
    {
        $this->annotationReader = $annotationReader;
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
                $data[$fieldName] = null;
                continue;
            }

            try {
                $file = new Base64EncodedFile($value);
                $data[$fieldName] = new UploadedBase64EncodedFile($file, Uuid::uuid4() . '.' . $file->getExtension());
            } catch (FileException $exception) {
                throw new NotNormalizableValueException($exception->getMessage());
            }
        }

        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }

    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        return !isset($context[self::ALREADY_CALLED]) &&
            \is_object($data) &&
            !$data instanceof \Traversable &&
            $this->annotationReader->isConfigured($data);
    }

    public function normalize($object, $format = null, array $context = [])
    {
        $context[self::ALREADY_CALLED] = true;

        $dimensions = new ImageDimensions();
        $dimensions->height = 100;
        $dimensions->width = 200;
        $mediaObject = new MediaObject();
        $mediaObject->contentUrl = 'https://www.website.com/path';
        $mediaObject->dimensions = $dimensions;
        $mediaObject->fileSize = 632;
        $mediaObject->imagineFilter = 'filter_name';
        $mediaObject->mimeType = 'octet/stream';
        $mediaObjects = [
            $mediaObject,
        ];

        $context[MetadataNormalizer::METADATA_CONTEXT]['media_objects'] = $this->normalizer->normalize(new ArrayCollection($mediaObjects), $format, ['jsonld_embed_context' => true]);

        $fieldConfigurations = $this->annotationReader->getConfiguredProperties($object, true, true);
        $classMetadata = $this->getClassMetadata($object);
        foreach ($fieldConfigurations as $fieldConfiguration) {
            $classMetadata->setFieldValue($object, $fieldConfiguration->property, null);
        }

        return $this->normalizer->normalize($object, $format, $context);
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }
}
