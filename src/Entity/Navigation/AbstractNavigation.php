<?php

namespace Silverback\ApiComponentBundle\Entity\Navigation;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class AbstractNavigation
 * @package Silverback\ApiComponentBundle\Entity\Navigation
 * @author Daniel West <daniel@silverback.is>
 */
abstract class AbstractNavigation implements NavigationInterface
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @Groups({"layout", "component"})
     * @var Collection|NavigationItemInterface[]
     */
    protected $items;

    public function __construct()
    {
        $this->id = Uuid::uuid4()->getHex();
        $this->items = new ArrayCollection;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getItems()
    {
        return $this->items;
    }

    public function addItem(NavigationItemInterface $navigationItem): AbstractNavigation
    {
        $this->items->add($navigationItem);
        return $this;
    }

    public function removeItem(NavigationItemInterface $navigationItem): AbstractNavigation
    {
        $this->items->removeElement($navigationItem);
        return $this;
    }
}
