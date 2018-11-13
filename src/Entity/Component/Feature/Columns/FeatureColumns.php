<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Component\Feature\Columns;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\Feature\AbstractFeature;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class FeatureColumns
 * @package Silverback\ApiComponentBundle\Entity\Component\FeatureHorizontal
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity()
 */
class FeatureColumns extends AbstractFeature
{
    /**
     * @ORM\Column()
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
