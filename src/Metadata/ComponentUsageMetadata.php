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
 * @author Daniel West <daniel@silverback.is>
 */
class ComponentUsageMetadata
{
    #[Groups(['AbstractComponent:cwa_resource:read'])]
    private int $positionCount;

    #[Groups(['AbstractComponent:cwa_resource:read'])]
    private int $pageDataCount;

    public function __construct(?int $positionCount = null, ?int $pageDataCount = null)
    {
        $this->positionCount = $positionCount;
        $this->pageDataCount = $pageDataCount;
    }

    public function getPositionCount(): int
    {
        return $this->positionCount;
    }

    public function getPageDataCount(): int
    {
        return $this->pageDataCount;
    }

    #[Groups(['AbstractComponent:cwa_resource:read'])]
    public function getTotal(): int
    {
        return $this->positionCount + $this->pageDataCount;
    }
}
