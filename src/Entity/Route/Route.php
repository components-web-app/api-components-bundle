<?php

namespace Silverback\ApiComponentBundle\Entity\Route;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\Page;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class Route
 * @package Silverback\ApiComponentBundle\Entity
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity()
 * @ApiResource(
 *     itemOperations={
 *         "get"={"method"="GET", "path"="/routes/{id}", "requirements"={"id"=".+"}},
 *         "put"={"method"="PUT", "path"="/routes/{id}", "requirements"={"id"=".+"}},
 *         "delete"={"method"="DELETE", "path"="/routes/{id}", "requirements"={"id"=".+"}}
 *      }
 * )
 */
class Route
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string")
     * @var string
     */
    private $route;

    /**
     * @ORM\ManyToOne(targetEntity="\Silverback\ApiComponentBundle\Entity\Content\Page", inversedBy="routes")
     * @ORM\JoinColumn(fieldName="page_id", referencedColumnName="id", nullable=true)
     * @var null|Page
     */
    private $page;

    /**
     * @ORM\ManyToOne(targetEntity="\Silverback\ApiComponentBundle\Entity\Route\Route")
     * @ORM\JoinColumn(name="redirect", referencedColumnName="route")
     * @var null|Route
     */
    private $redirect;

    public function __construct(
        string $route = null,
        Page $page = null,
        Route $redirect = null
    )
    {
        $this->setRoute($route);
        $this->setPage($page);
        if ($redirect) {
            $this->setRedirect($redirect);
        }
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
     * @return Page|null
     */
    public function getPage(): ?Page
    {
        return $this->page;
    }

    /**
     * @param Page|null $page
     */
    public function setPage(?Page $page): void
    {
        $this->page = $page;
    }

    public function __toString()
    {
        return $this->getRoute();
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
