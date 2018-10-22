<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Component\Feature\Columns;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\Component\Feature\AbstractFeatureItem;
use Silverback\ApiComponentBundle\Entity\Content\FileInterface;
use Silverback\ApiComponentBundle\Entity\Content\FileTrait;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Class FeatureColumnsItem
 * @package Silverback\ApiComponentBundle\Entity\Content\Component\FeatureList
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity()
 */
class FeatureColumnsItem extends AbstractFeatureItem implements FileInterface
{
    use FileTrait;

    /**
     * @ORM\Column()
     * @Groups({"component", "content"})
     * @var null|string
     */
    protected $description;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint(
            'filePath',
            new Assert\Image()
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
}
