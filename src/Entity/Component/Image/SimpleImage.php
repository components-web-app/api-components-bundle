<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Component\Image;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Component\FileInterface;
use Silverback\ApiComponentBundle\Entity\Component\FileTrait;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * @author Daniel West <daniel@silverback.is>
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
    public function setCaption(?string $caption): SimpleImage
    {
        $this->caption = $caption;
        return $this;
    }
}
