<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Hero;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Component\Navigation\Tabs\Tabs;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Class Hero
 * @package Silverback\ApiComponentBundle\Entity\Component\Hero
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource(shortName="component/hero")
 * @ORM\Entity()
 */
class Hero extends AbstractComponent
{
    /**
     * @ORM\Column(type="string", nullable=false)
     * @Groups({"content", "component"})
     * @var null|string
     */
    private $title;

    /**
     * @ORM\Column(type="string")
     * @Groups({"content", "component"})
     * @var null|string
     */
    private $subtitle;

    /**
     * @ORM\ManyToOne(targetEntity="\Silverback\ApiComponentBundle\Entity\Component\Navigation\Tabs\Tabs")
     * @ORM\JoinColumn(nullable=true, onDelete="SET NULL")
     * @Groups({"content", "component"})
     * @var null|Tabs
     */
    private $tabs;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint(
            'title',
            new Assert\NotNull()
        );
    }

    /**
     * @return null|string
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param null|string $title
     */
    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return null|string
     */
    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    /**
     * @param null|string $subtitle
     */
    public function setSubtitle(?string $subtitle): void
    {
        $this->subtitle = $subtitle;
    }

    /**
     * @return Tabs|null
     */
    public function getTabs(): ?Tabs
    {
        return $this->tabs;
    }

    /**
     * @param Tabs|null $tabs
     */
    public function setTabs(?Tabs $tabs): void
    {
        $this->tabs = $tabs;
    }

    public function __toString()
    {
        return 'Hero: ' . $this->getTitle();
    }
}
