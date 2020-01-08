<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Core;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentBundle\Entity\Utility\TimestampedInterface;
use Silverback\ApiComponentBundle\Entity\Utility\TimestampedTrait;
use Silverback\ApiComponentBundle\Entity\Utility\UiTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 * @ORM\MappedSuperclass()
 */
abstract class AbstractPage implements TimestampedInterface
{
    use IdTrait;
    use TimestampedTrait;
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

    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Core\Route", mappedBy="pageTemplate", cascade={"persist"})
     * @var Collection|Route[]
     */
    public Collection $routes;

    public function __construct()
    {
        $this->setId();
        $this->componentGroups = new ArrayCollection();
        $this->routes = new ArrayCollection();
    }
}
