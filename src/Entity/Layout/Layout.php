<?php

namespace Silverback\ApiComponentBundle\Entity\Layout;

use ApiPlatform\Core\Annotation\ApiResource;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentBundle\Entity\Layout\NavBar\NavBar;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class Layout
 * @package Silverback\ApiComponentBundle\Entity\Layout
 * @ApiResource(attributes={"force_eager"=false})
 */
class Layout
{
    /**
     * @var string
     */
    private $id;

    /**
     * @Groups({"layout"})
     * @var bool
     */
    private $default;

    /**
     * @Groups({"layout"})
     * @var null|NavBar
     */
    private $navBar;

    public function __construct(
        bool $default = false,
        ?NavBar $navBar = null
    ) {
        $this->id = Uuid::uuid4()->getHex();
        $this->setNavBar($navBar);
        $this->setDefault($default);
    }

    /**
     * @return string
     */
    public function getId()
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
}
