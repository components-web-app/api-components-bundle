<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Component;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentBundle\Entity\Component\Feature\AbstractFeatureItem;
use Silverback\ApiComponentBundle\Entity\Content\AbstractContent;
use Silverback\ApiComponentBundle\Entity\SortableInterface;
use Silverback\ApiComponentBundle\Entity\SortableTrait;
use Silverback\ApiComponentBundle\Validator\Constraints as ACBAssert;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Class ComponentLocation
 * @package Silverback\ApiComponentBundle\Entity\Component
 * @ACBAssert\ComponentLocation()
 * @ORM\Entity(repositoryClass="Silverback\ApiComponentBundle\Repository\Component\ComponentLocationRepository")
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
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Component\AbstractComponent", inversedBy="locations")
     * @ORM\JoinColumn(onDelete="CASCADE")
     * @Groups({"default", "component"})
     * @var AbstractComponent
     */
    private $component;

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
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
     * @return AbstractContent|null
     */
    public function getContent(): ?AbstractContent
    {
        return $this->content;
    }

    public function setContent(?AbstractContent $content, ?bool $sortLast = true): self
    {
        if ($content !== $this->content) {
            $this->content = $content;
            if (null === $this->sort || $sortLast !== null) {
                $this->setSort($this->calculateSort($sortLast));
            }
            if ($content) {
                $content->addComponentLocation($this);
            }
        }
        return $this;
    }

    /**
     * @return AbstractComponent
     */
    public function getComponent(): AbstractComponent
    {
        return $this->component;
    }

    public function setComponent(AbstractComponent $component): self
    {
        $this->component = $component;
        return $this;
    }

    /**
     * @return Collection|AbstractFeatureItem[]|null
     */
    public function getSortCollection(): ?Collection
    {
        return $this->getContent() ? $this->getContent()->getComponentLocations() : null;
    }

    public function __toString()
    {
        $content = $this->getContent();
        return sprintf('location/%s/%s', $content ? get_class($content) : 'no-content', get_class($this->getComponent()));
    }
}
