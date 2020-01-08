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
class ComponentGroup implements TimestampedInterface
{
    use IdTrait;
    use TimestampedTrait;

    /**
     * @ORM\ManyToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Core\Layout", inversedBy="componentGroups")
     * @var Collection|Layout[]
     */
    public $layouts;

    /**
     * @ORM\ManyToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Core\PageTemplate", inversedBy="componentGroups")
     * @var Collection|PageTemplate[]
     */
    public Collection $pageTemplates;

    /**
     * @ORM\ManyToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Core\PageData", inversedBy="componentGroups")
     * @var Collection|PageData[]
     */
    public Collection $pageData;

    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Core\ComponentLocation", mappedBy="componentGroup")
     * @var Collection|ComponentLocation[]
     */
    public Collection $componentLocations;

    public function __construct()
    {
        $this->setId();
        $this->componentLocations = new ArrayCollection();
    }
}
