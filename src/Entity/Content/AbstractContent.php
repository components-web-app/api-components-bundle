<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Content;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentBundle\Entity\Component\ComponentLocation;
use Silverback\ApiComponentBundle\Entity\TimestampedEntityTrait;

/**
 * @package Silverback\ApiComponentBundle\Entity
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity()
 * @ORM\Table(name="content")
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="type", type="string")
 * @ORM\DiscriminatorMap({
 *     "component_group" = "Silverback\ApiComponentBundle\Entity\Content\ComponentGroup\ComponentGroup",
 *     "abstract_page" = "Silverback\ApiComponentBundle\Entity\Content\Page\AbstractPage",
 *     "page" = "Silverback\ApiComponentBundle\Entity\Content\Page\Page",
 *     "dynamic_page" = "Silverback\ApiComponentBundle\Entity\Content\Page\Dynamic\AbstractDynamicPage",
 *     "article_page" = "Silverback\ApiComponentBundle\Entity\Content\Page\Dynamic\ArticlePage"
 * })
 */
abstract class AbstractContent implements ContentInterface
{
    use TimestampedEntityTrait;

    /**
     * @ORM\Id()
     * @ORM\Column(type="string")
     * @var string
     */
    protected $id;

    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Component\ComponentLocation", mappedBy="content", cascade={"persist", "remove"})
     * @ORM\OrderBy({"sort"="ASC"})
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
     * @param \Silverback\ApiComponentBundle\Entity\Component\ComponentLocation[]|iterable $componentLocations
     * @return AbstractContent
     */
    public function setComponentLocations(iterable $componentLocations): AbstractContent
    {
        $this->componentLocations = new ArrayCollection;
        /** @var ComponentLocation $componentLocation */
        foreach ($componentLocations as $componentLocation) {
            $this->addComponentLocation($componentLocation);
        }
        return $this;
    }

    /**
     * @param ComponentLocation $componentLocation
     * @return AbstractContent
     */
    public function addComponentLocation(ComponentLocation $componentLocation): AbstractContent
    {
        if (!$this->componentLocations->contains($componentLocation)) {
            $this->componentLocations->add($componentLocation);
            $componentLocation->setContent($this);
        }
        return $this;
    }

    /**
     * @param ComponentLocation $componentLocation
     * @return AbstractContent
     */
    public function removeComponentLocation(ComponentLocation $componentLocation): AbstractContent
    {
        if ($this->componentLocations->contains($componentLocation)) {
            $this->componentLocations->removeElement($componentLocation);
            if ($componentLocation->getContent() === $this) {
                $componentLocation->setContent(null);
            }
        }
        return $this;
    }
}
