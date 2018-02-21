<?php

namespace Silverback\ApiComponentBundle\Entity\Content;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Component\Feature\FeatureItem;
use Silverback\ApiComponentBundle\Entity\SortableInterface;
use Silverback\ApiComponentBundle\Entity\SortableTrait;
use Silverback\ApiComponentBundle\Validator\Constraints as ACBAssert;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class ComponentLocation
 * @package Silverback\ApiComponentBundle\Entity\Component
 * @ApiResource()
 * @ACBAssert\ComponentLocation()
 * @ORM\Entity()
 */
class ComponentLocation implements SortableInterface
{
    use SortableTrait;

    /**
     * @ORM\Id
     * @ORM\Column(type="string")
     * @var string
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Content\AbstractContent", inversedBy="components")
     * @ORM\JoinColumn(onDelete="CASCADE")
     * @Assert\NotBlank()
     * @Groups({"component"})
     * @var AbstractContent
     */
    private $content;

    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Component\AbstractComponent", inversedBy="locations")
     * @ORM\JoinColumn(onDelete="CASCADE")
     * @Assert\NotBlank()
     * @Groups({"component", "content", "route"})
     * @var AbstractComponent
     */
    private $component;

    public function __construct(
        ?AbstractContent $content = null,
        ?AbstractComponent $component = null
    ) {
        $this->id = Uuid::uuid4()->getHex();
        if ($content) {
            $this->setContent($content);
        }
        if ($component) {
            $this->setComponent($component);
        }
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return AbstractContent
     */
    public function getContent(): AbstractContent
    {
        return $this->content;
    }

    /**
     * @param AbstractContent $content
     * @param bool|null $sortLast
     */
    public function setContent(AbstractContent $content, ?bool $sortLast = true): void
    {
        $this->content = $content;
        $this->setSort($this->calculateSort($sortLast));
    }

    /**
     * @return AbstractComponent
     */
    public function getComponent(): AbstractComponent
    {
        return $this->component;
    }

    /**
     * @param AbstractComponent $component
     */
    public function setComponent(AbstractComponent $component): void
    {
        $this->component = $component;
    }

    /**
     * @return Collection|FeatureItem[]
     */
    public function getSortCollection(): Collection
    {
        return $this->content ? $this->content->getComponents() : new ArrayCollection;
    }
}
