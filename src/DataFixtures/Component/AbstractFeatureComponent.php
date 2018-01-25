<?php

namespace Silverback\ApiComponentBundle\DataFixtures\Component;

use Silverback\ApiComponentBundle\Entity\Component\Feature\FeatureInterface;

abstract class AbstractFeatureComponent extends AbstractComponent
{
    protected function addFeatureItem (FeatureInterface $feature, string $label, int $order = null, ?string $link) {
        if (null === $order) {
            $lastItem = $feature->getItems()->last();
            if (!$lastItem) {
                $order = 0;
            } else {
                $order = $lastItem->getSortOrder() + 1;
            }
        }
        $featureItem = $feature->createItem();
        $featureItem->setLabel($label);
        $featureItem->setSortOrder($order);
        $featureItem->setLink($link);
        $feature->addItem($featureItem);
        $this->manager->persist($featureItem);
        return $featureItem;
    }
}
