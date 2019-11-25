<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Component;

use ApiPlatform\Core\Annotation\ApiProperty;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Silverback\ApiComponentBundle\Entity\Content\ComponentGroup\ComponentGroup;
use Silverback\ApiComponentBundle\Entity\DeleteCascadeInterface;
use Silverback\ApiComponentBundle\Entity\ValidComponentTrait;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class AbstractComponent
 * @package Silverback\ApiComponentBundle\Entity\Component
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity()
 * @ORM\Table(name="component")
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="type", type="string")
 * @ORM\DiscriminatorMap({
 *     "content" = "Silverback\ApiComponentBundle\Entity\Component\Content\Content",
 *     "form" = "Silverback\ApiComponentBundle\Entity\Component\Form\Form",
 *     "gallery" = "Silverback\ApiComponentBundle\Entity\Component\Gallery\Gallery",
 *     "gallery_item" = "Silverback\ApiComponentBundle\Entity\Component\Gallery\GalleryItem",
 *     "hero" = "Silverback\ApiComponentBundle\Entity\Component\Hero\Hero",
 *     "feature_columns" = "Silverback\ApiComponentBundle\Entity\Component\Feature\Columns\FeatureColumns",
 *     "feature_columns_item" = "Silverback\ApiComponentBundle\Entity\Component\Feature\Columns\FeatureColumnsItem",
 *     "feature_stacked" = "Silverback\ApiComponentBundle\Entity\Component\Feature\Stacked\FeatureStacked",
 *     "feature_stacked_item" = "Silverback\ApiComponentBundle\Entity\Component\Feature\Stacked\FeatureStackedItem",
 *     "feature_text_list" = "Silverback\ApiComponentBundle\Entity\Component\Feature\TextList\FeatureTextList",
 *     "feature_text_list_item" = "Silverback\ApiComponentBundle\Entity\Component\Feature\TextList\FeatureTextListItem",
 *     "nav_bar" = "Silverback\ApiComponentBundle\Entity\Component\Navigation\NavBar\NavBar",
 *     "nav_bar_item" = "Silverback\ApiComponentBundle\Entity\Component\Navigation\NavBar\NavBarItem",
 *     "tabs" = "Silverback\ApiComponentBundle\Entity\Component\Navigation\Tabs\Tabs",
 *     "tabs_item" = "Silverback\ApiComponentBundle\Entity\Component\Navigation\Tabs\TabsItem",
 *     "menu" = "Silverback\ApiComponentBundle\Entity\Component\Navigation\Menu\Menu",
 *     "menu_item" = "Silverback\ApiComponentBundle\Entity\Component\Navigation\Menu\MenuItem",
 *     "collection" = "Silverback\ApiComponentBundle\Entity\Component\Collection\Collection",
 *     "layout_side_column" = "Silverback\ApiComponentBundle\Entity\Component\Layout\SideColumn",
 *     "simple_image" = "Silverback\ApiComponentBundle\Entity\Component\Image\SimpleImage"
 * })
 */
abstract class AbstractComponent implements ComponentInterface, DeleteCascadeInterface
{
    use ValidComponentTrait;

    /**
     * @ORM\Id()
     * @ORM\Column(type="string", length=36)
     * @var string
     */
    private $id;

    /**
     * @ORM\Column(nullable=true)
     * @Groups({"default"})
     * @var null|string
     */
    private $className;

    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Component\ComponentLocation", mappedBy="component", cascade={"persist"})
     * @Groups({"component"})
     * @var Collection|ComponentLocation[]
     */
    protected $locations;

    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Content\ComponentGroup\ComponentGroup", mappedBy="parent", cascade={"persist"})
     * @ORM\OrderBy({"sort"="ASC"})
     * @ApiProperty(attributes={"fetchEager": false})
     * @Groups({"layout", "route", "content", "component"})
     * @var Collection|ComponentGroup[]
     */
    protected $componentGroups;

    /**
     * @ORM\Column(nullable=true)
     * @Groups({"route", "content", "component"})
     * @var string|null
     */
    protected $componentName;

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
     * @return self
     */
    public function setClassName(?string $className): self
    {
        $this->className = $className;
        return $this;
    }

    /**
     * @return Collection|ComponentLocation[]
     */
    public function getLocations()
    {
        return $this->locations;
    }

    /**
     * @param ComponentLocation $componentLocation
     * @return self
     */
    public function addLocation(ComponentLocation $componentLocation): self
    {
        if (!$this->locations->contains($componentLocation)) {
            $componentLocation->setComponent($this);
            $this->locations->add($componentLocation);
        }
        return $this;
    }

    /**
     * @param ComponentLocation $componentLocation
     * @return self
     */
    public function removeLocation(ComponentLocation $componentLocation): self
    {
        if ($this->locations->contains($componentLocation)) {
            $this->locations->removeElement($componentLocation);
        }
        return $this;
    }

    /**
     * @param array $componentGroups
     * @return self
     */
    public function setComponentGroups(array $componentGroups): self
    {
        $this->componentGroups = new ArrayCollection;
        foreach ($componentGroups as $componentGroup) {
            $this->addComponentGroup($componentGroup);
        }
        return $this;
    }

    /**
     * @param ComponentGroup $componentGroup
     * @return self
     */
    public function addComponentGroup(ComponentGroup $componentGroup): self
    {
        if (!$this->componentGroups->contains($componentGroup)) {
            $this->componentGroups->add($componentGroup);
            $componentGroup->setParent($this);
        }
        return $this;
    }

    /**
     * @param ComponentGroup $componentGroup
     * @return self
     */
    public function removeComponentGroup(ComponentGroup $componentGroup): self
    {
        if ($this->componentGroups->contains($componentGroup)) {
            $this->componentGroups->removeElement($componentGroup);
        }
        return $this;
    }

    /**
     * @return Collection|\Silverback\ApiComponentBundle\Entity\Content\ComponentGroup\ComponentGroup[]
     */
    public function getComponentGroups(): Collection
    {
        return $this->componentGroups;
    }

    /**
     * @param null|string $componentName
     * @return self
     */
    public function setComponentName(?string $componentName): self
    {
        $this->componentName = $componentName;
        return $this;
    }

    /**
     * Return the component name for front-end to decipher
     * @return string
     */
    public function getComponentName(): string
    {
        if ($this->componentName) {
            return $this->componentName;
        }
        $explodedClass = explode('\\', static::class);
        return array_pop($explodedClass);
    }

    /**
     * @return bool
     */
    public function onDeleteCascade(): bool
    {
        return false;
    }

    /**
     * @Groups({"component_write"})
     * @param AbstractComponent $parent
     * @param int $componentGroupOffset
     * @return self
     */
    public function setParentComponent(AbstractComponent $parent, int $componentGroupOffset = 0): self
    {
        if (!\in_array($parent, $this->getParentComponents(), true)) {
            $componentGroup = $this->getComponentComponentGroup($parent, $componentGroupOffset);
            if (!$componentGroup->hasComponent($this)) {
                $componentGroup->addComponentLocation(new ComponentLocation($componentGroup, $this));
            }
        }
        return $this;
    }

    /**
     * @Groups({"component_write"})
     * @param ComponentGroup $componentGroup
     * @return self
     */
    public function setParentComponentGroup(ComponentGroup $componentGroup): self
    {
        if (!$componentGroup->hasComponent($this)) {
            $componentGroup->addComponentLocation(new ComponentLocation($componentGroup, $this));
        }
        return $this;
    }

    /**
     * @param AbstractContent $content
     * @return bool
     */
    public function hasParentContent(AbstractContent $content): bool
    {
        foreach ($this->locations as $location) {
            if ($location->getContent() === $content) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param AbstractComponent $component
     * @param int $componentGroupOffset
     * @return ComponentGroup
     */
    private function getComponentComponentGroup(AbstractComponent $component, int $componentGroupOffset = 0): ComponentGroup
    {
        /** @var ComponentGroup $componentGroup */
        $componentGroup = $component->getComponentGroups()->get($componentGroupOffset);
        if (null === $componentGroup) {
            throw new \InvalidArgumentException(sprintf('There is no component group child of this component with the offset %d', $componentGroupOffset));
        }
        return $componentGroup;
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
