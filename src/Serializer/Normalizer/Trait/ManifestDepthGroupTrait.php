<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Serializer\Normalizer\Trait;

/**
 * @author Daniel West <daniel@silverback.is>
 */
trait ManifestDepthGroupTrait
{
    use ManifestIriFilterTrait;

    /**
     * @return list<array{iri: string, children: array}>
     */
    private function buildDepthGroups(array $resource): array
    {
        $depthResources = [];
        for ($current = $resource; null !== $current; $current = $this->findParentResource($current)) {
            array_unshift($depthResources, $current);
        }

        $groups = [];
        $emittedLayouts = [];
        foreach ($depthResources as $depthResource) {
            $seen = [];
            $depthLayouts = [];
            $nodes = $this->buildDepthNodes($depthResource, $seen, $emittedLayouts, $depthLayouts);
            $groups[] = $nodes[0] ?? ['iri' => $depthResource['@id'] ?? '', 'children' => []];
            $emittedLayouts += $depthLayouts;
        }

        return $groups;
    }

    private function findParentResource(array $resource): ?array
    {
        foreach ($resource as $key => $value) {
            if (!\is_array($value) || str_starts_with((string) $key, '@')) {
                continue;
            }
            if (\in_array($key, ['parentPage', 'parentPageData'], true)) {
                if (isset($value['@id'])) {
                    return $value;
                }
                continue;
            }
            foreach (isset($value['@id']) ? [$value] : $value as $nested) {
                if (\is_array($nested) && isset($nested['@id']) && null !== $parent = $this->findParentResource($nested)) {
                    return $parent;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, true> $seen
     * @param array<string, true> $excludedLayouts
     * @param array<string, true> $depthLayouts
     *
     * @return list<array{iri: string, children: array}>
     */
    private function buildDepthNodes(array $resource, array &$seen, array $excludedLayouts, array &$depthLayouts): array
    {
        $id = $resource['@id'] ?? null;
        $isBlankNode = \is_string($id) && str_contains($id, '/.well-known/genid/');

        $ownNode = null;
        if (\is_string($id) && !$this->shouldSkipIri($id) && !isset($seen[$id])) {
            $seen[$id] = true;
            $ownNode = ['iri' => $id, 'children' => []];
        }

        $childBucket = [];
        foreach ($resource as $key => $value) {
            if (str_starts_with((string) $key, '@')) {
                continue;
            }

            if (\in_array($key, ['parentPage', 'parentPageData'], true)) {
                continue;
            }

            if ('layout' === $key) {
                $layoutIri = \is_array($value) ? ($value['@id'] ?? null) : $value;
                if (\is_string($layoutIri)) {
                    if (isset($excludedLayouts[$layoutIri])) {
                        continue;
                    }
                    $depthLayouts[$layoutIri] = true;
                }
            }

            if (!\is_array($value)) {
                if (!$isBlankNode && \is_string($value) && str_starts_with($value, '/') && !$this->shouldSkipIri($value) && !isset($seen[$value])) {
                    $seen[$value] = true;
                    $childBucket[] = ['iri' => $value, 'children' => []];
                }
                continue;
            }

            if (isset($value['@id'])) {
                array_push($childBucket, ...$this->buildDepthNodes($value, $seen, $excludedLayouts, $depthLayouts));
            } else {
                foreach ($value as $nested) {
                    if (\is_array($nested) && isset($nested['@id'])) {
                        array_push($childBucket, ...$this->buildDepthNodes($nested, $seen, $excludedLayouts, $depthLayouts));
                    }
                }
            }
        }

        if (null !== $ownNode) {
            $ownNode['children'] = $childBucket;

            return [$ownNode];
        }

        return $childBucket;
    }
}
