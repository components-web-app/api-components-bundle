<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Feature;

use Doctrine\Common\Collections\Collection;

interface FeatureInterface
{
    /**
     * @return Collection
     */
    public function getItems(): Collection;

    /**
     * @param array $items
     */
    public function setItems(array $items): void;

    /**
     * @param FeatureItemInterface $item
     */
    public function addItem(FeatureItemInterface $item): void;

    /**
     * @param FeatureItemInterface $item
     */
    public function removeItem(FeatureItemInterface $item): void;
}
