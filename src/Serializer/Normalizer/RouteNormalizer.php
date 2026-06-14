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
        if ('_api_/resource_manifest/{id}{._format}_get' === $operationName) {
            $normalized['@id'] = str_replace('resource_manifest', 'routes', $normalized['@id']);

            return [
                'resource_iris' => $this->buildDepthGroups($normalized),
            ];
        }

        return $normalized;
    }

    /**
     * Returns IRIs grouped by rendering depth, root first.
     * parentPage/parentPageData fields mark depth boundaries — everything reachable
     * without crossing those fields belongs to the same depth group.
     */
    private function buildDepthGroups(array $resource): array
    {
        [$currentIris, $parentResources] = $this->collectCurrentDepth($resource, [], []);

        if (empty($parentResources)) {
            return [array_values(array_unique($currentIris))];
        }

        $ancestorGroups = $this->buildDepthGroups($parentResources[0]);

        return [...$ancestorGroups, array_values(array_unique($currentIris))];
    }

    /**
     * Collects IRIs at the current depth without crossing parentPage/parentPageData boundaries.
     * Returns [$iris, $parentResources] where $parentResources are the objects found behind
     * those boundary fields (at most one, but returned as array for uniformity).
     *
     * @return array{0: string[], 1: array[]}
     */
    private function collectCurrentDepth(array $resource, array $iris, array $parentResources): array
    {
        $id = $resource['@id'] ?? null;
        if ($id && !$this->shouldSkipIri($id) && !\in_array($id, $iris, true)) {
            $iris[] = $id;
        }

        foreach ($resource as $key => $value) {
            if (!\is_array($value)) {
                continue;
            }

            if (\in_array($key, ['parentPage', 'parentPageData'], true)) {
                if (isset($value['@id'])) {
                    $parentResources[] = $value;
                }
                continue;
            }

            if (isset($value['@id'])) {
                [$iris, $parentResources] = $this->collectCurrentDepth($value, $iris, $parentResources);
            } else {
                foreach ($value as $nested) {
                    if (isset($nested['@id'])) {
                        [$iris, $parentResources] = $this->collectCurrentDepth($nested, $iris, $parentResources);
                    }
                }
            }
        }

        return [$iris, $parentResources];
    }

    private function shouldSkipIri(string $iri): bool
    {
        return str_contains($iri, '/.well-known/') || str_ends_with($iri, '/_/resource_metadatas');
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
