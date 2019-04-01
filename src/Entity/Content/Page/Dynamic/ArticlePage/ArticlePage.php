<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Content\Page\Dynamic\ArticlePage;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\FileInterface;
use Silverback\ApiComponentBundle\Entity\Component\FileTrait;
use Silverback\ApiComponentBundle\Entity\Content\Page\Dynamic\DynamicContent;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * @ORM\Entity()
 * @ORM\AttributeOverrides({
 *      @ORM\AttributeOverride(
 *          name="filePath",
 *          column=@ORM\Column(
 *              nullable=true
 *          )
 *      )
 * })
 */
final class ArticlePage extends DynamicContent implements FileInterface
{
    use FileTrait;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @Groups({"default"})
     * @var null|string
     */
    private $subtitle;

    /**
     * @ORM\Column(type="text")
     * @Groups({"default"})
     * @var string
     */
    private $content = '';

    /**
     * @ORM\Column(type="string", nullable=true)
     * @Groups({"default"})
     * @var null|string
     */
    private $imageCaption;

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): self
    {
        $this->subtitle = $subtitle;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function setImageCaption(string $imageCaption): self
    {
        $this->imageCaption = $imageCaption;
        return $this;
    }

    public function getImageCaption(): ?string
    {
        return $this->imageCaption;
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $metadata->addPropertyConstraint(
            'title',
            new Assert\NotNull()
        );

        $metadata->addPropertyConstraint(
            'content',
            new Assert\NotNull()
        );
    }
}
