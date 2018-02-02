<?php

namespace Silverback\ApiComponentBundle\Entity\Content;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Silverback\ApiComponentBundle\Entity\Navigation\Route\RouteAwareInterface;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class Page
 * @package Silverback\ApiComponentBundle\Entity\Content
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource()
 */
class Page extends AbstractContent
{
    /**
     * @Groups({"content"})
     * @var string
     */
    private $title;

    /**
     * @Groups({"content"})
     * @var string
     */
    private $metaDescription;

    /**
     * @Groups({"content"})
     * @var null|RouteAwareInterface
     */
    private $parent;

    /**
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
     * @return null|string
     */
    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    /**
     * @return RouteAwareInterface|null
     */
    public function getParent(): ?RouteAwareInterface
    {
        return $this->parent;
    }

    /**
     * @param RouteAwareInterface $child
     */
    public function addChild(RouteAwareInterface $child)
    {
        $this->children->add($child);
    }

    /**
     * @param RouteAwareInterface $child
     */
    public function removeChild(RouteAwareInterface $child)
    {
        $this->children->removeElement($child);
    }
}
