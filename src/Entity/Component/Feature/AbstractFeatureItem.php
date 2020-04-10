<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Component\Feature;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Route\Route;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

abstract class AbstractFeatureItem extends AbstractComponent implements FeatureItemInterface
{
    /**
     * @ORM\Column()
     * @Groups({"component", "content"})
     * @var string
     */
    protected $title;

    /**
     * @ORM\Column(nullable=true)
     * @Groups({"component", "content"})
     * @var string|null
     */
    protected $url;

    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Route\Route")
     * @ORM\JoinColumn(referencedColumnName="id", onDelete="SET NULL")
     * @Groups({"component", "content"})
     * @var Route|null
     */
    protected $route;

    /**
     * @ORM\Column(type="text")
     * @Groups({"component", "content"})
     * @var null|string
     */
    protected $description;

    public static function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint(
            'title',
            new Assert\NotBlank()
        );
        $metadata->addPropertyConstraint(
            'url',
            new Assert\Url()
        );
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): AbstractFeatureItem
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): AbstractFeatureItem
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @return null|Route
     */
    public function getRoute(): ?Route
    {
        return $this->route;
    }

    public function setRoute(?Route $route): AbstractFeatureItem
    {
        $this->route = $route;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): AbstractFeatureItem
    {
        $this->description = $description;
        return $this;
    }
}
