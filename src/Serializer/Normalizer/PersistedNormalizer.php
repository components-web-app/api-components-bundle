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

use ApiPlatform\Core\Api\ResourceClassResolverInterface;
use ApiPlatform\Core\Util\ClassInfoTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Traversable;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class PersistedNormalizer implements ContextAwareNormalizerInterface, CacheableSupportsMethodInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;
    use ClassInfoTrait;

    private const ALREADY_CALLED = 'PERSISTED_NORMALIZER_ALREADY_CALLED';

    private EntityManagerInterface $entityManager;
    private ResourceClassResolverInterface $resourceClassResolver;

    public function __construct(
        EntityManagerInterface $entityManager,
        ResourceClassResolverInterface $resourceClassResolver
    ) {
        $this->entityManager = $entityManager;
        $this->resourceClassResolver = $resourceClassResolver;
    }

    public function normalize($object, $format = null, array $context = [])
    {
        $context[self::ALREADY_CALLED] = true;
        $context[MetadataNormalizer::METADATA_CONTEXT]['persisted'] = $this->entityManager->contains($object);

        return $this->normalizer->normalize($object, $format, $context);
    }

    public function supportsNormalization($data, $format = null, $context = []): bool
    {
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        if (!\is_object($data) || $data instanceof Traversable) {
            return false;
        }

        return $this->resourceClassResolver->isResourceClass($this->getObjectClass($data));
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }
}
