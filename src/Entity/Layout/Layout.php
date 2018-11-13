<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Layout;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentBundle\Entity\Component\Navigation\NavBar\NavBar;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class Layout
 * @package Silverback\ApiComponentBundle\Entity\Layout
 * @ORM\Entity(repositoryClass="Silverback\ApiComponentBundle\Repository\LayoutRepository")
 */
class Layout
{
    /**
     * @ORM\Id()
     * @ORM\Column(type="string")
     * @var string
     */
    private $id;

    /**
     * @ORM\Column(type="boolean", name="is_default")
     * @Groups({"layout"})
     * @var bool
     */
    private $default = false;

    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Component\Navigation\NavBar\NavBar")
     * @ORM\JoinColumn(onDelete="SET NULL")
     * @Groups({"layout", "route"})
     * @var null|NavBar
     */
    private $navBar;

    /**
     * @ORM\Column(nullable=true)
     * @Groups({"layout"})
     * @var null|string
     */
    private $className;

    public function __construct()
    {
        $this->id = Uuid::uuid4()->getHex();
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return bool
     */
    public function isDefault(): bool
    {
        return $this->default;
    }

    /**
     * @param bool $default
     * @return Layout
     */
    public function setDefault(bool $default): self
    {
        $this->default = $default;
        return $this;
    }

    /**
     * @return null|NavBar
     */
    public function getNavBar(): ?NavBar
    {
        return $this->navBar;
    }

    /**
     * @param null|NavBar $navBar
     * @return Layout
     */
    public function setNavBar(?NavBar $navBar): self
    {
        $this->navBar = $navBar;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getClassName(): ?string
    {
        return $this->className;
    }

    /**
     * @param null|string $className
     * @return Layout
     */
    public function setClassName(?string $className): self
    {
        $this->className = $className;
        return $this;
    }
}
