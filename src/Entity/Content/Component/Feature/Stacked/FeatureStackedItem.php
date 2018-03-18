<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Component\Feature\Stacked;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\Component\Feature\AbstractFeatureItem;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Class FeatureStackedItem
 * @package Silverback\ApiComponentBundle\Entity\Content\Component\FeatureList
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource()
 * @ORM\Entity()
 */
class FeatureStackedItem extends AbstractFeatureItem
{
    /**
     * @ORM\Column()
     * @Groups({"component", "content"})
     * @var null|string
     */
    protected $description;

    /**
     * @ORM\Column()
     * @Groups({"component", "content"})
     * @var null|string
     */
    protected $buttonText;

    /**
     * @ORM\Column()
     * @Groups({"component", "content"})
     * @var null|string
     */
    protected $buttonClass;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint(
            'description',
            new Assert\NotBlank()
        );
    }

    /**
     * @return null|string
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param null|string $description
     */
    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return null|string
     */
    public function getButtonText(): ?string
    {
        return $this->buttonText;
    }

    /**
     * @param null|string $buttonText
     */
    public function setButtonText(?string $buttonText): void
    {
        $this->buttonText = $buttonText;
    }

    /**
     * @return null|string
     */
    public function getButtonClass(): ?string
    {
        return $this->buttonClass;
    }

    /**
     * @param null|string $buttonClass
     */
    public function setButtonClass(?string $buttonClass): void
    {
        $this->buttonClass = $buttonClass;
    }
}
