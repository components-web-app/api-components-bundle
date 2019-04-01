<?php

namespace Silverback\ApiComponentBundle\Entity\Route;

use Doctrine\ORM\Mapping as ORM;

trait ChildRouteTrait
{
    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Route\Route")
     * @ORM\JoinColumn(nullable=true, referencedColumnName="id", onDelete="SET NULL")
     * @var Route|null
     */
    protected $parentRoute;

    /**
     * @ORM\Column(type="boolean", options={"default":"0"})
     * @var boolean
     */
    protected $nested = false;

    public function isNested(): bool
    {
        return $this->nested;
    }

    public function setNested(bool $nested)
    {
        $this->nested = $nested;
        return $this;
    }

    public function setParentRoute(?Route $parentRoute)
    {
        $this->parentRoute = $parentRoute;
        return $this;
    }

    public function getParentRoute(): ?Route
    {
        if ($this->parentRoute) {
            return $this->parentRoute;
        }
        if ($this->nested) {
            return parent::getParentRoute();
        }
        return null;
    }
}
