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

namespace Silverback\ApiComponentBundle\Serializer;

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
class ApiNormalizer implements ContextAwareNormalizerInterface, CacheableSupportsMethodInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;
    use ClassInfoTrait;

    private const ALREADY_CALLED = 'API_NORMALIZER_ALREADY_CALLED';
    public const IS_PERSISTED_DATA_KEY = '__PERSISTED__';

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

        $data = $this->normalizer->normalize($object, $format, $context);
        if (\is_array($data)) {
            $data[self::IS_PERSISTED_DATA_KEY] = $this->entityManager->contains($object);
        }

        return $data;
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
