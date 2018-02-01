<?php

namespace Silverback\ApiComponentBundle\Entity\Content;

use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Annotation\ApiSubresource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Route\RouteAwareInterface;
use Silverback\ApiComponentBundle\Entity\Route\RouteAwareTrait;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="Silverback\ApiComponentBundle\Repository\PageRepository")
 * @ORM\EntityListeners({"\Silverback\ApiComponentBundle\EntityListener\PageListener"})
 * @ApiResource()
 */
class Page extends AbstractContent
{
    /**
     * @ORM\Column(type="string")
     * @var string
     */
    private $title;

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    private $metaDescription;

    /**
     * @ORM\ManyToOne(targetEntity="Page", inversedBy="children")
     * @ORM\JoinColumn(nullable=true)
     * @var null|Page
     */
    private $parent;

    /**
     * @ORM\OneToMany(targetEntity="Page", mappedBy="parent")
     * @var Collection
     */
    private $children;

    public function __construct()
    {
        parent::__construct();
        $this->children = new ArrayCollection();
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
}
