<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Component\Feature\TextList;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\Component\Feature\AbstractFeature;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class FeatureTextList
 * @package Silverback\ApiComponentBundle\Entity\Content\Component\FeatureList
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource()
 * @ORM\Entity()
 */
class FeatureTextList extends AbstractFeature
{
    /**
     * @Groups({"component", "content"})
     * @var null|string
     */
    protected $title;

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
