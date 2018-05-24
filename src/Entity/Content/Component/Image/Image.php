<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Component\Image;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Content\FileInterface;
use Silverback\ApiComponentBundle\Entity\Content\FileTrait;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource(iri="http://schema.org/ImageObject")
 * @ORM\Entity()
 */
class Image extends AbstractComponent implements FileInterface
{
    use FileTrait;

    /**
     * @param ClassMetadata $metadata
     */
    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraints(
            'filePath',
            [new Assert\NotBlank(), new Assert\Image()]
        );
    }
}
