<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Feature;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\Component;

/**
 * Class AbstractFeature
 * @package Silverback\ApiComponentBundle\Entity\Component\Feature
 * @ORM\MappedSuperclass()
 */
abstract class AbstractFeature extends Component implements FeatureInterface
{
    /**
     * @var Collection
     */
    protected $items;

    /**
     * AbstractFeature constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->items = new ArrayCollection();
    }

    /**
     * @return Collection
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    /**
     * @param array $items
     */
    public function setItems(array $items): void
    {
        $this->items = new ArrayCollection();
        foreach($items as $item)
        {
            $this->addItem($item);
        }
    }

    /**
     * @param FeatureItemInterface $item
     */
    public function addItem(FeatureItemInterface $item): void
    {
        $this->items->add($item);
        $item->setFeature($this);
    }

    /**
     * @param FeatureItemInterface $item
     */
    public function removeItem(FeatureItemInterface $item): void
    {
        $this->items->removeElement($item);
    }
}
