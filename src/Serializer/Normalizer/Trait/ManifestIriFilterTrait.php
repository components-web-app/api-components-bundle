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
trait ManifestIriFilterTrait
{
    private function shouldSkipIri(string $iri): bool
    {
        return str_contains($iri, '/.well-known/') || str_ends_with($iri, '/_/resource_metadatas');
    }
}
