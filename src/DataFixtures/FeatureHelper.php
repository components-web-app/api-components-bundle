<?php

namespace Silverback\ApiComponentBundle\DataFixtures;

use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Entity\Component\Feature\FeatureInterface;
use Silverback\ApiComponentBundle\Entity\Component\Feature\FeatureItemInterface;

class FeatureHelper
{
    private $manager;

    public function __construct(
        ObjectManager $manager
    )
    {
        $this->manager = $manager;
    }

    public function createItem(
        FeatureInterface $feature,
        string $label,
        ?string $link = null,
        int $order = null
    ) : FeatureItemInterface
    {
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
