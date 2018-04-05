<?php

namespace Silverback\ApiComponentBundle\Entity\Content\Dynamic;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Content\Component\ComponentLocation;
use Silverback\ApiComponentBundle\Entity\Content\Component\Content\Content;
use Silverback\ApiComponentBundle\Entity\Content\Component\Hero\Hero;
use Silverback\ApiComponentBundle\Entity\Content\FileInterface;
use Silverback\ApiComponentBundle\Entity\Content\FileTrait;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 *
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource()
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

    public function getComponentLocations(): Collection
    {
        return new ArrayCollection(
            [
                new ComponentLocation(null, $this->getHeroComponent()),
                new ComponentLocation(null, $this->getContentComponent())
            ]
        );
    }

    private function getHeroComponent() {
        $hero = new Hero();
        $hero->setTitle($this->getTitle());
        $hero->setSubtitle($this->subtitle);
        return $hero;
    }

    private function getContentComponent() {
        $content = new Content();
        $content->setContent($this->content);
        return $content;
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
