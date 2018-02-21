<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Feature;

use Doctrine\Common\Collections\Collection;
use Silverback\ApiComponentBundle\Entity\Component\Component;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class AbstractFeatureItem
 * @package Silverback\ApiComponentBundle\Entity\Component\Feature
 */
abstract class FeatureItem extends Component implements FeatureItemInterface
{
    /**
     * @var Feature
     */
    private $feature;

    /**
     * @Assert\NotBlank()
     * @var string
     */
    private $label;

    /**
     * @Assert\Url()
     * @var int|null
     */
    protected $link;

    /**
     * @return Feature
     */
    public function getFeature(): Feature
    {
        return $this->feature;
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
     * @return Collection|FeatureItem[]
     */
    public function getSortCollection(): Collection
    {
        return $this->getFeature()->getItems();
    }
}
