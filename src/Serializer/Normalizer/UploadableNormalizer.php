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

use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\AnnotationReader\UploadableAnnotationReader;
use Silverback\ApiComponentsBundle\Model\Uploadable\Base64EncodedFile;
use Silverback\ApiComponentsBundle\Model\Uploadable\UploadedBase64EncodedFile;
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
    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'UPLOADABLE_NORMALIZER_ALREADY_CALLED';

    private UploadableAnnotationReader $annotationReader;

    public function __construct(UploadableAnnotationReader $annotationReader)
    {
        $this->annotationReader = $annotationReader;
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

        $mediaObjects = [];
        $context[MetadataNormalizer::METADATA_CONTEXT]['media_objects'] = $mediaObjects;

        return $this->normalizer->normalize($object, $format, $context);
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }
}
