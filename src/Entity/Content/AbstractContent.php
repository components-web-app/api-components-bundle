<?php

namespace Silverback\ApiComponentBundle\Entity\Content;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentBundle\Entity\Content\Component\ComponentLocation;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;

/**
 * Class AbstractContent
 * @package Silverback\ApiComponentBundle\Entity
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity()
 * @ORM\Table(name="route_content")
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="type", type="string")
 * @ORM\DiscriminatorMap({
 *     "page" = "Silverback\ApiComponentBundle\Entity\Content\Page",
 *     "component_group" = "Silverback\ApiComponentBundle\Entity\Content\ComponentGroup"
 * })
 */
abstract class AbstractContent implements ContentInterface
{
    /**
     * @ORM\Id()
     * @ORM\Column(type="string")
     * @var string
     */
    protected $id;

    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Content\Component\ComponentLocation", mappedBy="content", cascade={"persist", "remove"})
     * @Groups({"content", "route"})
     * @MaxDepth(10)
     * @var Collection|ComponentLocation[]
     */
    protected $components;

    public function __construct()
    {
        $this->routes = new ArrayCollection;
        $this->id = Uuid::uuid4()->getHex();
        $this->components = new ArrayCollection;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return Collection|ComponentLocation[]
     */
    public function getComponents(): Collection
    {
        return $this->components;
    }

    /**
     * @param ComponentLocation $component
     * @return AbstractContent
     */
    public function addComponent(ComponentLocation $component): AbstractContent
    {
        $this->components->add($component);
        return $this;
    }

    /**
     * @param ComponentLocation $component
     * @return AbstractContent
     */
    public function removeComponent(ComponentLocation $component): AbstractContent
    {
        $this->components->removeElement($component);
        return $this;
    }
}
