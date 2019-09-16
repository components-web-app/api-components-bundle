<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Route;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Silverback\ApiComponentBundle\Entity\Content\Page\Dynamic\DynamicContent;
use Silverback\ApiComponentBundle\Entity\Content\Page\StaticPage;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Route
 * @package Silverback\ApiComponentBundle\Entity
 * @author Daniel West <daniel@silverback.is>
 * @ORM\Entity(repositoryClass="Silverback\ApiComponentBundle\Repository\Route\RouteRepository")
 * @UniqueEntity(fields={"route"}, message="This route is already in use")
 */
class Route
{
    /**
     * @ORM\Id()
     * @ORM\Column(type="string", length=36)
     * @Groups({"route"})
     * @var string
     */
    private $id;

    /**
     * @ORM\Column(type="string", unique=true)
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
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Content\Page\Dynamic\DynamicContent", inversedBy="routes")
     * @ORM\JoinColumn(onDelete="SET NULL", nullable=true)
     * @Groups({"route"})
     * @Assert\Type("Silverback\ApiComponentBundle\Entity\Route\RouteAwareInterface")
     * @var null|DynamicContent
     */
    private $dynamicContent;

    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Content\Page\StaticPage", inversedBy="routes")
     * @ORM\JoinColumn(onDelete="SET NULL", nullable=true)
     * @Groups({"route"})
     * @Assert\Type("Silverback\ApiComponentBundle\Entity\Route\RouteAwareInterface")
     * @var null|StaticPage
     */
    private $staticPage;

    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Route\Route", inversedBy="redirectedFrom")
     * @ORM\JoinColumn(name="redirect", referencedColumnName="id", onDelete="SET NULL")
     * @Groups({"route_read"})
     * @var null|Route
     */
    private $redirect;

    /**
     * @ORM\OneToMany(targetEntity="Silverback\ApiComponentBundle\Entity\Route\Route", mappedBy="redirect")
     * @Groups({"route"})
     * @var Route[]|Collection
     */
    private $redirectedFrom;

    public function __construct(?string $name = null, ?string $route = null, ?Route $redirect = null)
    {
        $this->id = Uuid::uuid4()->getHex();
        $this->name = $name ?: Uuid::uuid4()->getHex();
        $this->route = $route ?: '/' . Uuid::uuid4()->getHex();
        $this->redirectedFrom = new ArrayCollection();
        $this->setRedirect($redirect);
    }

    public function getRedirectedFrom(): Collection
    {
        return $this->redirectedFrom;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
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

    public function getDynamicContent(): ?DynamicContent
    {
        return $this->dynamicContent;
    }

    public function setDynamicContent(?DynamicContent $dynamicContent): self
    {
        $this->dynamicContent = $dynamicContent;
        return $this;
    }

    public function getStaticPage(): ?StaticPage
    {
        return $this->staticPage;
    }

    public function setStaticPage(?StaticPage $staticPage): self
    {
        $this->staticPage = $staticPage;
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

    /**
     * @Groups({"route_write"})
     * @param null|Route $redirectRoute
     */
    public function setRedirectRoute(?Route $redirectRoute): void
    {
        $this->redirect = $redirectRoute;
    }
}
