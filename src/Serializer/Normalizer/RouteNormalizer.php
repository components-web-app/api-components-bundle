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

use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RouteNormalizer implements ContextAwareNormalizerInterface, CacheableSupportsMethodInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'ROUTE_NORMALIZER_ALREADY_CALLED';

    /**
     * @param Route      $object
     * @param mixed|null $format
     */
    public function normalize($object, $format = null, array $context = [])
    {
        $context[self::ALREADY_CALLED] = true;

        $finalRoute = $object;
        $max = 50;
        $count = 0;
        while (($nextRedirect = $finalRoute->getRedirect()) && $count <= $max) {
            $finalRoute = $nextRedirect;
            ++$count;
        }

        $isRedirect = $finalRoute !== $object;
        if ($isRedirect) {
            $object->setPage($finalRoute->getPage());
            $object->setPageData($finalRoute->getPageData());
        }

        $normalized = $this->normalizer->normalize($object, $format, $context);

        unset($normalized['pageData'], $normalized['redirectedFrom']);
        if ($isRedirect) {
            $normalized['redirectPath'] = $finalRoute->getPath();
        }

        return $normalized;
    }

    public function supportsNormalization($data, $format = null, $context = []): bool
    {
        return !isset($context[self::ALREADY_CALLED]) && $data instanceof Route;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }
}
