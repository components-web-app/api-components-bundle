<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Feature\Columns;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\Feature\AbstractFeatureItem;
use Silverback\ApiComponentBundle\Entity\Component\FileInterface;
use Silverback\ApiComponentBundle\Entity\Component\FileTrait;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class FeatureColumnsItem
 * @package Silverback\ApiComponentBundle\Entity\Component\FeatureList
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity()
 */
class FeatureColumnsItem extends AbstractFeatureItem implements FileInterface
{
    use FileTrait;

    /**
     * @ORM\ManyToOne(targetEntity="FeatureColumns", inversedBy="items")
     * @var FeatureColumns
     */
    protected $feature;

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
