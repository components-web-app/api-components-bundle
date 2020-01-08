<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Core;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentBundle\Entity\Utility\TimestampedInterface;
use Silverback\ApiComponentBundle\Entity\Utility\TimestampedTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource
 * @ORM\Entity
 */
class Layout implements TimestampedInterface
{
    use IdTrait;
    use TimestampedTrait;

    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Core\PageTemplate", mappedBy="layout")
     * @var Collection|PageTemplate[]
     */
    public Collection $pageTemplates;

    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Core\PageData", mappedBy="layout")
     * @var Collection|PageData[]
     */
    public Collection $pageData;

    /**
     * @ORM\ManyToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Core\ComponentGroup", mappedBy="layouts")
     * @var Collection|ComponentGroup[]
     */
    public Collection $componentGroups;

    public function __construct()
    {
        $this->setId();
        $this->pageTemplates = new ArrayCollection();
        $this->pageData = new ArrayCollection();
        $this->componentGroups = new ArrayCollection();
    }
}
