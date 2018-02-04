<?php

namespace Silverback\ApiComponentBundle\Entity\Component;

use Doctrine\Common\Collections\ArrayCollection;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Silverback\ApiComponentBundle\Entity\Content\ComponentGroup;
use Silverback\ApiComponentBundle\Entity\Content\ContentInterface;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class BaseComponent
 * @package Silverback\ApiComponentBundle\Entity\Component
 * @author Daniel West <daniel@silverback.is>
 */
abstract class AbstractComponent implements ComponentInterface
{
    use SortableTrait;

    /**
     * @var string
     */
    private $id;

    /**
     * @Groups({"component"})
     * @var null|string
     */
    private $className;

    /**
     * @var AbstractContent[]
     */
    private $locations;

    /**
     * @var ComponentGroup[]
     */
    protected $componentGroups;

    /**
     * AbstractComponent constructor.
     * @param null|string $className
     */
    public function __construct(?string $className = null)
    {
        $this->id = Uuid::uuid4()->getHex();
        $this->setClassName($className);
        $this->locations = new ArrayCollection;
        $this->componentGroups = new ArrayCollection;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return null|string
     */
    public function getClassName(): ?string
    {
        return $this->className;
    }

    /**
     * @param null|string $className
     * @return AbstractComponent
     */
    public function setClassName(?string $className): AbstractComponent
    {
        $this->className = $className;
        return $this;
    }

    /**
     * @param ContentInterface $content
     * @param bool|null $sortLast
     * @return AbstractComponent
     */
    public function addLocation(ContentInterface $content, ?bool $sortLast = null): AbstractComponent
    {
        $this->locations->add($content);
        if (null !== $sortLast) {
            if ($sortLast) {
                $lastItem = $content->getComponents()->last();
                $this->setSort($lastItem ? ($lastItem->getSort() + 1) : 0);
            } else {
                $firstItem = $content->getComponents()->first();
                $this->setSort($firstItem ? ($firstItem->getSort() - 1) : 0);
            }
        }
        return $this;
    }

    /**
     * @param ContentInterface $content
     * @return AbstractComponent
     */
    public function removeLocation(ContentInterface $content): AbstractComponent
    {
        $this->locations->removeElement($content);
        return $this;
    }

    /**
     * @param ComponentGroup $componentGroup
     * @return AbstractComponent
     */
    public function addComponentGroup(ComponentGroup $componentGroup): AbstractComponent
    {
        $this->componentGroups->add($componentGroup);
        return $this;
    }

    /**
     * @param ComponentGroup $componentGroup
     * @return AbstractComponent
     */
    public function removeComponentGroup(ComponentGroup $componentGroup): AbstractComponent
    {
        $this->componentGroups->removeElement($componentGroup);
        return $this;
    }

    /**
     * @Groups({"component"})
     * @return string
     */
    public static function getComponentName(): string
    {
        $explodedClass = explode('\\', static::class);
        return array_pop($explodedClass);
    }
}
