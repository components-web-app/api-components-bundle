<?php

namespace Silverback\ApiComponentBundle\Entity\Component;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class ComponentGroup
 * @package Silverback\ApiComponentBundle\Entity\Component
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity()
 */
class ComponentGroup
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @Groups({"page"})
     * @var int
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="\Silverback\ApiComponentBundle\Entity\Component\Nav\Nav", inversedBy="childGroups")
     * @var Component
     */
    protected $parent;

    /**
     * @ORM\OneToMany(targetEntity="Component", mappedBy="group")
     * @Groups({"page"})
     * @var Collection
     */
    protected $components;

    public function __construct()
    {
        $this->components = new ArrayCollection();
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * @return Component
     */
    public function getParent(): Component
    {
        return $this->parent;
    }

    /**
     * @param Component $parent
     */
    public function setParent(Component $parent): void
    {
        $this->parent = $parent;
    }

    /**
     * @return Collection
     */
    public function getComponents(): Collection
    {
        return $this->components;
    }

    /**
     * @param array $components
     */
    public function setComponents(array $components): void
    {
        $this->components = new ArrayCollection();
        foreach ($components as $component) {
            $this->addComponent($component);
        }
    }

    /**
     * @param Component $component
     */
    public function addComponent(Component $component) {
        $this->components->add($component);
    }

    /**
     * @param Component $component
     */
    public function removeComponent(Component $component) {
        $this->components->removeElement($component);
    }
}
