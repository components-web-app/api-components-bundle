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
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource
 * @ORM\Entity(repositoryClass="Silverback\ApiComponentBundle\Repository\Core\RouteRepository")
 * @Assert\Expression(
 *     "this.pageTemplate && this.pageData",
 *     message="Please specify either pageTemplate or pageData, not both."
 * )
 */
class Route implements TimestampedInterface
{
    use IdTrait;
    use TimestampedTrait;

    /** @ORM\Column(unique=true) */
    public string $route;

    /** @ORM\Column(unique=true) */
    public string $name;

    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Core\Route", inversedBy="redirectedFrom")
     * @ORM\JoinColumn(name="redirect", referencedColumnName="id", onDelete="SET NULL")
     */
    public ?Route $redirect;

    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Core\Route", mappedBy="redirect")
     */
    public Collection $redirectedFrom;

    /**
     * @ORM\OneToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Core\PageTemplate", mappedBy="routes")
     * @ORM\JoinColumn(onDelete="SET NULL", nullable=true)
     */
    public PageTemplate $pageTemplate;

    /**
     * @ORM\OneToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Core\AbstractPageData", mappedBy="routes")
     * @ORM\JoinColumn(onDelete="SET NULL", nullable=true)
     */
    public AbstractPageData $pageData;

    public function __construct()
    {
        $this->setId();
        $this->redirectedFrom = new ArrayCollection();
    }
}
