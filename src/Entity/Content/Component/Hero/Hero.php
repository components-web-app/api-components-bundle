<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Component\Hero;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Content\Component\Navigation\Tabs\Tabs;
use Silverback\ApiComponentBundle\Entity\Content\ComponentGroup;
use Silverback\ApiComponentBundle\Entity\Content\FileInterface;
use Silverback\ApiComponentBundle\Entity\Content\FileTrait;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Class Hero
 * @package Silverback\ApiComponentBundle\Entity\Content\Component\Hero
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource()
 * @ORM\Entity()
 */
class Hero extends AbstractComponent implements FileInterface
{
    use FileTrait;

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

    public function __construct()
    {
        parent::__construct();
        $this->addValidComponent(Tabs::class);
        $this->addComponentGroup(new ComponentGroup());
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint(
            'title',
            new Assert\NotNull()
        );
//        $metadata->addPropertyConstraint(
//            'filePath',
//            new Assert\Image()
//        );
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

    public function __toString()
    {
        return 'Hero: ' . $this->getTitle();
    }
}
