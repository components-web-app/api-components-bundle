<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Nav;

use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class AbstractNav
 * @package Silverback\ApiComponentBundle\Entity\Component\Nav
 */
abstract class AbstractNav extends AbstractComponent implements NavInterface
{
    /**
     * @ORM\OneToMany(targetEntity="\Silverback\ApiComponentBundle\Entity\Content\ComponentGroup", mappedBy="parent")
     * @Groups({"page"})
     * @var Collection
     */
    protected $childGroups;

    public function __construct()
    {
        parent::__construct();
        $this->childGroups = new ArrayCollection();
    }

    /**
     * @return Collection
     */
    public function getChildGroups(): Collection
    {
        return $this->childGroups;
    }
}
