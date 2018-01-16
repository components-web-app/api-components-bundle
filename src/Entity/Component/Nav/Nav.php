<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Nav;

use Silverback\ApiComponentBundle\Entity\Component\Component;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class BaseNav
 * @package App\Entity\Component\Nav
 * @ORM\MappedSuperclass()
 */
abstract class Nav extends Component implements NavInterface
{
    /**
     * @var Collection
     */
    protected $items;

    /**
     * @ORM\OneToMany(targetEntity="\Silverback\ApiComponentBundle\Entity\Component\ComponentGroup", mappedBy="parent")
     * @var Collection
     */
    protected $childGroups;

    public function __construct()
    {
        parent::__construct();
        $this->childGroups = new ArrayCollection();
        $this->items = new ArrayCollection();
    }

    /**
     * @return Collection
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    /**
     * @param array $items
     */
    public function setItems(array $items): void
    {
        $this->items = new ArrayCollection();
        foreach($items as $item)
        {
            $this->addItem($item);
        }
    }

    /**
     * @param NavItemInterface $item
     */
    public function addItem(NavItemInterface $item): void
    {
        $this->items->add($item);
        $item->setNav($this);
    }

    /**
     * @param NavItemInterface $item
     */
    public function removeItem(NavItemInterface $item): void
    {
        $this->items->removeElement($item);
    }

    /**
     * @return Collection
     */
    public function getChildGroups(): Collection
    {
        return $this->childGroups;
    }
}
