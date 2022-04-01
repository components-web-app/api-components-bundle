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

namespace Silverback\ApiComponentsBundle\Metadata;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @internal
 *
 * @author Daniel West <daniel@silverback.is>
 */
class PageDataPropertyMetadata
{
    #[Groups(['AbstractPageData:cwa_resource:read'])]
    private string $property;

    #[Groups(['AbstractPageData:cwa_resource:read'])]
    private string $componentShortName;

    public function __construct(string $property, string $componentClass)
    {
        $this->property = $property;
        $this->componentShortName = $componentClass;
    }

    public function getProperty(): string
    {
        return $this->property;
    }

    public function getComponentShortName(): string
    {
        return $this->componentShortName;
    }
}
