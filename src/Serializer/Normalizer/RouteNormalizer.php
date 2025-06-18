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
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RouteNormalizer implements NormalizerInterface, NormalizerAwareInterface
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
            // if a route has just been deleted which had other routes redirecting to it, then they will delete - but appear here as a redirect still without an ID for some reason
            if (!$nextRedirect->getId()) {
                break;
            }
            if (\in_array($nextRedirect->getId(), $redirectedRoutes, true)) {
                throw new CircularReferenceException(\sprintf('The redirect routes result in a circular reference: %s', implode(' -> ', $redirectedRoutes)));
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
        if ('_api_/routes_manifest/{id}{._format}_get' === $operationName) {
            $normalized['@id'] = str_replace('routes_manifest', 'routes', $normalized['@id']);

            return [
                'resource_iris' => $this->getResourceIrisFromArray($normalized),
            ];
        }

        return $normalized;
    }

    private function getResourceIrisFromArray(array $resource, array $iris = []): array
    {
        $resourceId = $resource['@id'] ?? null;
        if (
            str_contains($resourceId, '/.well-known/')
            || '/_/resource_metadatas' === $resourceId
            || \in_array($resourceId, $iris, true)
        ) {
            return $iris;
        }
        if ($resourceId) {
            $iris[] = $resourceId;
        }
        foreach ($resource as $key => $resourceValue) {
            // may be a string or simple
            // may be an array representing a resource
            // may be an array of any other values
            // may be an array of arrays
            if (\is_array($resourceValue)) {
                // check if the array is representing a new resource
                if (isset($resourceValue['@id'])) {
                    $iris = $this->getResourceIrisFromArray($resourceValue, $iris);
                }
                // check if the array contains more resources
                foreach ($resourceValue as $nestedValue) {
                    if (isset($nestedValue['@id'])) {
                        $iris = $this->getResourceIrisFromArray($nestedValue, $iris);
                    }
                }
            }
        }

        return array_filter($iris, function ($iri) {
            return !str_contains($iri, '/.well-known/');
        });
    }

    public function supportsNormalization($data, $format = null, $context = []): bool
    {
        return !isset($context[self::ALREADY_CALLED]) && $data instanceof Route;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [Route::class => false];
    }
}
