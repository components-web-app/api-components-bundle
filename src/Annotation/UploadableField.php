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
    public function __construct(
        public string $adapter,
        public string $urlGenerator = 'api',
        public string $property = 'filename',
        public ?string $prefix = null,
        public ?array $imagineFilters = [],
        public bool $requiredOnPublish = false,
        public ?string $requiredOnPublishMessage = null,
        public string|false|null $cacheControl = null,
        public string|false|null $imagineCacheControl = null,
    ) {
    }
}
