<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Content;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentBundle\Entity\Component\ComponentLocation;
use Silverback\ApiComponentBundle\Entity\RestrictedResourceInterface;
use Silverback\ApiComponentBundle\Entity\TimestampedEntityTrait;

/**
 * @package Silverback\ApiComponentBundle\Entity
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity(repositoryClass="Silverback\ApiComponentBundle\Repository\Content\AbstractContentRepository")
 * @ORM\Table(name="content")
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="type", type="string")
 * @ORM\DiscriminatorMap({
 *     "abstract_page" = "Silverback\ApiComponentBundle\Entity\Content\Page\AbstractPage",
 *     "static_page" = "Silverback\ApiComponentBundle\Entity\Content\Page\StaticPage",
 *     "dynamic_page" = "Silverback\ApiComponentBundle\Entity\Content\Page\DynamicPage",
 *     "component_group" = "Silverback\ApiComponentBundle\Entity\Content\ComponentGroup\ComponentGroup"
 * })
 */
abstract class AbstractContent implements ContentInterface, RestrictedResourceInterface
{
    use TimestampedEntityTrait;

    /**
     * @ORM\Id()
     * @ORM\Column(type="string", length=36)
     * @var string
     */
    protected $id;

    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Component\ComponentLocation", mappedBy="content", cascade={"persist", "remove"})
     * @ORM\OrderBy({"sort"="ASC"})
     * @var Collection|ComponentLocation[]
     */
    protected $componentLocations;

    /**
     * @ORM\Column(type="json_array", nullable=true)
     * @var array|null
     */
    protected $securityRoles;

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
     * @param ComponentLocation[]|iterable $componentLocations
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

    public function getSecurityRoles(): ?array
    {
        return $this->securityRoles;
    }

    public function addSecurityRole(string $securityRole): self
    {
        $this->securityRoles[] = $securityRole;
        return $this;
    }

    public function setSecurityRoles(?array $securityRoles): self
    {
        if (!$securityRoles) {
            $this->securityRoles = $securityRoles;
            return $this;
        }
        $this->securityRoles = [];
        foreach ($securityRoles as $securityRole) {
            $this->addSecurityRole($securityRole);
        }
        return $this;
    }
}
