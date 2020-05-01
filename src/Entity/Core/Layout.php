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

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\Entity\Utility\ComponentGroupsTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\TimestampedTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\UiTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @Silverback\Timestamped
 * @ApiResource
 * @ORM\Entity
 * @ORM\AssociationOverrides({
 *     @ORM\AssociationOverride(name="componentGroups", inversedBy="layouts")
 * })
 */
class Layout
{
    use IdTrait;
    use TimestampedTrait;
    use UiTrait;
    use ComponentGroupsTrait;

    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentsBundle\Entity\Core\PageTemplate", mappedBy="layout")
     *
     * @var Collection|PageTemplate[]
     */
    public Collection $pageTemplates;

    /**
     * @ORM\Column(name="`default`", type="boolean", nullable=false)
     */
    public bool $default;

    public function __construct()
    {
        $this->setId();
        $this->initComponentGroups();
        $this->pageTemplates = new ArrayCollection();
    }
}
