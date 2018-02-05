<?php

namespace Silverback\ApiComponentBundle\Entity\Component;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentBundle\Entity\Content\ComponentGroup;
use Silverback\ApiComponentBundle\Entity\Content\ContentInterface;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class BaseComponent
 * @package Silverback\ApiComponentBundle\Entity\Component
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource()
 */
abstract class AbstractComponent implements ComponentInterface
{
    /**
     * @var string
     */
    private $id;

    /**
     * @Groups({"component", "content"})
     * @var null|string
     */
    private $className;

    /**
     * @var ComponentLocation[]
     */
    private $locations;

    /**
     * @Groups({"component", "content"})
     * @var ArrayCollection|ComponentGroup[]
     */
    protected $componentGroups;

    /**
     * AbstractComponent constructor.
     */
    public function __construct()
    {
        $this->id = Uuid::uuid4()->getHex();
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
     * @param array $componentGroups
     * @return AbstractComponent
     */
    public function setComponentGroups(array $componentGroups): AbstractComponent
    {
        $this->componentGroups = new ArrayCollection;
        foreach ($componentGroups as $componentGroup) {
            $this->addComponentGroup($componentGroup);
        }
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
     * @return ArrayCollection|ComponentGroup[]
     */
    public function getComponentGroups(): Collection
    {
        return $this->componentGroups;
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
