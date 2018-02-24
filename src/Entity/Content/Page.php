<?php

namespace Silverback\ApiComponentBundle\Entity\Content;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Layout\Layout;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;

/**
 * Class Page
 * @package Silverback\ApiComponentBundle\Entity\Content
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource()
 * @ORM\Entity()
 */
class Page extends AbstractContent
{
    /**
     * @ORM\Column()
     * @Groups({"content"})
     * @var string
     */
    private $title;

    /**
     * @ORM\Column()
     * @Groups({"content"})
     * @var string
     */
    private $metaDescription;

    /**
     * @ORM\ManyToOne(targetEntity="Page")
     * @ORM\JoinColumn(nullable=true)
     * @var null|Page
     */
    private $parent;

    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Layout\Layout")
     * @ORM\JoinColumn(onDelete="SET NULL")
     * @ApiProperty()
     * @Groups({"content"})
     * @var Layout|null
     */
    private $layout;

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getMetaDescription(): string
    {
        return $this->metaDescription;
    }

    /**
     * @param string $metaDescription
     */
    public function setMetaDescription(string $metaDescription): void
    {
        $this->metaDescription = $metaDescription;
    }

    /**
     * @return null|Page
     */
    public function getParent(): ?Page
    {
        return $this->parent;
    }

    /**
     * @param null|Page $parent
     */
    public function setParent(?Page $parent): void
    {
        $this->parent = $parent;
    }

    /**
     * @return Layout|null
     */
    public function getLayout(): ?Layout
    {
        return $this->layout;
    }

    /**
     * @param Layout|null $layout
     */
    public function setLayout(?Layout $layout): void
    {
        $this->layout = $layout;
    }
}
