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

use Silverback\ApiComponentsBundle\Model\Uploadable\UploadedDataUriFile;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class DataUriNormalizer implements NormalizerAwareInterface, DenormalizerAwareInterface, NormalizerInterface, DenormalizerInterface
{
    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;

    private NormalizerInterface&DenormalizerInterface $decorated;

    public function __construct(NormalizerInterface&DenormalizerInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function denormalize($data, $type, $format = null, array $context = []): mixed
    {
        if ($data instanceof UploadedDataUriFile || ($data instanceof File && '' === $data->getPath())) {
            return $data;
        }

        return $this->decorated->denormalize($data, $type, $format, $context);
    }

    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        return $this->decorated->supportsDenormalization($data, $type, $format, $context);
    }

    public function normalize($object, $format = null, array $context = []): float|array|\ArrayObject|bool|int|string|null
    {
        return $this->decorated->normalize($object, $format, $context);
    }

    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        return $this->decorated->supportsNormalization($data, $format, $context);
    }

    public function getSupportedTypes(?string $format): array
    {
        return $this->decorated->getSupportedTypes($format);
    }
}
