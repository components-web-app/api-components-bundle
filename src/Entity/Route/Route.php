<?php

namespace Silverback\ApiComponentBundle\Entity\Route;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Route
 * @package Silverback\ApiComponentBundle\Entity
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource(
 *     itemOperations={
 *         "get"={"requirements"={"id"=".+"}},
 *         "put"={"requirements"={"id"=".+"}},
 *         "delete"={"requirements"={"id"=".+"}}
 *     }
 * )
 * @ORM\Entity(repositoryClass="Silverback\ApiComponentBundle\Repository\RouteRepository")
 */
class Route
{
    /**
     * @ORM\Id()
     * @ORM\Column(type="string")
     * @Groups({"default"})
     * @var string
     */
    private $route;

    /**
     * @ORM\Column(type="string", unique=true)
     * @Groups({"route"})
     * @var string
     */
    private $name;

    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Content\AbstractContent", cascade={"remove"})
     * @ORM\JoinColumn(onDelete="CASCADE")
     * @Groups({"route"})
     * @Assert\Type("Silverback\ApiComponentBundle\Entity\Route\RouteAwareInterface")
     * @var null|AbstractContent
     */
    private $content;

    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Route\Route")
     * @ORM\JoinColumn(name="redirect", referencedColumnName="route", onDelete="SET NULL")
     * @Groups({"route"})
     * @var null|Route
     */
    private $redirect;

    public function __construct(?string $name = null, ?string $route = null, ?Route $redirect = null)
    {
        $this->name = $name ?: Uuid::uuid4()->getHex();
        $this->route = $route ?: '/' . Uuid::uuid4()->getHex();
        $this->setRedirect($redirect);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return Route
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
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
     * @return Route
     */
    public function setRoute(string $route): self
    {
        $this->route = $route;
        return $this;
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
     * @return Route
     */
    public function setContent(?AbstractContent $content): self
    {
        $this->content = $content;
        return $this;
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
     * @return Route
     */
    public function setRedirect(?Route $redirect): self
    {
        $this->redirect = $redirect;
        return $this;
    }
}
