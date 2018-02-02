<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Feature;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponentItem;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class AbstractFeatureItem
 * @package Silverback\ApiComponentBundle\Entity\Component\Feature
 *
 * @ORM\Table(name="feature_item")
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="discr", type="string")
 * @ORM\DiscriminatorMap({
 *     "columns_item" = "Silverback\ApiComponentBundle\Entity\Component\Feature\Columns\FeatureColumnsItem",
 *     "stacked_item" = "Silverback\ApiComponentBundle\Entity\Component\Feature\Stacked\FeatureStackedItem",
 *     "text_list_item" = "Silverback\ApiComponentBundle\Entity\Component\Feature\TextList\FeatureTextListItem"
 * })
 */
abstract class AbstractFeatureItem extends AbstractComponentItem implements FeatureItemInterface
{
    /**
     * @ORM\Column(type="string")
     * @Groups({"page"})
     * @Assert\NotBlank()
     * @var string
     */
    private $label;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @Groups({"page"})
     * @Assert\Url()
     * @var int|null
     */
    protected $link;

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
}
