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
 *     "component_group" = "Silverback\ApiComponentBundle\Entity\Content\ComponentGroup",
 *     "article" = "Silverback\ApiComponentBundle\Entity\Content\Dynamic\ArticlePage"
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
     * @ORM\OrderBy({"sort"="ASC"})
     * @Groups({"default"})
     * @MaxDepth(40)
     * @var Collection|ComponentLocation[]
     */
    protected $componentLocations;

    public function __construct()
    {
        $this->id = Uuid::uuid4()->getHex();
        $this->componentLocations = new ArrayCollection;
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
    public function getComponentLocations(): Collection
    {
        return $this->componentLocations;
    }

    /**
     * @param ComponentLocation $componentLocation
     * @return AbstractContent
     */
    public function addComponentLocation(ComponentLocation $componentLocation): AbstractContent
    {
        $this->componentLocations->add($componentLocation);
        return $this;
    }

    /**
     * @param ComponentLocation $componentLocation
     * @return AbstractContent
     */
    public function removeComponentLocation(ComponentLocation $componentLocation): AbstractContent
    {
        $this->componentLocations->removeElement($componentLocation);
        return $this;
    }
}
