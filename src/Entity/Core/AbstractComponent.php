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

namespace Silverback\ApiComponentsBundle\Entity\Core;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Entity\Utility\ComponentGroupsTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\UiTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="dtype", type="string")
 * @ORM\AssociationOverrides({
 *     @ORM\AssociationOverride(name="componentGroups", inversedBy="components")
 * })
 */
abstract class AbstractComponent implements ComponentInterface
{
    use IdTrait;
    use UiTrait;
    use ComponentGroupsTrait;

    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentsBundle\Entity\Core\ComponentLocation", mappedBy="component")
     *
     * @var Collection|ComponentLocation[]
     */
    public Collection $componentLocations;

    public function __construct()
    {
        $this->setId();
        $this->initComponentGroups();
        $this->componentLocations = new ArrayCollection();
    }

    public function __clone()
    {
        $this->setId();
    }
}
