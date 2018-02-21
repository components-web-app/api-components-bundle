<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Feature\Columns;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\Feature\FeatureItem;
use Silverback\ApiComponentBundle\Entity\Component\FileInterface;
use Silverback\ApiComponentBundle\Entity\Component\FileTrait;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class FeatureColumnsItem
 * @package Silverback\ApiComponentBundle\Entity\Component\FeatureList
 * @author Daniel West <daniel@silverback.is>
 */
class FeatureColumnsItem extends FeatureItem implements FileInterface
{
    use FileTrait;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @Groups({"page"})
     * @var null|string
     */
    protected $description;

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
