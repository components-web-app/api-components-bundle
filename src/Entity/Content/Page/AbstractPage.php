<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Content\Page;

use ApiPlatform\Core\Annotation\ApiProperty;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Silverback\ApiComponentBundle\Entity\Layout\Layout;
use Silverback\ApiComponentBundle\Entity\Route\Route;
use Silverback\ApiComponentBundle\Entity\Route\RouteAwareInterface;
use Silverback\ApiComponentBundle\Entity\Route\RouteAwareTrait;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class AbstractPage
 * @package Silverback\ApiComponentBundle\Entity\Content
 * @ORM\Entity()
 */
abstract class AbstractPage extends AbstractContent implements RouteAwareInterface
{
    use RouteAwareTrait;

    /**
     * @ORM\Column()
     * @Groups({"content", "route", "component"})
     * @var string
     */
    protected $title;

    /**
     * @ORM\Column()
     * @Groups({"content", "route"})
     * @var string
     */
    protected $metaDescription;

    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Content\Page\Page")
     * @ORM\JoinColumn(nullable=true, onDelete="SET NULL")
     * @Groups({"route"})
     * @var null|Page
     */
    protected $parent;

    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Layout\Layout")
     * @ORM\JoinColumn(onDelete="SET NULL")
     * @ApiProperty()
     * @Groups({"content","route"})
     * @var Layout|null
     */
    protected $layout;

    public function __construct()
    {
        parent::__construct();
        $this->routes = new ArrayCollection;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title ?: 'unnamed';
    }

    /**
     * @param string $title
     * @return AbstractPage
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getMetaDescription(): string
    {
        return $this->metaDescription ?: '';
    }

    /**
     * @param string $metaDescription
     * @return AbstractPage
     */
    public function setMetaDescription(string $metaDescription): self
    {
        $this->metaDescription = $metaDescription;
        return $this;
    }

    /**
     * @return null|Page
     */
    public function getParent(): ?Page
    {
        return $this->parent;
    }

    /**
     * @param null|Page $parent
     * @return AbstractPage
     */
    public function setParent(?Page $parent): self
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @return Layout|null
     */
    public function getLayout(): ?Layout
    {
        return $this->layout;
    }

    /**
     * @param Layout|null $layout
     * @return AbstractPage
     */
    public function setLayout(?Layout $layout): self
    {
        $this->layout = $layout;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getDefaultRoute(): string
    {
        return $this->getTitle();
    }

    /**
     * @inheritdoc
     */
    public function getDefaultRouteName(): string
    {
        return $this->getTitle();
    }

    /**
     * @inheritdoc
     */
    public function getParentRoute(): ?Route
    {
        return $this->getParent() ? $this->getParent()->getRoutes()->first() : null;
    }

    abstract public function isDynamic(): bool;
}
