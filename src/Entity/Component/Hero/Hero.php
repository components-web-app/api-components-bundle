<?php

namespace Silverback\ApiComponentBundle\Entity\Component\Hero;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Layout\NavBar\NavBar;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 *
 * @ApiResource()
 */
class Hero extends AbstractComponent
{
    /**
     * @ORM\ManyToOne(targetEntity="\Silverback\ApiComponentBundle\Entity\Component\Nav\Navbar\Navbar")
     * @ORM\JoinColumn(nullable=true)
     * @Groups({"page"})
     * @var null|NavBar
     */
    private $nav;

    /**
     * @ORM\Column(type="string")
     * @Groups({"page"})
     * @var null|string
     */
    private $title;

    /**
     * @ORM\Column(type="string")
     * @Groups({"page"})
     * @var null|string
     */
    private $subtitle;

    /**
     * @return NavBar|null
     */
    public function getNav(): ?NavBar
    {
        return $this->nav;
    }

    /**
     * @param NavBar|null $nav
     */
    public function setNav(?NavBar $nav): void
    {
        $this->nav = $nav;
    }

    /**
     * @return null|string
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param null|string $title
     */
    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return null|string
     */
    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    /**
     * @param null|string $subtitle
     */
    public function setSubtitle(?string $subtitle): void
    {
        $this->subtitle = $subtitle;
    }

    public function __toString()
    {
        return 'Hero: ' . $this->getTitle();
    }
}
