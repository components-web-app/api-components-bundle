<?php

namespace Silverback\ApiComponentBundle\Entity\Content;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentBundle\Entity\Component\Component;
use Silverback\ApiComponentBundle\Entity\Component\Feature\FeatureItem;
use Silverback\ApiComponentBundle\Entity\SortableInterface;
use Silverback\ApiComponentBundle\Entity\SortableTrait;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class ComponentLocation
 * @package Silverback\ApiComponentBundle\Entity\Component
 * @ApiResource()
 */
class ComponentLocation implements SortableInterface
{
    use SortableTrait;

    /**
     * @var string
     */
    private $id;

    /**
     * @Groups({"component"})
     * @var AbstractContent
     */
    private $content;

    /**
     * @Groups({"component", "content", "route"})
     * @var Component
     */
    private $component;

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
     * @return AbstractContent
     */
    public function getContent(): AbstractContent
    {
        return $this->content;
    }

    /**
     * @param AbstractContent $content
     */
    public function setContent(AbstractContent $content): void
    {
        $this->content = $content;
    }

    /**
     * @return Component
     */
    public function getComponent(): Component
    {
        return $this->component;
    }

    /**
     * @param Component $component
     */
    public function setComponent(Component $component): void
    {
        $this->component = $component;
    }

    /**
     * @return Collection|FeatureItem[]
     */
    public function getSortCollection(): Collection
    {
        return $this->content ? $this->content->getComponents() : new ArrayCollection;
    }
}
