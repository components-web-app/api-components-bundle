<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Feature;

use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class AbstractFeatureItem
 * @package Silverback\ApiComponentBundle\Entity\Component\Feature
 */
abstract class AbstractFeatureItem extends AbstractComponent implements FeatureItemInterface
{
    /**
     * @Groups({"component", "content"})
     * @Assert\NotBlank()
     * @var string
     */
    private $label;

    /**
     * @Groups({"component", "content"})
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
