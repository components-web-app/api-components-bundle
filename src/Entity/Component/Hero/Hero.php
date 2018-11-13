<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Component\Hero;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Component\FileInterface;
use Silverback\ApiComponentBundle\Entity\Component\FileTrait;
use Silverback\ApiComponentBundle\Entity\Component\Navigation\Tabs\Tabs;
use Silverback\ApiComponentBundle\Entity\Content\ComponentGroup\ComponentGroup;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Class Hero
 * @package Silverback\ApiComponentBundle\Entity\Component\Hero
 * @author Daniel West <daniel@silverback.is>
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
     * @return self
     */
    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
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
     * @return self
     */
    public function setSubtitle(?string $subtitle): self
    {
        $this->subtitle = $subtitle;
        return $this;
    }

    public function __toString()
    {
        return 'Hero: ' . $this->getTitle();
    }
}
