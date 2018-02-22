<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Feature\TextList;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\Feature\AbstractFeatureItem;

/**
 * Class FeatureTextListItem
 * @package Silverback\ApiComponentBundle\Entity\Component\FeatureList
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource(shortName="component/feature_text_list_items")
 * @ORM\Entity()
 */
class FeatureTextListItem extends AbstractFeatureItem
{
}
