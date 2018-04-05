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
 *         "get"={"method"="GET", "path"="/routes/{id}", "requirements"={"id"=".+"}},
 *         "put"={"method"="PUT", "path"="/routes/{id}", "requirements"={"id"=".+"}},
 *         "delete"={"method"="DELETE", "path"="/routes/{id}", "requirements"={"id"=".+"}}
 *     }
 * )
 * @ORM\Entity()
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
     * @ORM\Column(type="string")
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
     * @ORM\JoinColumn(name="redirect", referencedColumnName="name")
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
     */
    public function setName(string $name): void
    {
        $this->name = $name;
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
