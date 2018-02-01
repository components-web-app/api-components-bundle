<?php

namespace Silverback\ApiComponentBundle\Entity\Content;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Route\RouteAware;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class AbstractContent
 * @package Silverback\ApiComponentBundle\Entity
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity()
 * @ORM\Table(name="content")
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="discr", type="string")
 * @ORM\DiscriminatorMap({
 *     "page" = "\Silverback\ApiComponentBundle\Entity\Content\Page",
 *     "component_group" = "\Silverback\ApiComponentBundle\Entity\Content\ComponentGroup"
 * })
 */
abstract class AbstractContent extends RouteAware
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @Groups({"page"})
     * @var int
     */
    protected $id;

    /**
     * @ORM\OneToMany(targetEntity="\Silverback\ApiComponentBundle\Entity\Component\AbstractComponent", mappedBy="parentContent")
     * @Groups({"page"})
     * @var Collection
     */
    protected $components;

    public function __construct()
    {
        parent::__construct();
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
