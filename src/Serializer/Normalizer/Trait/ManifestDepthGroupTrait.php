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
 * Builds the manifest `resource_iris`: an array indexed by rendering depth (root first), where each
 * element is a nested resource tree `{ "iri": string, "children": [...] }` mirroring resource
 * containment (route → pageData → page → componentGroup → position → component → nested groups …).
 *
 * parentPage/parentPageData fields mark depth boundaries — everything reachable without crossing
 * those fields belongs to the same depth tree; the parent subtree becomes the previous depth.
 *
 * @author Daniel West <daniel@silverback.is>
 */
trait ManifestDepthGroupTrait
{
    /**
     * @return list<array{iri: string, children: array}> one nested tree per depth, root first
     */
    private function buildDepthGroups(array $resource): array
    {
        $seen = [];
        $parentResources = [];
        $nodes = $this->buildDepthNodes($resource, $parentResources, $seen);
        $currentTree = $nodes[0] ?? ['iri' => $resource['@id'] ?? '', 'children' => []];

        if (empty($parentResources)) {
            return [$currentTree];
        }

        $ancestorGroups = $this->buildDepthGroups($parentResources[0]);

        return [...$ancestorGroups, $currentTree];
    }

    /**
     * Builds the tree node(s) contributed by a normalised resource, within the current depth (i.e.
     * without crossing parentPage/parentPageData — those are collected into $parentResources instead).
     *
     * Returns a single-element list for a real resource, or the hoisted children of a skipped/blank
     * or already-seen (deduplicated) resource — so blank-node metadata and back-references never
     * appear as nodes, matching the flat behaviour this replaced.
     *
     * @param array<int, array>   $parentResources collected boundary resources (by reference)
     * @param array<string, true> $seen            per-depth IRI dedup set (by reference)
     *
     * @return list<array{iri: string, children: array}>
     */
    private function buildDepthNodes(array $resource, array &$parentResources, array &$seen): array
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
                if (\is_array($value) && isset($value['@id'])) {
                    $parentResources[] = $value;
                }
                continue;
            }

            if (!\is_array($value)) {
                if (!$isBlankNode && \is_string($value) && str_starts_with($value, '/') && !$this->shouldSkipIri($value) && !isset($seen[$value])) {
                    $seen[$value] = true;
                    $childBucket[] = ['iri' => $value, 'children' => []];
                }
                continue;
            }

            if (isset($value['@id'])) {
                array_push($childBucket, ...$this->buildDepthNodes($value, $parentResources, $seen));
            } else {
                foreach ($value as $nested) {
                    if (\is_array($nested) && isset($nested['@id'])) {
                        array_push($childBucket, ...$this->buildDepthNodes($nested, $parentResources, $seen));
                    }
                }
            }
        }

        if (null !== $ownNode) {
            $ownNode['children'] = $childBucket;

            return [$ownNode];
        }

        // Skipped/blank/duplicate resource: it contributes no node of its own, so its descendant
        // nodes are hoisted to the caller (keeps the tree free of blank-node/back-reference noise).
        return $childBucket;
    }

    private function shouldSkipIri(string $iri): bool
    {
        return str_contains($iri, '/.well-known/') || str_ends_with($iri, '/_/resource_metadatas');
    }
}
