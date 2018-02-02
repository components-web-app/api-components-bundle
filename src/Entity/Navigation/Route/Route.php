<?php

namespace Silverback\ApiComponentBundle\Entity\Navigation\Route;

use ApiPlatform\Core\Annotation\ApiResource;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class Route
 * @package Silverback\ApiComponentBundle\Entity
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource()
 */
final class Route
{
    /**
     * @Groups({"layout", "content", "component"})
     * @var string
     */
    private $route;

    /**
     * @Groups({"route_read"})
     * @var null|AbstractContent
     */
    private $content;

    /**
     * @Groups({"route_read"})
     * @var null|Route
     */
    private $redirect;

    public function __construct(
        string $route,
        AbstractContent $content,
        ?Route $redirect = null
    ) {
        $this->route = $route;
        $this->content = $content;
        $this->redirect = $redirect;
    }

    /**
     * @return string
     */
    public function getRoute(): string
    {
        return $this->route;
    }

    /**
     * @return null|AbstractContent
     */
    public function getContent(): ?AbstractContent
    {
        return $this->content;
    }

    /**
     * @return null|Route
     */
    public function getRedirect(): ?Route
    {
        return $this->redirect;
    }
}
