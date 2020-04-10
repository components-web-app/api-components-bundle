<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Component\Navigation;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Route\Route;
use Symfony\Component\Serializer\Annotation\Groups;

abstract class AbstractNavigationItem extends AbstractComponent implements NavigationItemInterface
{
    /**
     * @ORM\Column()
     * @Groups({"layout", "content", "component"})
     * @var string
     */
    protected $label;

    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Route\Route", cascade={"persist"})
     * @ORM\JoinColumn(referencedColumnName="id", onDelete="SET NULL")
     * @Groups({"layout", "content", "component"})
     * @var null|Route
     */
    protected $route;

    /**
     * @ORM\Column(nullable=true)
     * @Groups({"layout", "content", "component"})
     * @var null|string
     */
    protected $fragment;

    /**
     * @ORM\Column(type="array", nullable=true)
     * @Groups({"layout", "content", "component"})
     * @var null|array
     */
    protected $roles;

    /**
     * @ORM\Column(type="array", nullable=true)
     * @Groups({"layout", "content", "component"})
     * @var null|array
     */
    protected $excludeRoles;

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
     * @return null|array
     */
    public function getRoles(): ?array
    {
        return $this->roles;
    }

    /**
     * @param null|array $roles
     * @return AbstractNavigationItem
     */
    public function setRoles(?array $roles): AbstractNavigationItem
    {
        $this->roles = $roles;
        return $this;
    }

    /**
     * @return null|array
     */
    public function getExcludeRoles(): ?array
    {
        return $this->excludeRoles;
    }

    /**
     * @param null|array $excludeRoles
     * @return AbstractNavigationItem
     */
    public function setExcludeRoles(?array $excludeRoles): AbstractNavigationItem
    {
        $this->excludeRoles = $excludeRoles;
        return $this;
    }
}
