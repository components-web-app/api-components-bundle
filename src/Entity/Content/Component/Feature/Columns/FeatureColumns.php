<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Component\Feature\Columns;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\Component\Feature\AbstractFeature;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Class FeatureColumns
 * @package Silverback\ApiComponentBundle\Entity\Content\Component\FeatureHorizontal
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource(shortName="Component/FeatureColumns")
 * @ORM\Entity()
 */
class FeatureColumns extends AbstractFeature
{
    /**
     * @Groups({"component", "content"})
     * @var int
     */
    protected $columns = 3;

    /**
     * @Groups({"component", "content"})
     * @var null|string
     */
    protected $title;

    /**
     * @param ClassMetadata $metadata
     */
    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $metadata->addPropertyConstraint(
            'columns',
            new Assert\Range(
                [
                    'min' => 1,
                    'minMessage' => 'The FeatureColumns component must have at least 1 column'
                ]
            )
        );
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
