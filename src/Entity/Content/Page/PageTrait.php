<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Page;

use ApiPlatform\Core\Annotation\ApiProperty;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Layout\Layout;
use Symfony\Component\Serializer\Annotation\Groups;

trait PageTrait
{
    /**
     * @ORM\Column()
     * @Groups({"default"})
     * @var string
     */
    protected $title = 'Unnamed Page';

    /**
     * @ORM\Column(nullable=true)
     * @Groups({"content", "route"})
     * @var string
     */
    protected $metaDescription;

    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Layout\Layout")
     * @ORM\JoinColumn(onDelete="SET NULL")
     * @ApiProperty()
     * @Groups({"content","route"})
     * @var Layout|null
     */
    protected $layout;

    public function getTitle(): string
    {
        return $this->title ?: 'unnamed';
    }

    public function setTitle(string $title)
    {
        $this->title = $title;
        return $this;
    }

    public function getMetaDescription(): string
    {
        return $this->metaDescription ?: '';
    }

    public function setMetaDescription(string $metaDescription)
    {
        $this->metaDescription = $metaDescription;
        return $this;
    }

    public function getLayout(): ?Layout
    {
        return $this->layout;
    }

    public function setLayout(?Layout $layout)
    {
        $this->layout = $layout;
        return $this;
    }

    public function getDefaultRoute(): string
    {
        return $this->getTitle();
    }

    public function getDefaultRouteName(): string
    {
        return $this->getTitle();
    }
}
