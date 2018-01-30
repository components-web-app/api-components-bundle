<?php

namespace Silverback\ApiComponentBundle\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Component\Nav\AbstractNav;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class ComponentGroup
 * @package Silverback\ApiComponentBundle\Entity\Component
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity()
 * @ApiResource()
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
     * @ORM\ManyToOne(targetEntity="\Silverback\ApiComponentBundle\Entity\Component\Nav\AbstractNav", inversedBy="childGroups")
     * @var AbstractNav
     */
    protected $parent;

    /**
     * @ORM\OneToMany(targetEntity="\Silverback\ApiComponentBundle\Entity\Component\AbstractComponent", mappedBy="group")
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
     * @return AbstractComponent
     */
    public function getParent(): AbstractComponent
    {
        return $this->parent;
    }

    /**
     * @param AbstractComponent $parent
     */
    public function setParent(AbstractComponent $parent): void
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
     * @param AbstractComponent $component
     */
    public function addComponent(AbstractComponent $component) {
        $this->components->add($component);
    }

    /**
     * @param AbstractComponent $component
     */
    public function removeComponent(AbstractComponent $component) {
        $this->components->removeElement($component);
    }
}
