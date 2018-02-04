<?php

namespace Silverback\ApiComponentBundle\Entity\Content;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentBundle\Entity\Component\ComponentInterface;
use Silverback\ApiComponentBundle\Entity\Navigation\Route\RouteAware;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class AbstractContent
 * @package Silverback\ApiComponentBundle\Entity
 * @author Daniel West <daniel@silverback.is>
 */
abstract class AbstractContent extends RouteAware implements ContentInterface
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @Groups({"content"})
     * @var Collection
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
     * @return Collection
     */
    public function getComponents(): Collection
    {
        return $this->components;
    }

    /**
     * @param ComponentInterface $component
     * @return AbstractContent
     */
    public function addComponent(ComponentInterface $component): AbstractContent
    {
        $this->components->add($component);
        return $this;
    }

    /**
     * @param ComponentInterface $component
     * @return AbstractContent
     */
    public function removeComponent(ComponentInterface $component): AbstractContent
    {
        $this->components->removeElement($component);
        return $this;
    }
}
