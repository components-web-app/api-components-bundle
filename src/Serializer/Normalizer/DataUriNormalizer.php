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

use Silverback\ApiComponentsBundle\Model\Uploadable\UploadedDataUriFile;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\DataUriNormalizer as SymfonyDataUriNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * The DataUriNormalizer would be called even if we have already denormalized. The `supports`
 * method seems to have 'null' data. So we check during denormalization if we have already done it.
 * Bit hacky, but it'll be OK for now. Should trace source of issue, probably a bug in dependency,
 * Check removing this every now and again perhaps too. Tests will fail.
 *
 * @author Daniel West <daniel@silverback.is>
 */
class DataUriNormalizer implements NormalizerAwareInterface, DenormalizerAwareInterface, CacheableSupportsMethodInterface, NormalizerInterface, DenormalizerInterface
{
    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;

    private SymfonyDataUriNormalizer $decorated;

    public function __construct(SymfonyDataUriNormalizer $decorated)
    {
        $this->decorated = $decorated;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return $this->decorated->hasCacheableSupportsMethod();
    }

    public function denormalize($data, $type, $format = null, array $context = []): mixed
    {
        if ($data instanceof UploadedDataUriFile || ($data instanceof File && '' === $data->getPath())) {
            return $data;
        }

        return $this->decorated->denormalize($data, $type, $format, $context);
    }

    public function supportsDenormalization($data, $type, $format = null): bool
    {
        return $this->decorated->supportsDenormalization($data, $type, $format);
    }

    public function normalize($object, $format = null, array $context = []): float|array|\ArrayObject|bool|int|string|null
    {
        return $this->decorated->normalize($object, $format, $context);
    }

    public function supportsNormalization($data, $format = null): bool
    {
        return $this->decorated->supportsNormalization($data, $format);
    }
}
