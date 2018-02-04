<?php

namespace Silverback\ApiComponentBundle\Entity\Component;

use Doctrine\Common\Collections\Collection;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentBundle\Entity\Component\Feature\AbstractFeatureItem;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;

/**
 * Class ComponentLocation
 * @package Silverback\ApiComponentBundle\Entity\Component
 */
class ComponentLocation implements SortableInterface
{
    use SortableTrait;

    /**
     * @var string
     */
    private $id;

    /**
     * @var AbstractContent
     */
    private $content;

    /**
     * @var AbstractComponent
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
     * @return AbstractComponent
     */
    public function getComponent(): AbstractComponent
    {
        return $this->component;
    }

    /**
     * @param AbstractComponent $component
     */
    public function setComponent(AbstractComponent $component): void
    {
        $this->component = $component;
    }

    /**
     * @return Collection|AbstractFeatureItem[]
     */
    public function getSortCollection(): Collection
    {
        return $this->content->getComponents();
    }
}
