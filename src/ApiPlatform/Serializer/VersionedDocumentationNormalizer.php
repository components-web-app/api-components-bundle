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

use ApiPlatform\Documentation\Documentation;
use ApiPlatform\Hydra\Serializer\DocumentationNormalizer;
use Silverback\ApiComponentsBundle\OpenApi\OpenApiFactory;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class VersionedDocumentationNormalizer implements NormalizerInterface
{
    private NormalizerInterface|DocumentationNormalizer $decorated;

    public function __construct(NormalizerInterface|DocumentationNormalizer $decorated)
    {
        $this->decorated = $decorated;
    }

    /**
     * @param Documentation $object
     *
     * @throws ExceptionInterface
     */
    public function normalize($object, ?string $format = null, array $context = []): array
    {
        $doc = $this->decorated->normalize($object, $format, $context);
        if ('' !== $object->getVersion()) {
            $doc['info'] = ['version' => OpenApiFactory::getExtendedVersion($object->getVersion())];
        }

        return $doc;
    }

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        return $this->decorated->supportsNormalization($data, $format, $context);
    }

    public function getSupportedTypes(?string $format): array
    {
        return $this->decorated->getSupportedTypes($format);
    }
}
