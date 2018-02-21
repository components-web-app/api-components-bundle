<?php

namespace Silverback\ApiComponentBundle\Entity\Navigation\Route;

use ApiPlatform\Core\Annotation\ApiResource;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class Route
 * @package Silverback\ApiComponentBundle\Entity
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource(
 *     itemOperations={
 *         "get"={"method"="GET", "path"="/routes/{id}", "requirements"={"id"=".+"}},
 *         "put"={"method"="PUT", "path"="/routes/{id}", "requirements"={"id"=".+"}},
 *         "delete"={"method"="DELETE", "path"="/routes/{id}", "requirements"={"id"=".+"}}
 *     }
 * )
 */
class Route
{
    /**
     * @Groups({"default"})
     * @var string
     */
    private $route;

    /**
     * @Groups({"route"})
     * @var null|AbstractContent
     */
    private $content;

    /**
     * @Groups({"route"})
     * @var null|Route
     */
    private $redirect;

    public function __construct(?string $route = null) {
        $this->route = $route ?? Uuid::uuid4()->getHex();
    }

    /**
     * @return string
     */
    public function getRoute(): string
    {
        return $this->route;
    }

    /**
     * @param string $route
     */
    public function setRoute(string $route): void
    {
        $this->route = $route;
    }

    /**
     * @return null|AbstractContent
     */
    public function getContent(): ?AbstractContent
    {
        return $this->content;
    }

    /**
     * @param null|AbstractContent $content
     */
    public function setContent(?AbstractContent $content): void
    {
        $this->content = $content;
    }

    /**
     * @return null|Route
     */
    public function getRedirect(): ?Route
    {
        return $this->redirect;
    }

    /**
     * @param null|Route $redirect
     */
    public function setRedirect(?Route $redirect): void
    {
        $this->redirect = $redirect;
    }
}
