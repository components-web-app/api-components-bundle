<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Component\Image;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Content\FileInterface;
use Silverback\ApiComponentBundle\Entity\Content\FileTrait;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource(iri="http://schema.org/ImageObject")
 * @ORM\Entity()
 */
class SimpleImage extends AbstractComponent implements FileInterface
{
    use FileTrait;

    /**
     * @ORM\Column(nullable=true)
     * @Groups({"component", "content"})
     * @var null|string
     */
    protected $caption;

    /**
     * @param ClassMetadata $metadata
     */
    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraints(
            'filePath',
            [new Assert\NotBlank()] // , new Assert\Image()
        );
    }

    /**
     * @return null|string
     */
    public function getCaption(): ?string
    {
        return $this->caption;
    }

    /**
     * @param null|string $caption
     * @return SimpleImage
     */
    public function setCaption(?string $caption): self
    {
        $this->caption = $caption;
        return $this;
    }
}
