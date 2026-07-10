<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Annotation;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class UploadableField
{
    // Nice to have - feature to configure the IRI in the output media objects for this field
    // public string $iri = 'http://schema.org/MediaObject';

    public function __construct(
        public string $adapter,
        public string $urlGenerator = 'api',
        public string $property = 'filename',
        public ?string $prefix = null,
        public ?array $imagineFilters = [],
        // When true, a validation constraint is added in the `{ShortName}:published` group requiring
        // either the transient file property or its stored filename to be present before the owning
        // resource can be published. Configured per field, so multiple fields are independent.
        public bool $requiredOnPublish = false,
        // Optional override for the violation message (supports the `{{ property }}` placeholder).
        // Null falls back to the constraint's default message.
        public ?string $requiredOnPublishMessage = null,
    ) {
    }
}
