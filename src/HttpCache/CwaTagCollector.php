<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\HttpCache;

use ApiPlatform\Serializer\TagCollectorInterface;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\Trait\ManifestIriFilterTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class CwaTagCollector implements TagCollectorInterface
{
    use ManifestIriFilterTrait;

    public const string MANIFEST_TAG_PREFIX = 'manifest:';
    public const string MANIFEST_CONTEXT_KEY = 'cwa_manifest_tags';

    public function collect(array $context = []): void
    {
        if (!isset($context['resources'])) {
            return;
        }

        $iri = $context['iri'] ?? null;
        if (!\is_string($iri) || $this->shouldSkipIri($iri)) {
            return;
        }

        if ($context[self::MANIFEST_CONTEXT_KEY] ?? false) {
            if (!($context['object'] ?? null) instanceof AbstractPage) {
                return;
            }

            $iri = self::MANIFEST_TAG_PREFIX . $iri;
        }

        $context['resources'][$iri] = $iri;
    }
}
