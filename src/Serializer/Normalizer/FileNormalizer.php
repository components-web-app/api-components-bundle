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

use Silverback\ApiComponentBundle\Helper\FileHelper;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareDenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * Prevent serialization and deserialzation of the internal file path.
 *
 * @author Daniel West <daniel@silverback.is>
 */
class FileNormalizer implements ContextAwareNormalizerInterface, CacheableSupportsMethodInterface, NormalizerAwareInterface, ContextAwareDenormalizerInterface, DenormalizerAwareInterface
{
    use NormalizerAwareTrait;
    use DenormalizerAwareTrait;

    private const ALREADY_CALLED = 'FILE_NORMALIZER_ALREADY_CALLED';

    private FileHelper $fileHelper;

    public function __construct(FileHelper $fileHelper)
    {
        $this->fileHelper = $fileHelper;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }

    public function supportsDenormalization($data, string $type, string $format = null, array $context = [])
    {
        return !isset($context[self::ALREADY_CALLED]) && $this->fileHelper->isConfigured($type);
    }

    public function supportsNormalization($data, string $format = null, array $context = [])
    {
        return !isset($context[self::ALREADY_CALLED]) &&
            \is_object($data) &&
            !$data instanceof \Traversable &&
            $this->fileHelper->isConfigured($data);
    }

    public function denormalize($data, string $type, $format = null, array $context = [])
    {
        $context[self::ALREADY_CALLED] = true;
        $configuration = $this->fileHelper->getConfiguration($type);

        unset($data[$configuration->filePathFieldName]);

        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }

    public function normalize($object, $format = null, array $context = [])
    {
        $context[self::ALREADY_CALLED] = true;
        $configuration = $this->fileHelper->getConfiguration($object);
        $data = $this->normalizer->normalize($object, $format, $context);

        unset($data[$configuration->filePathFieldName]);

        return $data;
    }
}
