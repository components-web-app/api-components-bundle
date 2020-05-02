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

namespace Silverback\ApiComponentsBundle\Entity\Utility;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;

/**
 * Reusable trait by application developer so keep annotations as we cannot map with XML.
 *
 * @author Daniel West <daniel@silverback.is>
 */
trait ComponentGroupsTrait
{
    /**
     * @ORM\ManyToMany(targetEntity="Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup")
     *
     * @var Collection|ComponentGroup[]
     */
    public Collection $componentGroups;

    private function initComponentGroups(): void
    {
        $this->componentGroups = new ArrayCollection();
    }
}
