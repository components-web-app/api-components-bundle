<?php

namespace Silverback\ApiComponentBundle\Entity\Content;

use ApiPlatform\Core\Annotation\ApiResource;
use Silverback\ApiComponentBundle\Entity\Layout\Layout;
use Silverback\ApiComponentBundle\Entity\Navigation\Route\RouteAwareInterface;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class Page
 * @package Silverback\ApiComponentBundle\Entity\Content
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource()
 */
class Page extends AbstractContent
{
    /**
     * @Groups({"content"})
     * @var string
     */
    private $title;

    /**
     * @Groups({"content"})
     * @var string
     */
    private $metaDescription;

    /**
     * @var null|RouteAwareInterface
     */
    private $parent;

    /**
     * @Groups({"content"})
     * @var Layout|null
     */
    private $layout;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getMetaDescription(): string
    {
        return $this->metaDescription;
    }

    /**
     * @param string $metaDescription
     */
    public function setMetaDescription(string $metaDescription): void
    {
        $this->metaDescription = $metaDescription;
    }

    /**
     * @return null|RouteAwareInterface
     */
    public function getParent(): ?RouteAwareInterface
    {
        return $this->parent;
    }

    /**
     * @param null|RouteAwareInterface $parent
     */
    public function setParent(?RouteAwareInterface $parent): void
    {
        $this->parent = $parent;
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
     */
    public function setLayout(?Layout $layout): void
    {
        $this->layout = $layout;
    }
}
