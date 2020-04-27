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

namespace Silverback\ApiComponentBundle\Entity\Core;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Annotation as Silverback;
use Silverback\ApiComponentBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentBundle\Entity\Utility\TimestampedTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @Silverback\Timestamped
 * @ApiResource
 * @ORM\Entity
 */
class ComponentGroup
{
    use IdTrait;
    use TimestampedTrait;

    /** @ORM\Column(unique=true) */
    public string $reference;

    /**
     * @ORM\ManyToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Core\Layout", mappedBy="componentGroups")
     *
     * @var Collection|Layout[]
     */
    public $layouts;

    /**
     * @ORM\ManyToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Core\PageTemplate", mappedBy="componentGroups")
     *
     * @var Collection|PageTemplate[]
     */
    public Collection $pageTemplates;

    /**
     * @ApiProperty(writable=false)
     * @ORM\ManyToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Core\AbstractComponent", mappedBy="componentGroups")
     *
     * @var Collection|AbstractComponent[]
     */
    public Collection $components;

    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Core\ComponentLocation", mappedBy="componentGroup")
     * @ORM\OrderBy({"sort" = "ASC"})
     *
     * @var Collection|ComponentLocation[]
     */
    public Collection $componentLocations;

    public function __construct()
    {
        $this->setId();
        $this->componentLocations = new ArrayCollection();
    }
}
