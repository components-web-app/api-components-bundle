<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Feature\TextList;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\Feature\AbstractFeature;
use Silverback\ApiComponentBundle\Entity\Component\Feature\FeatureItemInterface;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class FeatureTextList
 * @package Silverback\ApiComponentBundle\Entity\Component\FeatureList
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity()
 * @ApiResource()
 */
class FeatureTextList extends AbstractFeature
{
    /**
     * @ORM\Column(type="smallint")
     * @Groups({"component"})
     * @var int
     */
    protected $columns = 3;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @Groups({"page"})
     * @var null|string
     */
    protected $title;

    /**
     * @return FeatureItemInterface
     */
    public function createItem(): FeatureItemInterface
    {
        return new FeatureTextListItem();
    }

    /**
     * @return int
     */
    public function getColumns(): int
    {
        return $this->columns;
    }

    /**
     * @param int $columns
     */
    public function setColumns(int $columns): void
    {
        $this->columns = $columns;
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
