<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Feature;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class AbstractFeatureItem
 * @package Silverback\ApiComponentBundle\Entity\Component\Feature
 * @ORM\Entity()
 * @ORM\Table(name="feature_item")
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="discr", type="string")
 * @ORM\DiscriminatorMap({
 *     "columns_item" = "Silverback\ApiComponentBundle\Entity\Component\Feature\Columns\FeatureColumnsItem",
 *     "stacked_item" = "Silverback\ApiComponentBundle\Entity\Component\Feature\Stacked\FeatureStackedItem",
 *     "text_list_item" = "Silverback\ApiComponentBundle\Entity\Component\Feature\TextList\FeatureTextListItem"
 * })
 */
abstract class AbstractFeatureItem implements FeatureItemInterface
{

    protected $feature;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @var int
     */
    private $id;

    /**
     * @ORM\Column(type="string")
     * @Groups({"page"})
     * @Assert\NotBlank()
     * @var string
     */
    private $label;

    /**
     * @ORM\Column(type="integer")
     * @Groups({"page"})
     * @var null|int
     */
    private $sortOrder;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @Groups({"page"})
     * @Assert\Url()
     * @var int|null
     */
    protected $link;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @Groups({"page"})
     * @var int|null
     */
    protected $className;

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
     * @return AbstractFeature
     */
    public function getFeature(): AbstractFeature
    {
        return $this->feature;
    }

    /**
     * @param AbstractFeature $feature
     */
    public function setFeature(AbstractFeature $feature): void
    {
        $this->feature = $feature;
    }

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @param string $label
     */
    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    /**
     * @return int|null
     */
    public function getSortOrder(): ?int
    {
        return $this->sortOrder;
    }

    /**
     * @param int|null $sortOrder
     */
    public function setSortOrder(?int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    /**
     * @return null|string
     */
    public function getLink(): ?string
    {
        return $this->link;
    }

    /**
     * @param null|string $link
     */
    public function setLink(?string $link): void
    {
        $this->link = $link;
    }

    /**
     * @return int|null
     */
    public function getClassName(): ?int
    {
        return $this->className;
    }

    /**
     * @param int|null $className
     */
    public function setClassName(?int $className): void
    {
        $this->className = $className;
    }
}
