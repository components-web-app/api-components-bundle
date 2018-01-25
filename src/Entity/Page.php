<?php

namespace Silverback\ApiComponentBundle\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Annotation\ApiSubresource;
use Silverback\ApiComponentBundle\Entity\Component\Component;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ApiResource(
 *     attributes={
 *          "normalization_context"={"groups"={"page"}}
 *     }
 * )
 * @ORM\Entity(repositoryClass="Silverback\ApiComponentBundle\Repository\PageRepository")
 * @ORM\EntityListeners({"\Silverback\ApiComponentBundle\EntityListener\PageListener"})
 */
class Page
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @Groups({"page", "route"})
     * @var int
     */
    private $id;

    /**
     * @ORM\Column(type="string")
     * @Groups({"page"})
     * @var string
     */
    private $title;

    /**
     * @ORM\Column(type="string")
     * @Groups({"page"})
     * @var string
     */
    private $metaDescription;

    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Component\Component", mappedBy="page")
     * @ORM\OrderBy({"sort" = "ASC"})
     * @Groups({"page"})
     * @var Collection
     */
    private $components;

    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Route", mappedBy="page", cascade={"persist", "remove"})
     * @var null|Route[]
     */
    private $routes;

    /**
     * @ORM\ManyToOne(targetEntity="Page", inversedBy="children")
     * @ORM\JoinColumn(nullable=true)
     * @Groups({"route"})
     * @var null|Page
     */
    private $parent;

    /**
     * @ORM\OneToMany(targetEntity="Page", mappedBy="parent")
     * @ApiSubresource()
     * @var Collection
     */
    private $children;

    public function __construct()
    {
        $this->components = new ArrayCollection();
        $this->children = new ArrayCollection();
        $this->routes = new ArrayCollection();
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return null|string
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return null|string
     */
    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    /**
     * @param string $metaDescription
     */
    public function setMetaDescription(string $metaDescription): void
    {
        $this->metaDescription = $metaDescription;
    }

    /**
     * @return Collection
     */
    public function getComponents(): Collection
    {
        return $this->components;
    }

    /**
     * @param array $components
     */
    public function setComponents(array $components): void
    {
        $this->components = new ArrayCollection();
        foreach ($components as $component)
        {
            $this->addComponent($component);
        }
    }

    public function addComponent(Component $component)
    {
        $this->components->add($component);
    }

    public function removeComponent(Component $component)
    {
        $this->components->removeElement($component);
    }

    /**
     * @return Page|null
     */
    public function getParent(): ?Page
    {
        return $this->parent;
    }

    /**
     * @param Page|null $parent
     */
    public function setParent(?Page $parent): void
    {
        $this->parent = $parent;
    }

    /**
     * @return Collection
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    /**
     * @param array $children
     */
    public function setChildren(array $children): void
    {
        $this->children = new ArrayCollection();
        foreach ($children as $child)
        {
            $this->addChild($child);
        }
    }

    /**
     * @param Page $child
     */
    public function addChild(Page $child)
    {
        $this->children->add($child);
    }

    /**
     * @param Page $child
     */
    public function removeChild(Page $child)
    {
        $this->children->removeElement($child);
    }

    /**
     * @return Collection
     */
    public function getRoutes(): Collection
    {
        return $this->routes;
    }

    /**
     * @param array $routes
     */
    public function setRoutes(array $routes): void
    {
        $this->routes = new ArrayCollection();
        foreach ($routes as $route)
        {
            $this->addChild($route);
        }
    }

    /**
     * @param Route $route
     */
    public function addRoute(Route $route)
    {
        $route->setPage($this);
        $this->routes->add($route);
    }

    /**
     * @param Route $route
     */
    public function removeRoute(Route $route)
    {
        $this->routes->removeElement($route);
    }
}
