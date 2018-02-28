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

    private function initValidComponents(): self
    {
        if (!($this->validComponents instanceof ArrayCollection)) {
            $this->validComponents = new ArrayCollection();
        }
        return $this;
    }

    /**
     * @return ArrayCollection
     */
    public function getValidComponents(): ArrayCollection
    {
        $this->initValidComponents();
        return $this->validComponents;
    }

    /**
     * @param string $component
     * @return ValidComponentTrait
     */
    public function addValidComponent(string $component): self
    {
        $this->initValidComponents();
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
        $this->initValidComponents();
        $this->validComponents->removeElement($component);
        return $this;
    }

    /**
     * @param ValidComponentInterface $entity
     * @param bool $force
     */
    protected function cascadeValidComponents(ValidComponentInterface $entity, bool $force = false): void
    {
        $entityValidComponents = $entity->getValidComponents();
        if ($force || $entityValidComponents->count()) {
            $this->validComponents = $entityValidComponents;
        }
    }
}
