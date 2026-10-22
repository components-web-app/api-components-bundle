<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Entity\Utility;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;

trait VersionedTrait
{
    #[ORM\Version]
    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    #[Ignore]
    private int $resourceVersion = 1;

    #[Ignore]
    public function getResourceVersion(): int
    {
        return $this->resourceVersion;
    }
}
