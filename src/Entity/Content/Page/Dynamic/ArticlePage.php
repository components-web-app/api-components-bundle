<?php

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
    private $content;

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

    /**
     * @param null|string $subtitle
     */
    public function setSubtitle(?string $subtitle): void
    {
        $this->subtitle = $subtitle;
    }

    /**
     * @param string $content
     */
    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    /**
     * @return null|string
     */
    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }
}
