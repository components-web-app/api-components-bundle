<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Component\Article;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\Component\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Content\Component\FileInterface;
use Silverback\ApiComponentBundle\Entity\Content\Component\FileTrait;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Class News
 * @package Silverback\ApiComponentBundle\Entity\Content\Component\News
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource(shortName="Component/Article")
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
class Article extends AbstractComponent implements FileInterface
{
    use FileTrait;

    /**
     * @ORM\Column(type="string", nullable=false)
     * @Groups({"content", "component"})
     * @var null|string
     */
    private $title;

    /**
     * @ORM\Column(type="string")
     * @Groups({"content", "component"})
     * @var null|string
     */
    private $subtitle;

    /**
     * @ORM\Column(type="text")
     * @Groups({"content", "component"})
     * @var string
     */
    private $content;

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $metadata->addPropertyConstraint(
            'filePath',
            new Assert\Image()
        );

        $metadata->addPropertyConstraint(
            'title',
            new Assert\NotNull()
        );

        $metadata->addPropertyConstraint(
            'content',
            new Assert\NotNull()
        );
    }

    /**
     * @return null|string
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param null|string $title
     */
    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return null|string
     */
    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    /**
     * @param null|string $subtitle
     */
    public function setSubtitle(?string $subtitle): void
    {
        $this->subtitle = $subtitle;
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @param string $content
     */
    public function setContent(string $content): void
    {
        $this->content = $content;
    }
}
