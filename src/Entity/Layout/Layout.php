<?php

namespace Silverback\ApiComponentBundle\Entity\Layout;

use ApiPlatform\Core\Annotation\ApiResource;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentBundle\Entity\Layout\NavBar\NavBar;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @ApiResource()
 */
final class Layout
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var bool
     * @Groups({"layout"})
     */
    private $default;

    /**
     * @var null|NavBar
     * @Groups({"layout"})
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
     * @return mixed
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
     * @return null|NavBar
     */
    public function getNavBar(): ?NavBar
    {
        return $this->navBar;
    }

    /**
     * @param bool $default
     */
    public function setDefault(bool $default): void
    {
        $this->default = $default;
    }

    /**
     * @param null|NavBar $navBar
     */
    public function setNavBar(?NavBar $navBar): void
    {
        $this->navBar = $navBar;
    }
}
