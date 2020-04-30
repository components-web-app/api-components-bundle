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

use Hshn\Base64EncodedFile\HttpFoundation\File\Base64EncodedFile;
use Silverback\ApiComponentBundle\Helper\UploadableHelper;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareDenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class UploadableNormalizer implements CacheableSupportsMethodInterface, ContextAwareDenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    private const ALREADY_CALLED = 'UPLOADABLE_NORMALIZER_ALREADY_CALLED';

    private UploadableHelper $uploadableHelper;

    public function __construct(UploadableHelper $uploadableHelper)
    {
        $this->uploadableHelper = $uploadableHelper;
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

            // Convert base64 string to UploadableFile.
            try {
                $data[$fieldName] = new Base64EncodedFile($value);
            } catch (FileException $exception) {
                // Invalid base64.
                // todo Should throw a violation error?
            }
        }

        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        return !isset($context[self::ALREADY_CALLED]) && $this->uploadableHelper->isConfigured($type);
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }
}
