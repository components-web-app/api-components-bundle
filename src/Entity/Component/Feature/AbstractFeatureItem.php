<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Component\Feature;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Route\Route;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Class AbstractFeatureItem
 * @package Silverback\ApiComponentBundle\Entity\Component\Feature
 */
abstract class AbstractFeatureItem extends AbstractComponent implements FeatureItemInterface
{
    /**
     * @ORM\Column()
     * @Groups({"component", "content"})
     * @var string
     */
    protected $title;

    /**
     * @ORM\Column()
     * @Groups({"component", "content"})
     * @var string|null
     */
    protected $url;

    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Route\Route")
     * @ORM\JoinColumn(referencedColumnName="route")
     * @Groups({"component", "content"})
     * @var Route|null
     */
    protected $route;

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

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return null|string
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * @param null|string $url
     */
    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    /**
     * @return null|Route
     */
    public function getRoute(): ?Route
    {
        return $this->route;
    }

    /**
     * @param null|Route $route
     */
    public function setRoute(?Route $route): void
    {
        $this->route = $route;
    }
}
