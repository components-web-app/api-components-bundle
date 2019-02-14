<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Component\Feature\Columns;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\Feature\AbstractFeatureItem;
use Silverback\ApiComponentBundle\Entity\Component\FileInterface;
use Silverback\ApiComponentBundle\Entity\Component\FileTrait;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Class FeatureColumnsItem
 * @package Silverback\ApiComponentBundle\Entity\Component\FeatureList
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity()
 */
class FeatureColumnsItem extends AbstractFeatureItem implements FileInterface
{
    use FileTrait;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint(
            'filePath',
            new Assert\Image()
        );
    }
}
