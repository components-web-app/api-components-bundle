<?php

namespace Silverback\ApiComponentBundle\Entity\Component;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Silverback\ApiComponentBundle\Entity\Content\ComponentGroup;
use Silverback\ApiComponentBundle\Entity\Content\ComponentLocation;
use Silverback\ApiComponentBundle\Entity\Content\ContentInterface;
use Silverback\ApiComponentBundle\Entity\ValidComponentTrait;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class AbstractComponent
 * @package Silverback\ApiComponentBundle\Entity\Component
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource()
 */
abstract class AbstractComponent implements ComponentInterface
{
    use ValidComponentTrait;

    /**
     * @Groups({"component"})
     * @var AbstractComponent|null
     */
    private $parent;

    /**
     * @return AbstractComponent|null
     */
    public function getParent(): ?AbstractComponent
    {
        return $this->parent;
    }

    /**
     * @param AbstractComponent|null $parent
     */
    public function setParent(?AbstractComponent $parent): void
    {
        $this->parent = $parent;
    }

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
    protected $locations;

    /**
     * @ApiProperty(attributes={"fetchEager": false})
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
        $this->validComponents = new ArrayCollection;
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
        $componentGroup->setParent($this);
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

    /**
     * @param AbstractComponent $component
     * @param int $componentGroupOffset
     * @return ComponentGroup
     * @throws \InvalidArgumentException
     */
    private function getComponentComponentGroup(AbstractComponent $component, int $componentGroupOffset = 0): ComponentGroup
    {
        /** @var ComponentGroup $componentGroup */
        $componentGroup = $component->getComponentGroups()->offsetGet($componentGroupOffset);
        if (!$componentGroup) {
            throw new \InvalidArgumentException(sprintf('There is no component group child of this component with the offset %d', $componentGroupOffset));
        }
        return $componentGroup;
    }

    /**
     * @param AbstractComponent $child
     * @param int $componentGroupOffset
     * @return AbstractComponent
     * @throws \InvalidArgumentException
     */
    public function addChildComponent(AbstractComponent $child, int $componentGroupOffset = 0): AbstractComponent
    {
        $componentGroup = $this->getComponentComponentGroup($this, $componentGroupOffset);
        $componentGroup->addComponent(new ComponentLocation($componentGroup, $child));
        return $this;
    }

    /**
     * @param AbstractComponent $parent
     * @param int $componentGroupOffset
     * @return AbstractComponent
     * @throws \InvalidArgumentException
     */
    public function addToParentComponent(AbstractComponent $parent, int $componentGroupOffset = 0): AbstractComponent
    {
        if (!\in_array($parent, $this->getParentComponents(), true)) {
            $componentGroup = $this->getComponentComponentGroup($parent, $componentGroupOffset);
            $componentGroup->addComponent(new ComponentLocation($componentGroup, $this));
        }
        return $this;
    }

    /**
     * @return AbstractComponent[]
     */
    private function getParentComponents(): array
    {
        $parentContent = $this->getParentContent();
        return array_unique(
            array_filter(
                array_map(
                    function ($content) {
                        if ($content instanceof ComponentGroup) {
                            return $content->getParent();
                        }
                    },
                    $parentContent
                )
            )
        );
    }

    /**
     * @return AbstractContent[]
     */
    private function getParentContent(): array
    {
        return array_unique(
            array_filter(
                array_map(
                    function (ComponentLocation $loc) {
                        return $loc->getContent();
                    },
                    $this->locations->toArray()
                )
            )
        );
    }
}
