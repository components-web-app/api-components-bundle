<?php

namespace Silverback\ApiComponentBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;

trait ValidComponentTrait
{
    /**
     * @var ArrayCollection
     */
    protected $validComponents;

    /**
     * @return ArrayCollection
     */
    public function getValidComponents(): ArrayCollection
    {
        return $this->validComponents;
    }

    /**
     * @param AbstractComponent $component
     * @return ValidComponentTrait
     */
    public function addValidComponent(AbstractComponent $component): self
    {
        $componentClass = \get_class($component);
        if (!$this->validComponents->contains($componentClass)) {
            $this->validComponents->add(\get_class($component));
        }
        return $this;
    }

    /**
     * @param AbstractComponent $component
     * @return ValidComponentTrait
     */
    public function removeValidComponent(AbstractComponent $component): self
    {
        $this->validComponents->removeElement(\get_class($component));
        return $this;
    }

    /**
     * @param ValidComponentInterface $entity
     * @param bool $force
     */
    protected function cascadeValidComponents(ValidComponentInterface $entity, bool $force = false): void
    {
        if ($force) {
            // Set to true so force the cascade whether empty or not
            $this->validComponents = $entity->getValidComponents();
            return;
        }
        // Default behaviour - cascade if set on parent
        $parentValidComponents = $entity->getValidComponents();
        if ($parentValidComponents->count()) {
            $this->validComponents = $entity->getValidComponents();
        }
    }
}
