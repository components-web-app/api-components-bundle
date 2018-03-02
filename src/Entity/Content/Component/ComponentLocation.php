<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Component;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Silverback\ApiComponentBundle\Entity\Content\Component\Feature\AbstractFeatureItem;
use Silverback\ApiComponentBundle\Entity\SortableInterface;
use Silverback\ApiComponentBundle\Entity\SortableTrait;
use Silverback\ApiComponentBundle\Validator\Constraints as ACBAssert;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Class ComponentLocation
 * @package Silverback\ApiComponentBundle\Entity\Content\Component
 * @ApiResource()
 * @ACBAssert\ComponentLocation()
 * @ORM\Entity()
 * @ORM\InheritanceType("SINGLE_TABLE")
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
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Content\AbstractContent", inversedBy="componentLocations")
     * @ORM\JoinColumn(onDelete="CASCADE")
     * @Groups({"component"})
     * @var AbstractContent
     */
    private $content;

    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Content\Component\AbstractComponent", inversedBy="locations")
     * @ORM\JoinColumn(onDelete="CASCADE")
     * @Groups({"component", "content", "route"})
     * @var AbstractComponent
     */
    private $component;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint(
            'content',
            new Assert\NotBlank()
        );
        $metadata->addPropertyConstraint(
            'component',
            new Assert\NotBlank()
        );
    }

    /**
     * ComponentLocation constructor.
     * @param null|AbstractContent $newContent
     * @param null|AbstractComponent $newComponent
     */
    public function __construct(
        ?AbstractContent $newContent = null,
        ?AbstractComponent $newComponent = null
    ) {
        $this->id = Uuid::uuid4()->getHex();
        if ($newContent) {
            $this->setContent($newContent);
        }
        if ($newComponent) {
            $this->setComponent($newComponent);
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
        if (null === $this->sort || $sortLast !== null) {
            $this->setSort($this->calculateSort($sortLast));
        }
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
     * @return Collection|AbstractFeatureItem[]
     */
    public function getSortCollection(): Collection
    {
        return $this->content ? $this->content->getComponentLocations() : new ArrayCollection;
    }
}
