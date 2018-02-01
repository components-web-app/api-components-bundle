<?php

namespace Silverback\ApiComponentBundle\Entity\Component;

use ApiPlatform\Core\Annotation\ApiProperty;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class BaseComponent
 * @package Silverback\ApiComponentBundle\Entity\Component
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity()
 * @ORM\Table(name="component")
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="discr", type="string")
 * @ORM\DiscriminatorMap({
 *     "navbar" = "\Silverback\ApiComponentBundle\Entity\Component\Nav\Navbar\Navbar",
 *     "menu" = "\Silverback\ApiComponentBundle\Entity\Component\Nav\Menu\Menu",
 *     "tabs" = "\Silverback\ApiComponentBundle\Entity\Component\Nav\Tabs\Tabs",
 *     "hero" = "\Silverback\ApiComponentBundle\Entity\Component\Hero\Hero",
 *     "form" = "\Silverback\ApiComponentBundle\Entity\Component\Form\Form",
 *     "content" = "\Silverback\ApiComponentBundle\Entity\Component\Content\Content",
 *     "feature_columns" = "\Silverback\ApiComponentBundle\Entity\Component\Feature\Columns\FeatureColumns",
 *     "feature_stacked" = "\Silverback\ApiComponentBundle\Entity\Component\Feature\Stacked\FeatureStacked",
 *     "feature_text_list" = "\Silverback\ApiComponentBundle\Entity\Component\Feature\TextList\FeatureTextList",
 *     "gallery" = "\Silverback\ApiComponentBundle\Entity\Component\Gallery\Gallery",
 *     "news" = "\Silverback\ApiComponentBundle\Entity\Component\News\News"
 * })
 */
abstract class AbstractComponent implements SortableInterface
{
    use SortableTrait;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @Groups({"component"})
     * @ApiProperty(identifier=true)
     * @var int
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Content\AbstractContent", inversedBy="components")
     * @var AbstractContent
     */
    private $parentContent;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @Groups({"component"})
     * @var null|string
     */
    private $className;

    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Component\AbstractComponentItem", mappedBy="parent")
     * @ORM\OrderBy({"sort" = "ASC"})
     * @Groups({"component"})
     */
    protected $items;

    /**
     * AbstractComponent constructor.
     */
    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    /**
     * @return int|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * @return AbstractContent|null
     */
    public function getParentContent(): ?AbstractContent
    {
        return $this->parentContent;
    }

    /**
     * @param AbstractContent $parent
     * @param int|null $order
     */
    public function setParentContent(?AbstractContent $parentContent, int $order = null): void
    {
        if ($parentContent && null === $order && !$this->getSort()) {
            // auto ordering
            $lastItem = $parentContent->getComponents()->last();
            if ($lastItem) {
                $this->setSort($lastItem->getSort() + 1);
            }
            if (!$parentContent->getComponents()->contains($this)) {
                $parentContent->addComponent($this);
            }
        }
        $this->parentContent = $parentContent;
    }

    /**
     * @return string
     * @Groups({"route", "page"})
     */
    public function getType()
    {
        $explCls = explode('\\', static::class);
        return array_pop($explCls);
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
     */
    public function setClassName(?string $className): void
    {
        $this->className = $className;
    }

    /**
     * @return Collection
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    /**
     * @param array $items
     */
    public function setItems(array $items): void
    {
        $this->items = new ArrayCollection();
        foreach($items as $item)
        {
            $this->addItem($item);
        }
    }

    /**
     * @param AbstractComponentItem $item
     */
    public function addItem(AbstractComponentItem $item): void
    {
        $this->items->add($item);
        $item->setParent($this);
    }

    /**
     * @param AbstractComponentItem $item
     */
    public function removeItem(AbstractComponentItem $item): void
    {
        $this->items->removeElement($item);
    }
}
