<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Content\Page\Dynamic;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Component\FileInterface;
use Silverback\ApiComponentBundle\Entity\Component\FileTrait;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 *
 * @author Daniel West <daniel@silverback.is>
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
class ArticlePage extends AbstractDynamicPage implements FileInterface
{
    use FileTrait;

    /**
     * @ORM\Column(type="string")
     * @Groups({"content", "component", "route"})
     * @var null|string
     */
    private $subtitle;

    /**
     * @ORM\Column(type="text")
     * @Groups({"content", "component", "route"})
     * @var string
     */
    private $content = '';

    /**
     * @ORM\Column(type="string")
     * @Groups({"content", "component", "route"})
     * @var null|string
     */
    private $imageCaption;

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
//        $metadata->addPropertyConstraint(
//            'filePath',
//            new Assert\Image()
//        );

        $metadata->addPropertyConstraint(
            'title',
            new Assert\NotNull()
        );

        $metadata->addPropertyConstraint(
            'content',
            new Assert\NotNull()
        );
    }

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
}
