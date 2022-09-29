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
use Symfony\Component\Serializer\Exception\CircularReferenceException;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RouteNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'ROUTE_NORMALIZER_ALREADY_CALLED';

    /**
     * @param Route      $object
     * @param mixed|null $format
     */
    public function normalize($object, $format = null, array $context = []): float|array|\ArrayObject|bool|int|string|null
    {
        $context[self::ALREADY_CALLED] = true;

        $finalRoute = $object;

        $redirectedRoutes = [$finalRoute->getId()];
        while ($nextRedirect = $finalRoute->getRedirect()) {
            if (\in_array($nextRedirect->getId(), $redirectedRoutes, true)) {
                throw new CircularReferenceException(sprintf('The redirect routes result in a circular reference: %s', implode(' -> ', $redirectedRoutes)));
            }
            $redirectedRoutes[] = $nextRedirect->getId();
            $finalRoute = $nextRedirect;
        }

        $isRedirect = $finalRoute !== $object;
        if ($isRedirect) {
            $object->setPage($finalRoute->getPage());
            $object->setPageData($finalRoute->getPageData());
        }

        $normalized = $this->normalizer->normalize($object, $format, $context);

        if ($isRedirect) {
            $normalized['redirectPath'] = $finalRoute->getPath();
        }

        $operationName = $context['operation_name'] ?? null;
        if ('_api_/routes_manifest/{id}.{_format}_get' === $operationName) {
            return [
                'resource_iris' => $this->getResourceIrisFromArray($normalized),
            ];
        }

        return $normalized;
    }

    private function getResourceIrisFromArray(array $resource): array
    {
        $iris = [];
        if (isset($resource['@id'])) {
            $iris[] = $resource['@id'];
        }
        foreach ($resource as $resourceValue) {
            // may be a string or simple
            // may be an array representing a resource
            // may be an array of any other values
            // may be an array of arrays
            if (\is_array($resourceValue)) {
                // check if the array is representing a new resource
                if (isset($resourceValue['@id'])) {
                    array_push($iris, ...$this->getResourceIrisFromArray($resourceValue));
                }
                // check if the array contains more resources
                foreach ($resourceValue as $nestedValue) {
                    if (isset($nestedValue['@id'])) {
                        array_push($iris, ...$this->getResourceIrisFromArray($nestedValue));
                    }
                }
            }
        }

        return $iris;
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
