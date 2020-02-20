<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Content\Page;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Silverback\ApiComponentBundle\Entity\Route\Route;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ORM\Entity()
 */
abstract class AbstractPage extends AbstractContent implements PageInterface
{
    use PageTrait;

    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Content\Page\AbstractPage")
     * @ORM\JoinColumn(nullable=true, onDelete="SET NULL")
     * @Groups({"route", "component"})
     * @var null|StaticPage
     */
    protected $parent;

    /**
     * @ORM\Column(type="boolean")
     * @Groups({"content", "route", "component"})
     * @var boolean
     */
    protected $dynamic = false;

    public function getParent(): ?StaticPage
    {
        return $this->parent;
    }

    public function setParent(?StaticPage $parent): self
    {
        $this->parent = $parent;
        return $this;
    }

    public function isDynamic(): bool
    {
        return $this->dynamic;
    }

    public function getDefaultRoute(): string
    {
        return $this->getTitle();
    }

    public function getDefaultRouteName(): string
    {
        return $this->getTitle();
    }

    public function getParentRoute(): ?Route
    {
        return $this->getParent() ? $this->getParent()->getRoutes()->first() : null;
    }
}
