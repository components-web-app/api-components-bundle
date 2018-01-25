<?php

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
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Component\Feature\Columns\FeatureColumnsItem", mappedBy="feature")
     * @ORM\OrderBy({"sortOrder" = "ASC"})
     * @Groups({"page"})
     */
    protected $items;
}
