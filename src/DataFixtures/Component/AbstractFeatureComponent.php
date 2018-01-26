<?php

namespace Silverback\ApiComponentBundle\DataFixtures\Component;

abstract class AbstractFeatureComponent extends AbstractComponent
{
    protected function addItem (string $label, int $order = null, ?string $link) {
        $feature = $this->entity;
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
