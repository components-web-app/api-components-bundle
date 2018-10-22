<?php

namespace Silverback\ApiComponentBundle\Entity\Layout;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentBundle\Entity\Content\Component\Navigation\NavBar\NavBar;
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
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Content\Component\Navigation\NavBar\NavBar")
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
     */
    public function setDefault(bool $default): void
    {
        $this->default = $default;
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
     */
    public function setNavBar(?NavBar $navBar): void
    {
        $this->navBar = $navBar;
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
     */
    public function setClassName(?string $className): void
    {
        $this->className = $className;
    }
}
