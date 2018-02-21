<?php

namespace Silverback\ApiComponentBundle\Entity\Content;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentBundle\Entity\Component\ComponentLocation;
use Silverback\ApiComponentBundle\Entity\Navigation\Route\RouteAware;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class AbstractContent
 * @package Silverback\ApiComponentBundle\Entity
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource()
 */
abstract class AbstractContent extends RouteAware implements ContentInterface
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @Groups({"content", "route"})
     * @var Collection|ComponentLocation[]
     */
    protected $components;

    public function __construct()
    {
        parent::__construct();
        $this->id = Uuid::uuid4()->getHex();
        $this->components = new ArrayCollection;
    }

    /**
     * @return string
     */
    public function getId()
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
