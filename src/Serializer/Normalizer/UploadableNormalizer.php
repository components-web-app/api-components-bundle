<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Serializer\Normalizer;

use ApiPlatform\Core\EventListener\DeserializeListener;
use ApiPlatform\Core\Metadata\Resource\ToggleableOperationAttributeTrait;
use ApiPlatform\Core\Util\RequestAttributesExtractor;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentBundle\AnnotationReader\UploadableAnnotationReader;
use Silverback\ApiComponentBundle\Model\Uploadable\Base64EncodedFile;
use Silverback\ApiComponentBundle\Model\Uploadable\UploadedBase64EncodedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareDenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class UploadableNormalizer implements CacheableSupportsMethodInterface, ContextAwareDenormalizerInterface, DenormalizerAwareInterface
{
    use ToggleableOperationAttributeTrait;
    use DenormalizerAwareTrait;

    private const ALREADY_CALLED = 'UPLOADABLE_NORMALIZER_ALREADY_CALLED';

    private UploadableAnnotationReader $uploadableHelper;
    private RequestStack $requestStack;

    public function __construct(UploadableAnnotationReader $uploadableHelper, RequestStack $requestStack)
    {
        $this->uploadableHelper = $uploadableHelper;
        $this->requestStack = $requestStack;
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
            if (!$this->uploadableHelper->isFieldConfigured($reflectionProperty)) {
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

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        $request = $this->requestStack->getMasterRequest();
        if (!$request) {
            $isDisabled = false;
        } else {
            $attributes = RequestAttributesExtractor::extractAttributes($request);
            $isDisabled = $this->isOperationAttributeDisabled($attributes, DeserializeListener::OPERATION_ATTRIBUTE_KEY);
        }

        return !$isDisabled && !isset($context[self::ALREADY_CALLED]) && $this->uploadableHelper->isConfigured($type);
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }
}
