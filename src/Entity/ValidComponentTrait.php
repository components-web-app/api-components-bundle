<?php

namespace Silverback\ApiComponentBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Validator\Constraints as ACBAssert;

trait ValidComponentTrait
{
    /**
     * @ORM\Column(type="array")
     * @ACBAssert\ComponentTypeClasses()
     * @var ArrayCollection|AbstractComponent[]
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
     * @param string $component
     * @return ValidComponentTrait
     */
    public function addValidComponent(string $component): self
    {
        if (!$this->validComponents->contains($component)) {
            $this->validComponents->add($component);
        }
        return $this;
    }

    /**
     * @param string $component
     * @return ValidComponentTrait
     */
    public function removeValidComponent(string $component): self
    {
        $this->validComponents->removeElement($component);
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
