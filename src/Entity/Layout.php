<?php

namespace Silverback\ApiComponentBundle\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Silverback\ApiComponentBundle\Entity\Component\Nav\Navbar\Navbar;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;

/**
 * @ApiResource(
 *     attributes={
 *          "normalization_context"={"groups"={"layout"}}
 *     }
 * )
 * @ORM\Entity(repositoryClass="Silverback\ApiComponentBundle\Repository\LayoutRepository")
 */
class Layout
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="\Silverback\ApiComponentBundle\Entity\Component\Nav\Navbar\Navbar")
     * @var null|Navbar
     * @Groups({"layout"})
     * @MaxDepth(5)
     */
    private $nav;

    /**
     * @ORM\Column(type="boolean", name="`default`")
     * @var bool
     */
    private $default = false;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * @return Navbar|null
     */
    public function getNav(): ?Navbar
    {
        return $this->nav;
    }

    /**
     * @param Navbar|null $nav
     */
    public function setNav(?Navbar $nav): void
    {
        $this->nav = $nav;
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
}
