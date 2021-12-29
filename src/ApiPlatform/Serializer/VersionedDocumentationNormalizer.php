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

namespace Silverback\ApiComponentsBundle\ApiPlatform\Serializer;

use ApiPlatform\Core\Hydra\Serializer\DocumentationNormalizer;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class VersionedDocumentationNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface
{
    private DocumentationNormalizer $decorated;

    public function __construct(DocumentationNormalizer $decorated)
    {
        $this->decorated = $decorated;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return $this->decorated->hasCacheableSupportsMethod();
    }

    public function normalize($object, string $format = null, array $context = [])
    {
        $doc = $this->decorated->normalize($object, $format, $context);
        if ('' !== $object->getVersion()) {
            $doc['info'] = ['version' => $object->getVersion()];
        }

        return $doc;
    }

    public function supportsNormalization($data, string $format = null): bool
    {
        return $this->decorated->supportsNormalization($data, $format);
    }
}
