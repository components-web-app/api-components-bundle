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
use Silverback\ApiComponentsBundle\Entity\Core\ComponentCollection;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @internal
 */
trait UiTrait
{
    /** @ORM\Column(nullable=true) */
    public ?string $uiComponent = null;

    /** @ORM\Column(type="json", nullable=true) */
    public ?array $uiClassNames = null;

    /**
     * @ORM\ManyToMany(targetEntity="ComponentCollection")
     *
     * @var Collection|ComponentCollection[]
     */
    public Collection $componentCollections;

    private function initComponentCollections(): void
    {
        $this->componentCollections = new ArrayCollection();
    }
}
