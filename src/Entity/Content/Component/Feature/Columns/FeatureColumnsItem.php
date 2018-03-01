<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Component\Feature\Columns;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\Component\Feature\AbstractFeatureItem;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class FeatureColumnsItem
 * @package Silverback\ApiComponentBundle\Entity\Content\Component\FeatureList
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource()
 * @ORM\Entity()
 */
class FeatureColumnsItem extends AbstractFeatureItem
{
    /**
     * @Groups({"component", "content"})
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
