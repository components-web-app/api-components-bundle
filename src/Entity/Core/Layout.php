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
 * @ApiResource
 * @ORM\Entity
 * @ORM\AssociationOverrides({
 *     @ORM\AssociationOverride(name="componentGroups", inversedBy="layouts")
 * })
 */
class Layout implements TimestampedInterface
{
    use IdTrait;
    use TimestampedTrait;
    use UiTrait;

    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Core\PageTemplate", mappedBy="layout")
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
