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
     *
     * @return array{0: string[], 1: array[]}
     */
    private function collectCurrentDepth(array $resource, array $iris, array $parentResources): array
    {
        $id = $resource['@id'] ?? null;
        if ($id && !$this->shouldSkipIri($id) && !\in_array($id, $iris, true)) {
            $iris[] = $id;
        }

        $isBlankNode = isset($resource['@id']) && str_contains($resource['@id'], '/.well-known/genid/');

        foreach ($resource as $key => $value) {
            if (!\is_array($value)) {
                if (!$isBlankNode && \is_string($value) && !str_starts_with($key, '@') && str_starts_with($value, '/') && !$this->shouldSkipIri($value) && !\in_array($value, $iris, true)) {
                    $iris[] = $value;
                }
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
}
