<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Core;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Utility\UiTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource
 * @ORM\Entity
 */
class PageTemplate extends AbstractPage
{
    use UiTrait;

    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Core\Layout", inversedBy="pageTemplates")
     * @ORM\JoinColumn(nullable=true, onDelete="SET NULL")
     * @var Layout|null
     */
    public ?Layout $layout;

    /**
     * @ORM\ManyToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Core\ComponentGroup", mappedBy="pageTemplates")
     * @var Collection|ComponentGroup[]
     */
    public Collection $componentGroups;

    public function __construct()
    {
        parent::__construct();
        $this->componentGroups = new ArrayCollection();
    }
}
