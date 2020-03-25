<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Utility;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Core\ComponentGroup;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @internal
 */
trait UiTrait
{
    /** @ORM\Column(nullable=true) */
    public ?string $uiComponent;

    /** @ORM\Column(type="json", nullable=true) */
    public ?array $uiClassNames;

    /**
     * @ORM\ManyToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Core\ComponentGroup")
     *
     * @var Collection|ComponentGroup[]
     */
    public Collection $componentGroups;

    private function initComponentGroups(): void
    {
        $this->componentGroups = new ArrayCollection();
    }
}
