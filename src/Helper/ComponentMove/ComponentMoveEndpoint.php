<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\ComponentMove;

use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;

final readonly class ComponentMoveEndpoint
{
    private function __construct(
        public string $path,
        public ?ComponentPosition $componentPosition = null,
        public ?ComponentGroup $componentGroup = null,
        public ?string $after = null,
        public ?string $before = null,
        public ?AbstractPageData $pageData = null,
        public ?string $property = null,
    ) {
    }

    public static function position(string $path, ComponentPosition $componentPosition): self
    {
        return new self($path, componentPosition: $componentPosition);
    }

    public static function group(string $path, ComponentGroup $componentGroup, ?string $after, ?string $before): self
    {
        return new self($path, componentGroup: $componentGroup, after: $after, before: $before);
    }

    public static function slot(string $path, AbstractPageData $pageData, string $property): self
    {
        return new self($path, pageData: $pageData, property: $property);
    }

    public function propertyPath(string $key): string
    {
        return $this->path . '.' . $key;
    }
}
