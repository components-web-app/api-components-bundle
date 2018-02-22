<?php

namespace Silverback\ApiComponentBundle\Entity\Navigation;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class AbstractNavigation
 * @package Silverback\ApiComponentBundle\Entity\Navigation
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity()
 * @ORM\Table(name="navigation")
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="type", type="string")
 * @ORM\DiscriminatorMap({
 *     "nav_bar" = "Silverback\ApiComponentBundle\Entity\Layout\NavBar\NavBar",
 *     "tabs" = "Silverback\ApiComponentBundle\Entity\Component\Nav\Tabs\Tabs",
 *     "menu" = "Silverback\ApiComponentBundle\Entity\Component\Nav\Menu\Menu"
 * })
 */
abstract class AbstractNavigation implements NavigationInterface
{
    /**
     * @ORM\Id()
     * @ORM\Column(type="string")
     * @var string
     */
    protected $id;

    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Navigation\AbstractNavigationItem", mappedBy="navigation")
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

    public function setItems(array $items): AbstractNavigation
    {
        $this->items = new ArrayCollection;
        foreach ($items as $item) {
            $this->addItem($item);
        }
        return $this;
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
