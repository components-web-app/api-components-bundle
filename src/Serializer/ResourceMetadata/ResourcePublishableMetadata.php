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

namespace Silverback\ApiComponentsBundle\Serializer\ResourceMetadata;

use Symfony\Component\Serializer\Annotation\Groups;

class ResourcePublishableMetadata
{
    public function __construct(
        #[Groups('cwa_resource:metadata')]
        public bool $published,
        #[Groups('cwa_resource:metadata')]
        public ?string $publishedAt = null,
    ) {
    }
}
