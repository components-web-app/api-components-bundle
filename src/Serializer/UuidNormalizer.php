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

namespace Silverback\ApiComponentsBundle\Serializer;

use ApiPlatform\Core\Bridge\RamseyUuid\Identifier\Normalizer\UuidNormalizer as BaseUuidNormalizer;
use ApiPlatform\Core\Exception\InvalidIdentifierException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Because we have a couple of entities which can be fetched with either the UUID or another
 * field, this normalizer allows the UUID denormalization to fail silently.
 *
 * @author Daniel West <daniel@silverback.is>
 */
class UuidNormalizer implements DenormalizerInterface
{
    private BaseUuidNormalizer $decorated;

    public function __construct(BaseUuidNormalizer $decorated)
    {
        $this->decorated = $decorated;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $class, $format = null, array $context = [])
    {
        try {
            return $this->decorated->denormalize($data, $class, $format, $context);
        } catch (InvalidIdentifierException $e) {
            return $data;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return $this->decorated->supportsDenormalization($data, $type, $format);
    }
}
