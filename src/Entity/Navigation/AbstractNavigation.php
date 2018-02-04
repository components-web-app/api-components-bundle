<?php

namespace Silverback\ApiComponentBundle\Entity\Navigation;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class AbstractNavigation
 * @package Silverback\ApiComponentBundle\Entity\Navigation
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource(attributes={"force_eager"=false})
 */
abstract class AbstractNavigation implements NavigationInterface
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @Groups({"layout", "content", "component"})
     * @var Collection|AbstractNavigationItem[]
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

    public function addItem(AbstractNavigationItem $navigationItem): AbstractNavigation
    {
        $this->items->add($navigationItem);
        return $this;
    }

    public function removeItem(AbstractNavigationItem $navigationItem): AbstractNavigation
    {
        $this->items->removeElement($navigationItem);
        return $this;
    }
}
