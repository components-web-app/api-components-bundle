<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Feature\Columns;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\Feature\AbstractFeature;
use Silverback\ApiComponentBundle\Entity\Component\Feature\FeatureItemInterface;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class FeatureColumns
 * @package Silverback\ApiComponentBundle\Entity\Component\FeatureHorizontal
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource(shortName="component/feature_columns")
 * @ORM\Entity()
 */
class FeatureColumns extends AbstractFeature
{
    /**
     * @Groups({"component", "content"})
     * @var null|string
     */
    protected $title;

    /**
     * @return FeatureItemInterface
     */
    public function createItem(): FeatureItemInterface
    {
        return new FeatureColumnsItem();
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
}
