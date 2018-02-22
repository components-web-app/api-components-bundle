<?php

namespace Silverback\ApiComponentBundle\Factory\Component\Item;

use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Entity\Component\Feature\FeatureInterface;
use Silverback\ApiComponentBundle\Entity\Component\Feature\FeatureItemInterface;

class FeatureItemFactory
{
    private $manager;

    public function __construct(
        ObjectManager $manager
    ) {
        $this->manager = $manager;
    }

    public function createItem(
        FeatureInterface $feature,
        string $label,
        ?string $link = null,
        int $order = null
    ) : FeatureItemInterface {
        if (null === $order) {
            $lastItem = $feature->getItems()->last();
            if (!$lastItem) {
                $order = 0;
            } else {
                $order = $lastItem->getSort() + 1;
            }
        }
        $featureItem = $feature->createItem();
        $featureItem->setLabel($label);
        $featureItem->setSort($order);
        $featureItem->setLink($link);
        $feature->addItem($featureItem);
        $this->manager->persist($featureItem);
        return $featureItem;
    }
}
