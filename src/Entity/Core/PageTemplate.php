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
     *
     * @var Layout|null
     */
    public ?Layout $layout;

    /**
     * @ORM\ManyToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Core\ComponentGroup", mappedBy="pageTemplates")
     *
     * @var Collection|ComponentGroup[]
     */
    public Collection $componentGroups;

    /**
     * @ORM\OneToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Core\Route", inversedBy="pageTemplate", cascade={"persist"})
     *
     * @var Route
     */
    public Route $routes;

    public function __construct()
    {
        parent::__construct();
        $this->componentGroups = new ArrayCollection();
    }
}
