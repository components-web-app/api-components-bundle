<?php

namespace Silverback\ApiComponentBundle\Entity\Navigation;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentBundle\Entity\Navigation\Route\Route;
use Silverback\ApiComponentBundle\Entity\SortableTrait;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class AbstractNavigationItem
 * @package Silverback\ApiComponentBundle\Entity\Navigation
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource(attributes={"force_eager"=false})
 * @ORM\Entity()
 * @ORM\Table(name="navigation_item")
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="type", type="string")
 * @ORM\DiscriminatorMap({
 *     "nav_bar" = "Silverback\ApiComponentBundle\Entity\Layout\NavBar\NavBarItem"
 * })
 */
abstract class AbstractNavigationItem implements NavigationItemInterface
{
    use SortableTrait;

    /**
     * @ORM\Id()
     * @ORM\Column(type="string")
     * @var string
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Navigation\AbstractNavigation", inversedBy="items", cascade={"remove"})
     * @ORM\JoinColumn(onDelete="CASCADE")
     * @var AbstractNavigation
     * @Groups({"component_item_write"})
     */
    protected $navigation;

    /**
     * @ORM\Column()
     * @Groups({"layout", "component", "component_item"})
     * @var string
     */
    protected $label;

    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Navigation\Route\Route")
     * @ORM\JoinColumn(referencedColumnName="route")
     * @Groups({"layout", "component", "component_item"})
     * @var null|Route
     */
    protected $route;

    /**
     * @ORM\Column(nullable=true)
     * @Groups({"layout", "component", "component_item"})
     * @var null|string
     */
    protected $fragment;

    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Navigation\AbstractNavigation")
     * @Groups({"layout", "component", "component_item"})
     * @var null|AbstractNavigation
     */
    protected $child;

    /**
     * AbstractNavigationItem constructor.
     */
    public function __construct()
    {
        $this->id = Uuid::uuid4()->getHex();
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return AbstractNavigation
     */
    public function getNavigation(): AbstractNavigation
    {
        return $this->navigation;
    }

    /**
     * @param AbstractNavigation $navigation
     * @param bool|null $sortLast
     */
    public function setNavigation(AbstractNavigation $navigation, ?bool $sortLast = true): void
    {
        $this->navigation = $navigation;
        if (null === $this->sort || $sortLast !== null) {
            $this->setSort($this->calculateSort($sortLast));
        }
    }


    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @param string $label
     * @return AbstractNavigationItem
     */
    public function setLabel(string $label): AbstractNavigationItem
    {
        $this->label = $label;
        return $this;
    }

    /**
     * @return null|Route
     */
    public function getRoute(): ?Route
    {
        return $this->route;
    }

    /**
     * @param null|Route $route
     * @return AbstractNavigationItem
     */
    public function setRoute(?Route $route): AbstractNavigationItem
    {
        $this->route = $route;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getFragment(): ?string
    {
        return $this->fragment;
    }

    /**
     * @param null|string $fragment
     * @return AbstractNavigationItem
     */
    public function setFragment(?string $fragment): AbstractNavigationItem
    {
        $this->fragment = $fragment;
        return $this;
    }

    /**
     * @return null|AbstractNavigation
     */
    public function getChild(): ?AbstractNavigation
    {
        return $this->child;
    }

    /**
     * @param null|AbstractNavigation $child
     * @return AbstractNavigationItem
     */
    public function setChild(?AbstractNavigation $child): AbstractNavigationItem
    {
        $this->child = $child;
        return $this;
    }

    /**
     * @return \Doctrine\Common\Collections\ArrayCollection|Collection|\Silverback\ApiComponentBundle\Entity\SortableInterface[]|AbstractNavigationItem[]
     */
    public function getSortCollection(): Collection
    {
        return $this->navigation->getItems();
    }
}
