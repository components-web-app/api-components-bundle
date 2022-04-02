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

namespace Silverback\ApiComponentsBundle\Annotation;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class UploadableField
{
    public string $property;

    public ?string $prefix;

    public ?array $imagineFilters;

    // Nice to have - feature to configure the IRI in the output media objects for this field
    // public string $iri = 'http://schema.org/MediaObject';

    public ?string $adapter;

    public function __construct(string $adapter, string $property = 'filename', ?string $prefix = null, ?array $imagineFilters = [])
    {
        $this->property = $property;
        $this->prefix = $prefix;
        $this->imagineFilters = $imagineFilters;
        $this->adapter = $adapter;
    }
}
