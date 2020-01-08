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
use Silverback\ApiComponentBundle\Entity\Utility\UiTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity
 * @ORM\Table(name="component")
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="dtype", type="string")
 * This is populated via an extension to include any entity that extends this one.
 * @ORM\DiscriminatorMap({})
 */
abstract class AbstractComponent implements ComponentInterface, TimestampedInterface
{
    use IdTrait;
    use TimestampedTrait;
    use UiTrait;

    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Core\ComponentLocation", mappedBy="component")
     * @var Collection|ComponentLocation[]
     */
    public Collection $componentLocations;

    public function __construct()
    {
        $this->setId();
        $this->componentLocations = new ArrayCollection();
    }
}
